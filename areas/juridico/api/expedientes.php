<?php
/** API Expedientes — AJ */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/juridico/expedientes.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM aj_expedientes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="expedientes_aj_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Folio','Tipo','Asunto','Partes','Tribunal','Inicio','Cierre','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['folio'],$r['tipo'],$r['asunto'],$r['partes'],$r['tribunal'],$r['fecha_inicio'],$r['fecha_cierre'],$r['estatus']]);
    fclose($out);exit;
}
function generarFolioEJ($pdo){
    $anio=date('Y');
    $ultimo=$pdo->query("SELECT folio FROM aj_expedientes WHERE folio LIKE 'EJ-{$anio}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $siguiente=$ultimo?((int)substr($ultimo,-4)+1):1;
    return 'EJ-'.$anio.'-'.str_pad($siguiente,4,'0',STR_PAD_LEFT);
}
validarCsrfPost();
if ($accion === 'crear') {
    $folio=generarFolioEJ($pdo);
    $pdo->prepare("INSERT INTO aj_expedientes (folio,tipo,partes,tribunal,asunto,fecha_inicio,fecha_cierre,estatus,observaciones,creado_por) VALUES(?,?,?,?,?,?,?,?,?,?)")
        ->execute([$folio,postParam('tipo','administrativo'),trim(postParam('partes')),trim(postParam('tribunal')),trim(postParam('asunto')),postParam('fecha_inicio')?:(null),postParam('fecha_cierre')?:(null),postParam('estatus','activo'),trim(postParam('observaciones')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE aj_expedientes SET tipo=?,partes=?,tribunal=?,asunto=?,fecha_inicio=?,fecha_cierre=?,estatus=?,observaciones=? WHERE id=?")
        ->execute([postParam('tipo'),trim(postParam('partes')),trim(postParam('tribunal')),trim(postParam('asunto')),postParam('fecha_inicio')?:(null),postParam('fecha_cierre')?:(null),postParam('estatus'),trim(postParam('observaciones')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM aj_expedientes WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
