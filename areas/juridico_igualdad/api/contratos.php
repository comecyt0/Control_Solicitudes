<?php
/**
 * API Contratos — Jurídico Administrativo
 */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

verificarSesionAdmin();
validarCsrfPost();

$pdo     = getConnection();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$accion  = postParam('accion');
$back    = BASE_URL . 'areas/juridico_igualdad/contratos.php';

if ($accion === 'crear') {
    $titulo      = trim(postParam('titulo'));
    $contraparte = trim(postParam('contraparte'));
    $tipo        = postParam('tipo', 'contrato');
    $estatus     = postParam('estatus', 'activo');
    $fi          = postParam('fecha_inicio') ?: null;
    $ff          = postParam('fecha_fin')    ?: null;
    $monto       = postParam('monto')        ? (float) postParam('monto') : null;
    $notas       = trim(postParam('notas'));
    $archivePath = null;

    if (!$titulo) { header("Location: {$back}?flash=error"); exit; }

    // Upload PDF
    if (!empty($_FILES['archivo']['name'])) {
        $ext      = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        $allowed  = ['pdf','doc','docx'];
        if (in_array($ext, $allowed)) {
            $uuid = bin2hex(random_bytes(16)) . '.' . $ext;
            $dir  = __DIR__ . '/../../../public/uploads/ja_contratos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $dir . $uuid)) {
                $archivePath = 'ja_contratos/' . $uuid;
            }
        }
    }

    $pdo->prepare("INSERT INTO ja_contratos (titulo, contraparte, tipo, estatus, fecha_inicio, fecha_fin, monto, archivo_path, notas, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$titulo, $contraparte, $tipo, $estatus, $fi, $ff, $monto, $archivePath, $notas, $adminId]);

    header("Location: {$back}?flash=creado"); exit;

} elseif ($accion === 'editar') {
    $id          = (int) postParam('id');
    $titulo      = trim(postParam('titulo'));
    $contraparte = trim(postParam('contraparte'));
    $tipo        = postParam('tipo', 'contrato');
    $estatus     = postParam('estatus', 'activo');
    $fi          = postParam('fecha_inicio') ?: null;
    $ff          = postParam('fecha_fin')    ?: null;
    $monto       = postParam('monto') ? (float) postParam('monto') : null;
    $notas       = trim(postParam('notas'));

    if (!$id || !$titulo) { header("Location: {$back}"); exit; }

    // Replace file if uploaded
    $archivePath = null;
    if (!empty($_FILES['archivo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf','doc','docx'])) {
            $uuid = bin2hex(random_bytes(16)) . '.' . $ext;
            $dir  = __DIR__ . '/../../../public/uploads/ja_contratos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $dir . $uuid)) {
                $archivePath = 'ja_contratos/' . $uuid;
            }
        }
    }

    if ($archivePath) {
        $pdo->prepare("UPDATE ja_contratos SET titulo=?,contraparte=?,tipo=?,estatus=?,fecha_inicio=?,fecha_fin=?,monto=?,archivo_path=?,notas=? WHERE id=?")
            ->execute([$titulo, $contraparte, $tipo, $estatus, $fi, $ff, $monto, $archivePath, $notas, $id]);
    } else {
        $pdo->prepare("UPDATE ja_contratos SET titulo=?,contraparte=?,tipo=?,estatus=?,fecha_inicio=?,fecha_fin=?,monto=?,notas=? WHERE id=?")
            ->execute([$titulo, $contraparte, $tipo, $estatus, $fi, $ff, $monto, $notas, $id]);
    }

    header("Location: {$back}?flash=editado"); exit;

} elseif ($accion === 'eliminar') {
    $id = (int) postParam('id');
    if ($id > 0) {
        $row = $pdo->prepare("SELECT archivo_path FROM ja_contratos WHERE id = ?");
        $row->execute([$id]);
        $fila = $row->fetch();
        if ($fila && !empty($fila['archivo_path'])) {
            @unlink(__DIR__ . '/../../../public/uploads/' . $fila['archivo_path']);
        }
        $pdo->prepare("DELETE FROM ja_contratos WHERE id = ?")->execute([$id]);
    }
    header("Location: {$back}?flash=eliminado"); exit;
}

header("Location: {$back}"); exit;
