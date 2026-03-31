<?php
/** API Convenios — DT */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/desarrollo_tecnologico/convenios.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM dt_convenios ORDER BY fecha_fin ASC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="convenios_dt_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Folio','Institución','Tipo','Responsable','Inicio','Fin','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['folio'],$r['institucion'],$r['tipo'],$r['responsable'],$r['fecha_inicio'],$r['fecha_fin'],$r['estatus']]);
    fclose($out);exit;
}
// Generar folio CV-YYYY-NNNN
function generarFolioCV($pdo){
    $anio=date('Y');
    $ultimo=$pdo->query("SELECT folio FROM dt_convenios WHERE folio LIKE 'CV-{$anio}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $siguiente=$ultimo?((int)substr($ultimo,-4)+1):1;
    return 'CV-'.$anio.'-'.str_pad($siguiente,4,'0',STR_PAD_LEFT);
}
validarCsrfPost();
if ($accion === 'crear') {
    $folio=generarFolioCV($pdo);
    $pdo->prepare("INSERT INTO dt_convenios (folio,institucion,tipo,objeto,fecha_inicio,fecha_fin,estatus,responsable,observaciones,creado_por) VALUES(?,?,?,?,?,?,?,?,?,?)")
        ->execute([$folio,trim(postParam('institucion')),postParam('tipo','colaboracion'),trim(postParam('objeto')),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus','vigente'),trim(postParam('responsable')),trim(postParam('observaciones')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE dt_convenios SET institucion=?,tipo=?,objeto=?,fecha_inicio=?,fecha_fin=?,estatus=?,responsable=?,observaciones=? WHERE id=?")
        ->execute([trim(postParam('institucion')),postParam('tipo'),trim(postParam('objeto')),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus'),trim(postParam('responsable')),trim(postParam('observaciones')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM dt_convenios WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
