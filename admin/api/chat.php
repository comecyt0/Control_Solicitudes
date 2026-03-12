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
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'No autorizado']));
}

$pdo     = getConnection();
$adminId = (int) $_SESSION['admin_id'];
$accion  = $_GET['accion'] ?? $_POST['accion'] ?? '';

// ------------------------------------------------------------------
// LISTAR: Devuelve los últimos mensajes del canal grupal o de un DM
// Parámetros GET:
//   desde       → ID del último mensaje conocido (long-polling optimizado)
//   destinatario → ID del admin destino para filtrar DMs bidireccionales
// ------------------------------------------------------------------
if ($accion === 'listar') {
    $desde        = (int) ($_GET['desde'] ?? 0);
    $destinatario = isset($_GET['destinatario']) ? (int) $_GET['destinatario'] : null;

    if ($destinatario !== null && $destinatario > 0) {
        // Canal DM bidireccional: mensajes entre los dos admins
        $sql = "SELECT m.id, m.admin_id, m.destinatario_id, m.mensaje, m.tipo,
                       m.ref_id, m.ref_titulo,
                       TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS hora,
                       TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'DD/MM/YYYY') AS fecha_dia,
                       a.nombre AS admin_nombre
                FROM sb_chat_mensajes m
                INNER JOIN administradores a ON a.id = m.admin_id
                WHERE m.id > :desde
                  AND (
                    (m.admin_id = :yo AND m.destinatario_id = :ellos)
                    OR
                    (m.admin_id = :ellos2 AND m.destinatario_id = :yo2)
                  )
                ORDER BY m.id ASC
                LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':desde'  => $desde,
            ':yo'     => $adminId,
            ':ellos'  => $destinatario,
            ':ellos2' => $destinatario,
            ':yo2'    => $adminId,
        ]);
    } else {
        // Canal grupal: mensajes sin destinatario privado
        $sql = "SELECT m.id, m.admin_id, m.destinatario_id, m.mensaje, m.tipo,
                       m.ref_id, m.ref_titulo,
                       TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS hora,
                       TO_CHAR(m.fecha AT TIME ZONE 'America/Mexico_City', 'DD/MM/YYYY') AS fecha_dia,
                       a.nombre AS admin_nombre
                FROM sb_chat_mensajes m
                INNER JOIN administradores a ON a.id = m.admin_id
                WHERE m.id > :desde
                  AND m.destinatario_id IS NULL
                ORDER BY m.id ASC
                LIMIT 80";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':desde' => $desde]);
    }

    $mensajes = $stmt->fetchAll();
    echo json_encode(['ok' => true, 'mensajes' => $mensajes]);
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
    $destinatario = !empty($_POST['destinatario']) ? (int) $_POST['destinatario'] : null;

    if (mb_strlen($texto) < 1 || mb_strlen($texto) > 2000) {
        echo json_encode(['ok' => false, 'error' => 'Mensaje inválido.']);
        exit;
    }

    // Validar que el destinatario existe y está activo si se especificó
    if ($destinatario !== null) {
        $chk = $pdo->prepare("SELECT id FROM administradores WHERE id = ? AND activo = true");
        $chk->execute([$destinatario]);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Destinatario inválido.']);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO sb_chat_mensajes (admin_id, destinatario_id, mensaje, tipo)
         VALUES (?, ?, ?, 'texto')"
    );
    $stmt->execute([$adminId, $destinatario, $texto]);

    // lastInsertId() no aplica en PostgreSQL para diferentes tablas; usamos RETURNING
    $nuevoId = (int) $pdo->query("SELECT lastval()")->fetchColumn();

    echo json_encode(['ok' => true, 'id' => $nuevoId]);
    exit;
}

// ------------------------------------------------------------------
// ADMINS: Lista de administradores activos para el panel de DMs
// Incluye inicial y rol para el UI
// ------------------------------------------------------------------
if ($accion === 'admins') {
    $stmt = $pdo->query(
        "SELECT id, nombre, rol,
                UPPER(SUBSTRING(nombre FROM 1 FOR 1)) AS inicial
         FROM administradores
         WHERE activo = true
         ORDER BY nombre"
    );
    $admins = $stmt->fetchAll();
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
        "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a)
         VALUES (?, ?, ?, 'pendiente', ?, ?) RETURNING id"
    );
    $stmtT->execute([$titulo, $descripcion, $color, $adminId, $asignadoA]);
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
