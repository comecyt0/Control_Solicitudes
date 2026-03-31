<?php
/**
 * API Adquisiciones — Jurídico Administrativo
 */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

verificarSesionAdmin();

$pdo     = getConnection();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$accion  = getParam('accion') ?: postParam('accion');
$back    = BASE_URL . 'areas/juridico_igualdad/adquisiciones.php';

// Exportar CSV (GET con CSRF via query param)
if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die('Token inválido');
    }
    $estatus = getParam('estatus', '');
    $where   = $estatus ? 'WHERE estatus = ?' : '';
    $params  = $estatus ? [$estatus] : [];
    $stmt    = $pdo->prepare("SELECT * FROM ja_adquisiciones $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="adquisiciones_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['ID','Concepto','Proveedor','Monto','Área Solicitante','Estatus','Fecha Solicitud','Fecha Entrega']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['concepto'], $r['proveedor'], $r['monto'],
            $r['area_solicitante'], $r['estatus'], $r['fecha_solicitud'], $r['fecha_entrega'],
        ]);
    }
    fclose($out);
    exit;
}

// Acciones POST
validarCsrfPost();

if ($accion === 'crear') {
    $concepto        = trim(postParam('concepto'));
    $proveedor       = trim(postParam('proveedor'));
    $monto           = postParam('monto') ? (float) postParam('monto') : null;
    $areaSolicitante = trim(postParam('area_solicitante'));
    $estatus         = postParam('estatus', 'en_proceso');
    $fs              = postParam('fecha_solicitud') ?: date('Y-m-d');
    $fe              = postParam('fecha_entrega') ?: null;
    $notas           = trim(postParam('notas'));

    if ($concepto) {
        $pdo->prepare("INSERT INTO ja_adquisiciones (concepto, proveedor, monto, area_solicitante, estatus, fecha_solicitud, fecha_entrega, notas, creado_por) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$concepto, $proveedor, $monto, $areaSolicitante, $estatus, $fs, $fe, $notas, $adminId]);
    }
    header("Location: {$back}?flash=creado"); exit;

} elseif ($accion === 'editar') {
    $id              = (int) postParam('id');
    $concepto        = trim(postParam('concepto'));
    $proveedor       = trim(postParam('proveedor'));
    $monto           = postParam('monto') ? (float) postParam('monto') : null;
    $areaSolicitante = trim(postParam('area_solicitante'));
    $estatus         = postParam('estatus', 'en_proceso');
    $fs              = postParam('fecha_solicitud') ?: null;
    $fe              = postParam('fecha_entrega') ?: null;
    $notas           = trim(postParam('notas'));

    if ($id > 0 && $concepto) {
        $pdo->prepare("UPDATE ja_adquisiciones SET concepto=?,proveedor=?,monto=?,area_solicitante=?,estatus=?,fecha_solicitud=?,fecha_entrega=?,notas=? WHERE id=?")
            ->execute([$concepto, $proveedor, $monto, $areaSolicitante, $estatus, $fs, $fe, $notas, $id]);
    }
    header("Location: {$back}?flash=editado"); exit;

} elseif ($accion === 'eliminar') {
    $id = (int) postParam('id');
    if ($id > 0) {
        $pdo->prepare("DELETE FROM ja_adquisiciones WHERE id=?")->execute([$id]);
    }
    header("Location: {$back}?flash=eliminado"); exit;
}

header("Location: {$back}"); exit;
