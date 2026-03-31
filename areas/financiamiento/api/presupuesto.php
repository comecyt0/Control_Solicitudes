<?php
/** API Presupuesto — Financiamiento */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/financiamiento/presupuesto.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $anio = (int)getParam('anio', date('Y'));
    $rows = $pdo->prepare("SELECT * FROM fin_presupuesto WHERE anio=? ORDER BY partida"); $rows->execute([$anio]); $rows=$rows->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="presupuesto_'.$anio.'_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Partida','Descripción','Año','Asignado','Ejercido','Disponible','% Ejercido']);
    foreach($rows as $r){$disp=$r['monto_asignado']-$r['monto_ejercido'];$pct=$r['monto_asignado']>0?round($r['monto_ejercido']/$r['monto_asignado']*100):0;fputcsv($out,[$r['id'],$r['partida'],$r['descripcion'],$r['anio'],$r['monto_asignado'],$r['monto_ejercido'],$disp,$pct.'%']);}
    fclose($out);exit;
}

validarCsrfPost();
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO fin_presupuesto (partida,descripcion,anio,monto_asignado,monto_ejercido,creado_por) VALUES(?,?,?,?,?,?)")
        ->execute([trim(postParam('partida')),trim(postParam('descripcion')),(int)postParam('anio',date('Y')),$_POST['monto_asignado']??0,$_POST['monto_ejercido']??0,$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0){
        $pdo->prepare("UPDATE fin_presupuesto SET partida=?,descripcion=?,anio=?,monto_asignado=?,monto_ejercido=? WHERE id=?")
            ->execute([trim(postParam('partida')),trim(postParam('descripcion')),(int)postParam('anio'),$_POST['monto_asignado']??0,$_POST['monto_ejercido']??0,$id]);
    }
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM fin_presupuesto WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
