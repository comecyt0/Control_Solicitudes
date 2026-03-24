<?php
/**
 * COMECyT — API Admin: Servicio Social
 * CRUD de usuarios SS y tareas Kanban SS.
 * Solo accesible para administradores autenticados.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

verificarSesionAdmin();
header('Content-Type: application/json; charset=utf-8');

$pdo     = getConnection();
$adminId = (int) $_SESSION['admin_id'];
$accion  = $_GET['accion'] ?? $_POST['accion'] ?? '';

function ssAdminJson(bool $ok, array $datos = [], string $error = ''): never {
    echo json_encode($ok
        ? array_merge(['ok' => true], $datos)
        : ['ok' => false, 'error' => $error]
    , JSON_UNESCAPED_UNICODE);
    exit;
}

// ──────────────────────────────────────────────────────────────
// GET: Ver usuario SS
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'ver_usuario_ss') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) ssAdminJson(false, error: 'ID requerido');
    $stmt = $pdo->prepare("SELECT id,nombre,appat,apmat,email,institucion,carrera,fecha_inicio,fecha_fin FROM ss_usuarios WHERE id=?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) ssAdminJson(false, error: 'Usuario no encontrado');
    ssAdminJson(true, ['usuario' => $u]);
}

// GET: Ver tarea SS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'ver_tarea_ss') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) ssAdminJson(false, error: 'ID requerido');
    $stmt = $pdo->prepare("SELECT id,titulo,descripcion,columna,prioridad,color,asignado_a,fecha_limite FROM ss_kanban_tareas WHERE id=?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (!$t) ssAdminJson(false, error: 'Tarea no encontrada');
    ssAdminJson(true, ['tarea' => $t]);
}

// ──────────────────────────────────────────────────────────────
// POST: Validar CSRF
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ssAdminJson(false, error: 'Método no permitido');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    ssAdminJson(false, error: 'Token CSRF inválido');
}

// ──────────────────────────────────────────────────────────────
// POST: Crear usuario SS
// ──────────────────────────────────────────────────────────────
if ($accion === 'crear_usuario_ss') {
    $nombre  = trim($_POST['nombre'] ?? '');
    $appat   = trim($_POST['appat'] ?? '');
    $apmat   = trim($_POST['apmat'] ?? '') ?: null;
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';

    if (empty($nombre) || empty($appat) || empty($email) || empty($pass)) {
        ssAdminJson(false, error: 'Nombre, apellido paterno, email y contraseña son obligatorios');
    }
    if (strlen($pass) < 6) ssAdminJson(false, error: 'La contraseña debe tener al menos 6 caracteres');

    // Verificar email único
    $chk = $pdo->prepare("SELECT id FROM ss_usuarios WHERE email = ?");
    $chk->execute([strtolower($email)]);
    if ($chk->fetch()) ssAdminJson(false, error: 'El email ya está registrado');

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $ins = $pdo->prepare(
        "INSERT INTO ss_usuarios (nombre, appat, apmat, email, password_hash, institucion, carrera, fecha_inicio, fecha_fin)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $ins->execute([
        $nombre, $appat, $apmat, strtolower($email), $hash,
        trim($_POST['institucion'] ?? '') ?: null,
        trim($_POST['carrera'] ?? '') ?: null,
        $_POST['fecha_inicio'] ?: null,
        $_POST['fecha_fin'] ?: null,
    ]);
    ssAdminJson(true, ['mensaje' => 'Usuario SS creado']);
}

// ──────────────────────────────────────────────────────────────
// POST: Editar usuario SS
// ──────────────────────────────────────────────────────────────
if ($accion === 'editar_usuario_ss') {
    $id    = (int) ($_POST['id'] ?? 0);
    $pass  = $_POST['password'] ?? '';
    if (!$id) ssAdminJson(false, error: 'ID requerido');

    $sql = "UPDATE ss_usuarios SET
                nombre=?, appat=?, apmat=?, email=?,
                institucion=?, carrera=?, fecha_inicio=?, fecha_fin=?";
    $params = [
        trim($_POST['nombre'] ?? ''),
        trim($_POST['appat'] ?? ''),
        trim($_POST['apmat'] ?? '') ?: null,
        strtolower(trim($_POST['email'] ?? '')),
        trim($_POST['institucion'] ?? '') ?: null,
        trim($_POST['carrera'] ?? '') ?: null,
        $_POST['fecha_inicio'] ?: null,
        $_POST['fecha_fin'] ?: null,
    ];

    if ($pass !== '') {
        if (strlen($pass) < 6) ssAdminJson(false, error: 'Contraseña mínimo 6 caracteres');
        $sql .= ', password_hash=?';
        $params[] = password_hash($pass, PASSWORD_BCRYPT);
    }
    $sql .= ' WHERE id=?';
    $params[] = $id;

    $pdo->prepare($sql)->execute($params);
    ssAdminJson(true, ['mensaje' => 'Usuario SS actualizado']);
}

// ──────────────────────────────────────────────────────────────
// POST: Toggle activo SS
// ──────────────────────────────────────────────────────────────
if ($accion === 'toggle_activo_ss') {
    $id    = (int) ($_POST['id'] ?? 0);
    $activo= filter_var($_POST['activo'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    if (!$id) ssAdminJson(false, error: 'ID requerido');
    $pdo->prepare("UPDATE ss_usuarios SET activo=? WHERE id=?")->execute([$activo, $id]);
    ssAdminJson(true, ['mensaje' => 'Estado actualizado']);
}

// ──────────────────────────────────────────────────────────────
// POST: Crear tarea Kanban SS
// ──────────────────────────────────────────────────────────────
if ($accion === 'crear_tarea_ss') {
    $titulo = trim($_POST['titulo'] ?? '');
    if (empty($titulo)) ssAdminJson(false, error: 'El título es obligatorio');

    $asig   = (int) ($_POST['asignado_a'] ?? 0) ?: null;
    $ins = $pdo->prepare(
        "INSERT INTO ss_kanban_tareas (titulo, descripcion, prioridad, color, asignado_a, creado_por, fecha_limite)
         VALUES (?,?,?,?,?,?,?)"
    );
    $ins->execute([
        $titulo,
        trim($_POST['descripcion'] ?? '') ?: null,
        $_POST['prioridad'] ?? 'media',
        $_POST['color'] ?? '#662331',
        $asig,
        $adminId,
        $_POST['fecha_limite'] ?: null,
    ]);
    ssAdminJson(true, ['mensaje' => 'Tarea SS creada']);
}

// ──────────────────────────────────────────────────────────────
// POST: Editar tarea Kanban SS
// ──────────────────────────────────────────────────────────────
if ($accion === 'editar_tarea_ss') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) ssAdminJson(false, error: 'ID requerido');
    $asig = (int) ($_POST['asignado_a'] ?? 0) ?: null;
    $pdo->prepare(
        "UPDATE ss_kanban_tareas SET titulo=?,descripcion=?,prioridad=?,color=?,asignado_a=?,fecha_limite=?,updated_at=NOW()
         WHERE id=?"
    )->execute([
        trim($_POST['titulo'] ?? ''),
        trim($_POST['descripcion'] ?? '') ?: null,
        $_POST['prioridad'] ?? 'media',
        $_POST['color'] ?? '#662331',
        $asig,
        $_POST['fecha_limite'] ?: null,
        $id,
    ]);
    ssAdminJson(true, ['mensaje' => 'Tarea SS actualizada']);
}

// ──────────────────────────────────────────────────────────────
// POST: Eliminar tarea Kanban SS (ON DELETE CASCADE elimina evidencias)
// ──────────────────────────────────────────────────────────────
if ($accion === 'eliminar_tarea_ss') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) ssAdminJson(false, error: 'ID requerido');
    $pdo->prepare("DELETE FROM ss_kanban_tareas WHERE id=?")->execute([$id]);
    ssAdminJson(true, ['mensaje' => 'Tarea SS eliminada']);
}

// ──────────────────────────────────────────────────────────────
// GET: Listar evidencias de una tarea
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar_evidencias') {
    $tareaId = (int) ($_GET['tarea_id'] ?? 0);
    if (!$tareaId) ssAdminJson(false, error: 'ID de tarea requerido');
    $stmt = $pdo->prepare(
        "SELECT id, tipo, archivo_path, descripcion, 
                TO_CHAR(created_at AT TIME ZONE 'America/Mexico_City', 'DD/MM/YYYY HH24:MI') AS fecha
         FROM ss_evidencias WHERE tarea_id = ? ORDER BY created_at DESC"
    );
    $stmt->execute([$tareaId]);
    ssAdminJson(true, ['evidencias' => $stmt->fetchAll()]);
}

ssAdminJson(false, error: 'Acción no reconocida');
