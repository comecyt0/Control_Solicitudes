<?php
/**
 * COMECyT — API Servicio Social (Portal SS)
 * Acciones AJAX: mover_tarea, subir_evidencia, registrar_asistencia,
 *                listar_tareas, listar_evidencias, listar_asistencia
 *
 * Solo accesible para usuarios con sesión SS activa.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionSS();
header('Content-Type: application/json; charset=utf-8');

$pdo   = getConnection();
$ssId  = (int) $_SESSION['ss_id'];
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Función helper para respuesta JSON
function ssJson(bool $ok, array $datos = [], string $error = ''): never {
    echo json_encode($ok
        ? array_merge(['ok' => true], $datos)
        : ['ok' => false, 'error' => $error]
    , JSON_UNESCAPED_UNICODE);
    exit;
}

// ──────────────────────────────────────────────────────────────
// GET: Listar tareas
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar_tareas') {
    $stmt = $pdo->prepare(
        "SELECT t.id, t.titulo, t.columna, t.prioridad, t.color, t.fecha_limite,
                u.nombre || ' ' || u.appat AS asignado_nombre
         FROM ss_kanban_tareas t
         LEFT JOIN ss_usuarios u ON t.asignado_a = u.id
         WHERE t.asignado_a = :uid OR t.asignado_a IS NULL
         ORDER BY t.created_at DESC"
    );
    $stmt->execute([':uid' => $ssId]);
    ssJson(true, ['tareas' => $stmt->fetchAll()]);
}

// ──────────────────────────────────────────────────────────────
// GET: Listar evidencias de una tarea
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar_evidencias') {
    $tareaId = (int) ($_GET['tarea_id'] ?? 0);
    if (!$tareaId) ssJson(false, error: 'tarea_id requerido');

    // Verificar que el SS tiene acceso a la tarea
    $chk = $pdo->prepare("SELECT id FROM ss_kanban_tareas WHERE id = :tid AND (asignado_a = :uid OR asignado_a IS NULL)");
    $chk->execute([':tid' => $tareaId, ':uid' => $ssId]);
    if (!$chk->fetch()) ssJson(false, error: 'Sin acceso a esta tarea');

    $stmt = $pdo->prepare(
        "SELECT e.id, e.tipo, e.archivo, e.nombre_original, e.descripcion,
                TO_CHAR(e.created_at AT TIME ZONE 'America/Mexico_City','DD/MM/YYYY HH24:MI') AS created_at,
                u.nombre || ' ' || u.appat AS usuario_nombre
         FROM ss_evidencias e
         JOIN ss_usuarios u ON e.usuario_id = u.id
         WHERE e.tarea_id = :tid
         ORDER BY e.created_at DESC"
    );
    $stmt->execute([':tid' => $tareaId]);
    ssJson(true, ['evidencias' => $stmt->fetchAll()]);
}

// ──────────────────────────────────────────────────────────────
// POST: Validar CSRF para todas las siguientes acciones
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ssJson(false, error: 'Método no permitido');

$csrfPost = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfPost)) {
    ssJson(false, error: 'Token CSRF inválido');
}

// ──────────────────────────────────────────────────────────────
// POST: Mover tarea entre columnas (restricción: solo pendiente↔en_proceso↔completada)
// ──────────────────────────────────────────────────────────────
if ($accion === 'mover_tarea') {
    $tareaId    = (int) ($_POST['tarea_id'] ?? 0);
    $nuevaCol   = trim($_POST['columna'] ?? '');
    $colsVal    = ['pendiente', 'en_proceso', 'completada'];

    if (!$tareaId || !in_array($nuevaCol, $colsVal, true)) {
        ssJson(false, error: 'Parámetros inválidos');
    }

    // Solo puede mover sus propias o asignadas a todos (asignado_a IS NULL)
    $chk = $pdo->prepare("SELECT columna FROM ss_kanban_tareas WHERE id = :tid AND (asignado_a = :uid OR asignado_a IS NULL)");
    $chk->execute([':tid' => $tareaId, ':uid' => $ssId]);
    $tarea = $chk->fetch();
    if (!$tarea) ssJson(false, error: 'Sin acceso a esta tarea');

    // Reglas: solo puede avanzar/retroceder en secuencia lógica
    $secuencia = array_flip($colsVal);
    $actualIdx = $secuencia[$tarea['columna']];
    $nuevoIdx  = $secuencia[$nuevaCol];

    if (abs($actualIdx - $nuevoIdx) !== 1) {
        ssJson(false, error: 'Solo puedes mover una columna a la vez');
    }
    // No puede mover hacia atrás desde "completada"
    if ($tarea['columna'] === 'completada') {
        ssJson(false, error: 'No puedes reabrir una tarea completada');
    }

    $upd = $pdo->prepare("UPDATE ss_kanban_tareas SET columna = :col, updated_at = NOW() WHERE id = :tid");
    $upd->execute([':col' => $nuevaCol, ':tid' => $tareaId]);
    ssJson(true, ['mensaje' => 'Tarea movida a ' . $nuevaCol]);
}

// ──────────────────────────────────────────────────────────────
// POST: Subir evidencia (foto, documento o nota)
// ──────────────────────────────────────────────────────────────
if ($accion === 'subir_evidencia') {
    $tareaId = (int) ($_POST['tarea_id'] ?? 0);
    $tipo    = trim($_POST['tipo'] ?? 'nota');
    $desc    = trim($_POST['descripcion'] ?? '');
    $tipos   = ['foto', 'documento', 'nota'];

    if (!$tareaId || !in_array($tipo, $tipos, true)) ssJson(false, error: 'Parámetros inválidos');

    // Verificar acceso a la tarea
    $chk = $pdo->prepare("SELECT id FROM ss_kanban_tareas WHERE id = :tid AND (asignado_a = :uid OR asignado_a IS NULL)");
    $chk->execute([':tid' => $tareaId, ':uid' => $ssId]);
    if (!$chk->fetch()) ssJson(false, error: 'Sin acceso a la tarea');

    $archivoRuta = null;
    $nombreOrig  = null;

    if ($tipo !== 'nota' && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['archivo'];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($file['size'] > $maxSize) ssJson(false, error: 'El archivo supera 10 MB');

        // Validar MIME
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($file['tmp_name']);
        $allowImg = ['image/jpeg','image/png','image/gif','image/webp'];
        $allowDoc = ['application/pdf','application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                     'application/vnd.ms-excel',
                     'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $allowed  = array_merge($allowImg, $allowDoc);

        if (!in_array($mime, $allowed, true)) ssJson(false, error: 'Tipo de archivo no permitido');

        $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nombreHash = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
        $uploadDir  = __DIR__ . '/../../public/uploads/ss/';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $nombreHash)) {
            ssJson(false, error: 'Error al guardar el archivo');
        }

        $archivoRuta = $nombreHash;
        $nombreOrig  = basename($file['name']);
    }

    if ($tipo === 'nota' && empty($desc)) ssJson(false, error: 'La nota no puede estar vacía');

    $ins = $pdo->prepare(
        "INSERT INTO ss_evidencias (tarea_id, usuario_id, tipo, archivo, nombre_original, descripcion)
         VALUES (:tid, :uid, :tipo, :arch, :orig, :desc)"
    );
    $ins->execute([
        ':tid'  => $tareaId,
        ':uid'  => $ssId,
        ':tipo' => $tipo,
        ':arch' => $archivoRuta,
        ':orig' => $nombreOrig,
        ':desc' => $desc ?: null,
    ]);

    ssJson(true, ['mensaje' => 'Evidencia registrada']);
}

// ──────────────────────────────────────────────────────────────
// POST: Registrar asistencia (entrada o salida) — timestamp servidor
// ──────────────────────────────────────────────────────────────
if ($accion === 'registrar_asistencia') {
    $tipo = trim($_POST['tipo'] ?? '');
    if (!in_array($tipo, ['entrada', 'salida'], true)) ssJson(false, error: 'Tipo inválido');

    // Verificar el último registro de hoy
    $hoy = date('Y-m-d');
    $stmtUlt = $pdo->prepare(
        "SELECT tipo FROM ss_asistencia
         WHERE usuario_id = :uid AND DATE(fecha_hora) = :hoy
         ORDER BY fecha_hora DESC LIMIT 1"
    );
    $stmtUlt->execute([':uid' => $ssId, ':hoy' => $hoy]);
    $ultimoTipo = $stmtUlt->fetchColumn() ?: null;

    if ($tipo === 'entrada' && $ultimoTipo === 'entrada') {
        ssJson(false, error: 'Ya registraste entrada. Primero registra tu salida.');
    }
    if ($tipo === 'salida' && ($ultimoTipo === null || $ultimoTipo === 'salida')) {
        ssJson(false, error: 'No tienes una entrada activa hoy.');
    }

    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $ins = $pdo->prepare(
        "INSERT INTO ss_asistencia (usuario_id, tipo, ip, fecha_hora)
         VALUES (:uid, :tipo, :ip, NOW())"
    );
    $ins->execute([':uid' => $ssId, ':tipo' => $tipo, ':ip' => $ip]);

    $hora = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('H:i:s');
    ssJson(true, ['mensaje' => ucfirst($tipo) . ' registrada a las ' . $hora]);
}

ssJson(false, error: 'Acción no reconocida');
