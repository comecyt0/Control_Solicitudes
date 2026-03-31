<?php
/** API Igualdad — RRHH */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/formacion_rrhh/igualdad.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM rrhh_igualdad ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="igualdad_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Folio','Tipo','Descripción','Responsable','Inicio','Fin','Estatus','Observaciones']);
    foreach($rows as $r) fputcsv($out,[$r['folio'],$r['tipo'],$r['descripcion'],$r['responsable'],$r['fecha_inicio'],$r['fecha_fin'],$r['estatus'],$r['observaciones']]);
    fclose($out);exit;
}
validarCsrfPost();
// Generar folio automático IG-YYYY-NNNN
function generarFolioIg($pdo){
    $anio=date('Y');
    $ultimo=$pdo->query("SELECT folio FROM rrhh_igualdad WHERE folio LIKE 'IG-{$anio}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $siguiente=$ultimo?((int)substr($ultimo,-4)+1):1;
    return 'IG-'.$anio.'-'.str_pad($siguiente,4,'0',STR_PAD_LEFT);
}
if ($accion === 'crear') {
    $folio=generarFolioIg($pdo);
    $pdo->prepare("INSERT INTO rrhh_igualdad (folio,tipo,descripcion,responsable,fecha_inicio,fecha_fin,estatus,observaciones,creado_por) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([$folio,trim(postParam('tipo')),trim(postParam('descripcion')),trim(postParam('responsable')),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus','pendiente'),trim(postParam('observaciones')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE rrhh_igualdad SET tipo=?,descripcion=?,responsable=?,fecha_inicio=?,fecha_fin=?,estatus=?,observaciones=? WHERE id=?")
        ->execute([trim(postParam('tipo')),trim(postParam('descripcion')),trim(postParam('responsable')),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus'),trim(postParam('observaciones')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM rrhh_igualdad WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
