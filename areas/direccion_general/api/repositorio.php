<?php
/**
 * COMECyT — API: Repositorio de Documentos (Dirección General)
 * Gestión de carpetas y archivos. Solo accesible para admins DG.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

verificarSesionAdmin();
header('Content-Type: application/json; charset=utf-8');

$pdo     = getConnection();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$accion  = $_GET['accion'] ?? $_POST['accion'] ?? '';

// ── Tipos MIME permitidos ────────────────────────────────────────
const MIME_PERMITIDOS = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip', 'application/x-zip-compressed',
    'text/plain', 'text/csv',
];

const MAX_SIZE = 52_428_800; // 50 MB

function repoJson(bool $ok, array $datos = [], string $error = ''): never {
    echo json_encode(
        $ok ? array_merge(['ok' => true], $datos)
            : ['ok' => false, 'error' => $error],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// ═══════════════════════════════════════════════════════════════
// GET
// ═══════════════════════════════════════════════════════════════

// GET: Listar árbol de carpetas
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar_carpetas') {
    $stmt = $pdo->query(
        "SELECT id, nombre, padre_id FROM dg_repo_carpetas ORDER BY padre_id NULLS FIRST, nombre"
    );
    repoJson(true, ['carpetas' => $stmt->fetchAll()]);
}

// GET: Listar archivos de una carpeta
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar_archivos') {
    $carpetaId = (int) ($_GET['carpeta_id'] ?? 1);
    $stmt = $pdo->prepare(
        "SELECT id, nombre_original, archivo_path, tipo_mime, tamano_bytes,
                TO_CHAR(created_at, 'DD/MM/YYYY HH24:MI') AS fecha
         FROM dg_repo_archivos
         WHERE carpeta_id = :cid
         ORDER BY created_at DESC"
    );
    $stmt->execute([':cid' => $carpetaId]);
    repoJson(true, ['archivos' => $stmt->fetchAll()]);
}

// ═══════════════════════════════════════════════════════════════
// POST — Validar CSRF
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') repoJson(false, error: 'Método no permitido');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    repoJson(false, error: 'Token CSRF inválido');
}

// ────────────────────────────────────────────────────────────────
// POST: Crear carpeta
// ────────────────────────────────────────────────────────────────
if ($accion === 'crear_carpeta') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $padreId  = (int) ($_POST['padre_id'] ?? 1) ?: 1;
    if (empty($nombre)) repoJson(false, error: 'El nombre es obligatorio');
    if (strlen($nombre) > 120) repoJson(false, error: 'Nombre demasiado largo');

    // Verificar que no haya duplicado en el mismo padre
    $chk = $pdo->prepare("SELECT id FROM dg_repo_carpetas WHERE nombre = :n AND padre_id = :p");
    $chk->execute([':n' => $nombre, ':p' => $padreId]);
    if ($chk->fetch()) repoJson(false, error: 'Ya existe una carpeta con ese nombre aquí');

    $ins = $pdo->prepare(
        "INSERT INTO dg_repo_carpetas (nombre, padre_id, creado_por) VALUES (:n, :p, :a) RETURNING id"
    );
    $ins->execute([':n' => $nombre, ':p' => $padreId, ':a' => $adminId]);
    $newId = $ins->fetchColumn();

    // Crear directorio físico
    $dir = __DIR__ . '/../../../public/uploads/dg_repo/' . $newId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    repoJson(true, ['id' => $newId, 'mensaje' => 'Carpeta creada']);
}

// ────────────────────────────────────────────────────────────────
// POST: Subir archivo
// ────────────────────────────────────────────────────────────────
if ($accion === 'subir_archivo') {
    $carpetaId = (int) ($_POST['carpeta_id'] ?? 1);
    if (!$carpetaId) repoJson(false, error: 'Carpeta no especificada');
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        repoJson(false, error: 'Error en la subida del archivo');
    }

    $file      = $_FILES['archivo'];
    $tamano    = $file['size'];
    $tmpPath   = $file['tmp_name'];
    $nombreOrig = basename($file['name']);

    if ($tamano > MAX_SIZE) repoJson(false, error: 'El archivo supera 50 MB');

    // Verificar MIME real
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($tmpPath);
    if (!in_array($mime, MIME_PERMITIDOS, true)) {
        repoJson(false, error: 'Tipo de archivo no permitido: ' . $mime);
    }

    // Nombre único en disco (UUID + ext. original)
    $ext      = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
    $uuidName = bin2hex(random_bytes(12)) . ($ext ? '.' . $ext : '');
    $destDir  = __DIR__ . '/../../../public/uploads/dg_repo/' . $carpetaId;
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $destPath = $destDir . '/' . $uuidName;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        repoJson(false, error: 'No se pudo guardar el archivo en el servidor');
    }

    $ins = $pdo->prepare(
        "INSERT INTO dg_repo_archivos (carpeta_id, nombre_original, archivo_path, tipo_mime, tamano_bytes, creado_por)
         VALUES (:c, :n, :p, :m, :s, :a) RETURNING id"
    );
    $ins->execute([
        ':c' => $carpetaId,
        ':n' => $nombreOrig,
        ':p' => 'dg_repo/' . $carpetaId . '/' . $uuidName,
        ':m' => $mime,
        ':s' => $tamano,
        ':a' => $adminId,
    ]);
    $newId = $ins->fetchColumn();

    repoJson(true, ['id' => $newId, 'mensaje' => 'Archivo subido correctamente']);
}

// ────────────────────────────────────────────────────────────────
// POST: Eliminar archivo
// ────────────────────────────────────────────────────────────────
if ($accion === 'eliminar_archivo') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) repoJson(false, error: 'ID requerido');

    $stmt = $pdo->prepare("SELECT archivo_path FROM dg_repo_archivos WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    if (!$fila) repoJson(false, error: 'Archivo no encontrado');

    // Eliminar físicamente
    $fisico = __DIR__ . '/../../../public/uploads/' . $fila['archivo_path'];
    if (file_exists($fisico)) unlink($fisico);

    $pdo->prepare("DELETE FROM dg_repo_archivos WHERE id = ?")->execute([$id]);
    repoJson(true, ['mensaje' => 'Archivo eliminado']);
}

// ────────────────────────────────────────────────────────────────
// POST: Eliminar carpeta (cascade BD + archivos físicos)
// ────────────────────────────────────────────────────────────────
if ($accion === 'eliminar_carpeta') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id || $id === 1) repoJson(false, error: 'No se puede eliminar la carpeta raíz');

    // Recopilar todos los archivos de la carpeta (y sub-carpetas via cascade SQL luego del delete)
    $stmt = $pdo->prepare(
        "SELECT archivo_path FROM dg_repo_archivos
         WHERE carpeta_id IN (
             WITH RECURSIVE sub AS (
                 SELECT id FROM dg_repo_carpetas WHERE id = ?
                 UNION ALL
                 SELECT c.id FROM dg_repo_carpetas c INNER JOIN sub s ON c.padre_id = s.id
             ) SELECT id FROM sub
         )"
    );
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $f) {
        $fisico = __DIR__ . '/../../../public/uploads/' . $f['archivo_path'];
        if (file_exists($fisico)) unlink($fisico);
    }

    $pdo->prepare("DELETE FROM dg_repo_carpetas WHERE id = ?")->execute([$id]);
    repoJson(true, ['mensaje' => 'Carpeta eliminada']);
}

// ────────────────────────────────────────────────────────────────
// POST: Renombrar carpeta o archivo
// ────────────────────────────────────────────────────────────────
if ($accion === 'renombrar') {
    $tipo   = $_POST['tipo'] ?? '';   // 'carpeta' | 'archivo'
    $id     = (int) ($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    if (!$id || empty($nombre)) repoJson(false, error: 'ID y nombre requeridos');

    if ($tipo === 'carpeta') {
        $pdo->prepare("UPDATE dg_repo_carpetas SET nombre = ? WHERE id = ?")->execute([$nombre, $id]);
    } elseif ($tipo === 'archivo') {
        $pdo->prepare("UPDATE dg_repo_archivos SET nombre_original = ? WHERE id = ?")->execute([$nombre, $id]);
    } else {
        repoJson(false, error: 'Tipo inválido');
    }
    repoJson(true, ['mensaje' => 'Renombrado correctamente']);
}

repoJson(false, error: 'Acción no reconocida');
