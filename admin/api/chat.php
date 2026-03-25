<?php
/**
 * COMECyT Control de Solicitudes
 * API AJAX — Chat Grupal + Mensajes Directos (DM) de Administradores
 *
 * Acciones disponibles (?accion=):
 *   listar        → GET  Devuelve mensajes del canal/DM
 *   enviar        → POST Inserta un mensaje (grupal o DM)
 *   admins        → GET  Lista de admins para DMs
 *   crear_tarea   → POST Crea tarea en sb_kanban_tareas + mensaje tipo='tarea'
 *   crear_evento  → POST Crea evento en eventos + mensaje tipo='evento'
 *
 * Solo accesible para administradores autenticados.
 * Compatible 100% con PostgreSQL 15+. Responde siempre en JSON.
 *
 * @note DATE_FORMAT() es MySQL solamente. En PostgreSQL usamos TO_CHAR().
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

// Garantizar JSON siempre
header('Content-Type: application/json; charset=utf-8');

// Iniciar sesión y verificar autenticación
inicializarSesion();
if (empty($_SESSION['admin_id']) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'No autorizado']));
}

$pdo     = getConnection();
$adminId  = $_SESSION['admin_id'] ?? null;
$personalId = $_SESSION['user_id'] ?? null;
$cveArea  = (int) ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0);
$accion   = $_GET['accion'] ?? $_POST['accion'] ?? '';

// ------------------------------------------------------------------
// LISTAR: Devuelve los últimos mensajes del canal grupal o de un DM con reacciones
// Parámetros GET:
//   desde       → ID del último mensaje conocido
//   destinatario → ID del admin destino para filtrar DMs
// ------------------------------------------------------------------
if ($accion === 'listar') {
    $desde        = (int) ($_GET['desde'] ?? 0);
    $destinatarioRaw = $_GET['destinatario'] ?? null;
    
    $destAdminId = null;
    $destUserId  = null;
    if ($destinatarioRaw) {
        if (str_starts_with($destinatarioRaw, 'A')) $destAdminId = (int) substr($destinatarioRaw, 1);
        elseif (str_starts_with($destinatarioRaw, 'P')) $destUserId = (int) substr($destinatarioRaw, 1);
        else $destAdminId = (int)$destinatarioRaw; // Fallback legacy
    }

    $params = [':desde' => $desde];
    $where  = "m.id > :desde ";

    if ($destAdminId || $destUserId) {
        // Lógica de DM Híbrida
        $idPropioAdmin = $adminId;
        $idPropioUser  = $personalId;

        $where .= " AND (
            (m.admin_id = :yoA AND :yoA IS NOT NULL AND m.destinatario_id = :destA) OR 
            (m.usuario_id = :yoU AND :yoU IS NOT NULL AND m.destinatario_usuario_id = :destU) OR
            (m.admin_id = :yoA2 AND m.destinatario_usuario_id = :destU2) OR
            (m.usuario_id = :yoU2 AND m.destinatario_id = :destA2) OR
            (m.admin_id = :destA3 AND m.destinatario_id = :yoA3) OR
            (m.usuario_id = :destU3 AND m.destinatario_usuario_id = :yoU3) OR
            (m.admin_id = :destA4 AND m.destinatario_usuario_id = :yoU4) OR
            (m.usuario_id = :destU5 AND m.destinatario_id = :yoA5)
        )";
        // Esto es complejo pero cubre todas las combinaciones A->A, U->U, A->U, U->A
        $params[':yoA']   = $idPropioAdmin;   $params[':destA']  = $destAdminId;
        $params[':yoU']   = $idPropioUser;    $params[':destU']  = $destUserId;
        $params[':yoA2']  = $idPropioAdmin;   $params[':destU2'] = $destUserId;
        $params[':yoU2']  = $idPropioUser;    $params[':destA2'] = $destAdminId;
        $params[':destA3'] = $destAdminId;    $params[':yoA3']   = $idPropioAdmin;
        $params[':destU3'] = $destUserId;     $params[':yoU3']   = $idPropioUser;
        $params[':destA4'] = $destAdminId;    $params[':yoU4']   = $idPropioUser;
        $params[':destU5'] = $destUserId;     $params[':yoA5']   = $idPropioAdmin;
    } else {
        $where .= " AND m.destinatario_id IS NULL AND m.destinatario_usuario_id IS NULL AND (p.cve_area = :cve_area OR p2.cve_area = :cve_area2)";
        $params[':cve_area'] = $cveArea;
        $params[':cve_area2'] = $cveArea;
    }

    if ($desde === 0) {
        // Carga inicial: obtener los ÚLTIMOS 50 para empezar en el presente
        $sql = "SELECT * FROM (
                    SELECT m.id, 
                           COALESCE('A' || m.admin_id, 'P' || m.usuario_id) AS admin_id,
                           COALESCE('A' || m.destinatario_id, 'P' || m.destinatario_usuario_id) AS destinatario_id,
                           m.mensaje, m.tipo,
                           m.ref_id, m.ref_titulo,
                           TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS hora,
                           TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'DD/MM/YYYY') AS fecha_dia,
                           COALESCE(a.nombre, p2.nombre) AS admin_nombre,
                           (
                               SELECT json_agg(json_build_object('emoji', r.emoji, 'admin_id', COALESCE('A' || r.admin_id, 'P' || r.usuario_id), 'nombre', COALESCE(ra.nombre, rp.nombre)))
                               FROM sb_chat_reacciones r
                               LEFT JOIN administradores ra ON ra.id = r.admin_id
                               LEFT JOIN cat_personal rp ON rp.id_personal = r.usuario_id
                               WHERE r.mensaje_id = m.id
                           ) as reacciones
                    FROM sb_chat_mensajes m
                    LEFT JOIN administradores a ON a.id = m.admin_id
                    LEFT JOIN cat_personal p2 ON p2.id_personal = m.usuario_id
                    LEFT JOIN cat_personal p ON (p.correo_institucional = a.email OR p.correo_personal = a.email)
                    WHERE $where
                    ORDER BY m.id DESC
                    LIMIT 50
                ) sub
                ORDER BY id ASC";
    } else {
        // Polling: obtener solo lo nuevo desde el último ID conocido
        $sql = "SELECT m.id, 
                       COALESCE('A' || m.admin_id, 'P' || m.usuario_id) AS admin_id,
                       COALESCE('A' || m.destinatario_id, 'P' || m.destinatario_usuario_id) AS destinatario_id,
                       m.mensaje, m.tipo,
                       m.ref_id, m.ref_titulo,
                       TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS hora,
                       TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'DD/MM/YYYY') AS fecha_dia,
                       COALESCE(a.nombre, p2.nombre) AS admin_nombre,
                       (
                           SELECT json_agg(json_build_object('emoji', r.emoji, 'admin_id', COALESCE('A' || r.admin_id, 'P' || r.usuario_id), 'nombre', COALESCE(ra.nombre, rp.nombre)))
                           FROM sb_chat_reacciones r
                           LEFT JOIN administradores ra ON ra.id = r.admin_id
                           LEFT JOIN cat_personal rp ON rp.id_personal = r.usuario_id
                           WHERE r.mensaje_id = m.id
                       ) as reacciones
                FROM sb_chat_mensajes m
                LEFT JOIN administradores a ON a.id = m.admin_id
                LEFT JOIN cat_personal p2 ON p2.id_personal = m.usuario_id
                LEFT JOIN cat_personal p ON (p.correo_institucional = a.email OR p.correo_personal = a.email)
                WHERE $where
                ORDER BY m.id ASC
                LIMIT 100";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $mensajes = $stmt->fetchAll();

    // Limpiar nulos de reacciones (json_agg devuelve null si no hay filas)
    foreach ($mensajes as &$m) {
        $m['reacciones'] = json_decode($m['reacciones'] ?? '[]', true);
    }

    echo json_encode(['ok' => true, 'mensajes' => $mensajes]);
    exit;
}

// ------------------------------------------------------------------
// MARCAR LEÍDO: Persistir el último mensaje visto por el admin
// ------------------------------------------------------------------
if ($accion === 'marcar_leido' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ultimoId = (int) ($_POST['ultimo_id'] ?? 0);
    if ($ultimoId > 0) {
        $sql = "INSERT INTO sb_chat_lectura (admin_id, ultimo_id_leido, fecha_actualizacion)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (admin_id) DO UPDATE 
                SET ultimo_id_leido = EXCLUDED.ultimo_id_leido, 
                    fecha_actualizacion = CURRENT_TIMESTAMP";
        $pdo->prepare($sql)->execute([$adminId, $ultimoId]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ------------------------------------------------------------------
// REACCIONAR: Añadir, cambiar o quitar reacción a un mensaje
// ------------------------------------------------------------------
if ($accion === 'reaccionar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    $mensajeId = (int) ($_POST['mensaje_id'] ?? 0);
    $emoji     = trim($_POST['emoji'] ?? '');

    if ($mensajeId > 0 && $emoji) {
        // Si el usuario ya reaccionó con el MISMO emoji -> quitarla (toggle)
        $chk = $pdo->prepare("SELECT id FROM sb_chat_reacciones WHERE mensaje_id = ? AND admin_id = ? AND emoji = ?");
        $chk->execute([$mensajeId, $adminId, $emoji]);
        
        if ($chk->fetch()) {
            $pdo->prepare("DELETE FROM sb_chat_reacciones WHERE mensaje_id = ? AND (admin_id = ? OR usuario_id = ?) AND emoji = ?")
                ->execute([$mensajeId, $adminId, $personalId, $emoji]);
            $res = 'deleted';
        } else {
            $sql = "INSERT INTO sb_chat_reacciones (mensaje_id, admin_id, usuario_id, emoji)
                    VALUES (?, ?, ?, ?)
                    ON CONFLICT (mensaje_id, COALESCE(admin_id, -1), COALESCE(usuario_id, -1)) DO UPDATE SET emoji = EXCLUDED.emoji";
            // Nota: El ON CONFLICT requiere un índice que cubra nulls o usar COALESCE si se cambió la constraint
            $pdo->prepare($sql)->execute([$mensajeId, $adminId, $personalId, $emoji]);
            $res = 'saved';
        }
        echo json_encode(['ok' => true, 'res' => $res]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    }
    exit;
}

// ------------------------------------------------------------------
// ENVIAR: Insertar mensaje de texto plano (grupal o DM)
// Parámetros POST:
//   mensaje      → Texto del mensaje
//   destinatario → (Opcional) ID del admin destino para DM
// ------------------------------------------------------------------
if ($accion === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    $texto        = trim($_POST['mensaje'] ?? '');
    $destinatarioRaw = $_POST['destinatario'] ?? null;
    $destAdminId = null;
    $destUserId  = null;

    if ($destinatarioRaw) {
        if (str_starts_with($destinatarioRaw, 'A')) $destAdminId = (int) substr($destinatarioRaw, 1);
        elseif (str_starts_with($destinatarioRaw, 'P')) $destUserId = (int) substr($destinatarioRaw, 1);
        else $destAdminId = (int) $destinatarioRaw;
    }

    if (mb_strlen($texto) < 1 || mb_strlen($texto) > 2000) {
        echo json_encode(['ok' => false, 'error' => 'Mensaje inválido.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO sb_chat_mensajes (admin_id, usuario_id, destinatario_id, destinatario_usuario_id, mensaje, tipo)
         VALUES (?, ?, ?, ?, ?, 'texto')"
    );
    $stmt->execute([$adminId, $personalId, $destAdminId, $destUserId, $texto]);

    $nuevoId = (int) $pdo->query("SELECT lastval()")->fetchColumn();

    echo json_encode(['ok' => true, 'id' => $nuevoId]);
    exit;
}

// ------------------------------------------------------------------
// ADMINS: Lista de administradores activos para el panel de DMs
// Incluye inicial y rol para el UI
// ------------------------------------------------------------------
if ($accion === 'admins') {
    // Listar TODOS los miembros del área desde cat_personal
    // Pero detectar si tienen una cuenta de admin asociada para usar su AdminID si existe
    $stmt = $pdo->prepare(
        "SELECT p.id_personal, p.nombre, a.id as admin_id, a.rol,
                UPPER(SUBSTRING(p.nombre FROM 1 FOR 1)) AS inicial
         FROM cat_personal p
         LEFT JOIN administradores a ON (p.correo_institucional = a.email OR p.correo_personal = a.email)
         WHERE p.cve_area = ? AND p.habilitado = true
         ORDER BY p.nombre"
    );
    $stmt->execute([$cveArea]);
    $listaRaw = $stmt->fetchAll();
    
    $admins = [];
    foreach ($listaRaw as $row) {
        $admins[] = [
            'id'      => $row['admin_id'] ? 'A' . $row['admin_id'] : 'P' . $row['id_personal'],
            'nombre'  => $row['nombre'],
            'rol'     => $row['rol'] ?? 'Staff',
            'inicial' => $row['inicial']
        ];
    }
    
    echo json_encode(['ok' => true, 'admins' => $admins]);
    exit;
}

// ------------------------------------------------------------------
// CREAR TAREA KANBAN desde el chat
// ------------------------------------------------------------------
if ($accion === 'crear_tarea' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $color       = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3788d8';
    $asignadoA   = !empty($_POST['asignado_a']) ? (int) $_POST['asignado_a'] : null;

    if (mb_strlen($titulo) < 2) {
        echo json_encode(['ok' => false, 'error' => 'Título de tarea requerido.']);
        exit;
    }

    $stmtT = $pdo->prepare(
        "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area)
         VALUES (?, ?, ?, 'pendiente', ?, ?, ?) RETURNING id"
    );
    $stmtT->execute([$titulo, $descripcion, $color, $adminId, $asignadoA, $cveArea]);
    $tareaId = (int) $stmtT->fetchColumn();

    $msgTexto = "📌 Nueva tarea: \"$titulo\"";
    $stmtM = $pdo->prepare(
        "INSERT INTO sb_chat_mensajes (admin_id, mensaje, tipo, ref_id, ref_titulo)
         VALUES (?, ?, 'tarea', ?, ?) RETURNING id"
    );
    $stmtM->execute([$adminId, $msgTexto, $tareaId, $titulo]);
    $nuevoId = (int) $stmtM->fetchColumn();

    echo json_encode(['ok' => true, 'id' => $nuevoId, 'tarea_id' => $tareaId]);
    exit;
}

// ------------------------------------------------------------------
// CREAR EVENTO de Calendario desde el chat
// ------------------------------------------------------------------
if ($accion === 'crear_evento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin    = $_POST['fecha_fin']    ?? $fechaInicio;
    $color       = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3788d8';

    if (mb_strlen($titulo) < 2 || !$fechaInicio) {
        echo json_encode(['ok' => false, 'error' => 'Título y fecha de inicio requeridos.']);
        exit;
    }

    $stmtE = $pdo->prepare(
        "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por)
         VALUES (?, ?, ?, ?, ?, ?) RETURNING id"
    );
    $stmtE->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $adminId]);
    $eventoId = (int) $stmtE->fetchColumn();

    $fechaFormato = date('d/m/Y', strtotime($fechaInicio));
    $msgTexto     = "📅 Evento: \"$titulo\" → $fechaFormato";
    $stmtM = $pdo->prepare(
        "INSERT INTO sb_chat_mensajes (admin_id, mensaje, tipo, ref_id, ref_titulo)
         VALUES (?, ?, 'evento', ?, ?) RETURNING id"
    );
    $stmtM->execute([$adminId, $msgTexto, $eventoId, $titulo]);
    $nuevoId = (int) $stmtM->fetchColumn();

    echo json_encode(['ok' => true, 'id' => $nuevoId, 'evento_id' => $eventoId]);
    exit;
}

// Acción no reconocida
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Acción no reconocida']);
