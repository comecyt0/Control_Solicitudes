<?php
/** API Dictámenes — AJ */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/juridico/dictamenes.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM aj_dictamenes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="dictamenes_aj_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Folio','Título','Materia','Área Solicitante','Fecha Solicitud','Fecha Respuesta','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['folio'],$r['titulo'],$r['materia'],$r['area_solicitante'],$r['fecha_solicitud'],$r['fecha_respuesta'],$r['estatus']]);
    fclose($out);exit;
}
function generarFolioDJ($pdo){
    $anio=date('Y');
    $ultimo=$pdo->query("SELECT folio FROM aj_dictamenes WHERE folio LIKE 'DJ-{$anio}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $siguiente=$ultimo?((int)substr($ultimo,-4)+1):1;
    return 'DJ-'.$anio.'-'.str_pad($siguiente,4,'0',STR_PAD_LEFT);
}
validarCsrfPost();
if ($accion === 'crear') {
    $folio=generarFolioDJ($pdo);
    $pdo->prepare("INSERT INTO aj_dictamenes (folio,area_solicitante,materia,titulo,descripcion,fecha_solicitud,fecha_respuesta,estatus,creado_por) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([$folio,trim(postParam('area_solicitante')),postParam('materia','administrativo'),trim(postParam('titulo')),trim(postParam('descripcion')),postParam('fecha_solicitud')?:(null),postParam('fecha_respuesta')?:(null),postParam('estatus','pendiente'),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE aj_dictamenes SET area_solicitante=?,materia=?,titulo=?,descripcion=?,fecha_solicitud=?,fecha_respuesta=?,estatus=? WHERE id=?")
        ->execute([trim(postParam('area_solicitante')),postParam('materia'),trim(postParam('titulo')),trim(postParam('descripcion')),postParam('fecha_solicitud')?:(null),postParam('fecha_respuesta')?:(null),postParam('estatus'),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM aj_dictamenes WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
