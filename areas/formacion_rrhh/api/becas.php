<?php
/** API Becas — RRHH */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/formacion_rrhh/becas.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM rrhh_becas ORDER BY beneficiario")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="becas_rrhh_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Beneficiario','Tipo','Institución','Monto','Inicio','Fin','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['id'],$r['beneficiario'],$r['tipo'],$r['institucion'],$r['monto'],$r['fecha_inicio'],$r['fecha_fin'],$r['estatus']]);
    fclose($out);exit;
}
validarCsrfPost();
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO rrhh_becas (beneficiario,tipo,institucion,monto,fecha_inicio,fecha_fin,estatus,notas,creado_por) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('beneficiario')),postParam('tipo','posgrado'),trim(postParam('institucion')),postParam('monto')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus','activa'),trim(postParam('notas')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE rrhh_becas SET beneficiario=?,tipo=?,institucion=?,monto=?,fecha_inicio=?,fecha_fin=?,estatus=?,notas=? WHERE id=?")
        ->execute([trim(postParam('beneficiario')),postParam('tipo'),trim(postParam('institucion')),postParam('monto')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus'),trim(postParam('notas')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM rrhh_becas WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
