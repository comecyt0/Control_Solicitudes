<?php
/** API Acuerdos — AJ */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/juridico/acuerdos.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM aj_acuerdos ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="acuerdos_aj_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Folio','Tipo','Título','Área Responsable','Fecha','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['folio'],$r['tipo'],$r['titulo'],$r['area_resp'],$r['fecha'],$r['estatus']]);
    fclose($out);exit;
}
function generarFolioAR($pdo){
    $anio=date('Y');
    $ultimo=$pdo->query("SELECT folio FROM aj_acuerdos WHERE folio LIKE 'AR-{$anio}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $siguiente=$ultimo?((int)substr($ultimo,-4)+1):1;
    return 'AR-'.$anio.'-'.str_pad($siguiente,4,'0',STR_PAD_LEFT);
}
validarCsrfPost();
if ($accion === 'crear') {
    $folio=generarFolioAR($pdo);
    $pdo->prepare("INSERT INTO aj_acuerdos (folio,tipo,titulo,descripcion,area_resp,fecha,estatus,observaciones,creado_por) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([$folio,postParam('tipo','acuerdo'),trim(postParam('titulo')),trim(postParam('descripcion')),trim(postParam('area_resp')),postParam('fecha')?:(null),postParam('estatus','vigente'),trim(postParam('observaciones')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE aj_acuerdos SET tipo=?,titulo=?,descripcion=?,area_resp=?,fecha=?,estatus=?,observaciones=? WHERE id=?")
        ->execute([postParam('tipo'),trim(postParam('titulo')),trim(postParam('descripcion')),trim(postParam('area_resp')),postParam('fecha')?:(null),postParam('estatus'),trim(postParam('observaciones')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM aj_acuerdos WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
