<?php
/** API Transferencias — DT */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/desarrollo_tecnologico/transferencias.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM dt_transferencias ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="transferencias_dt_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Nombre','Tipo','Responsable','Institución','Fecha','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['id'],$r['nombre'],$r['tipo'],$r['responsable'],$r['institucion'],$r['fecha'],$r['estatus']]);
    fclose($out);exit;
}
validarCsrfPost();
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO dt_transferencias (nombre,tipo,responsable,institucion,fecha,estatus,descripcion,creado_por) VALUES(?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('nombre')),postParam('tipo','prototipo'),trim(postParam('responsable')),trim(postParam('institucion')),postParam('fecha')?:(null),postParam('estatus','en_proceso'),trim(postParam('descripcion')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE dt_transferencias SET nombre=?,tipo=?,responsable=?,institucion=?,fecha=?,estatus=?,descripcion=? WHERE id=?")
        ->execute([trim(postParam('nombre')),postParam('tipo'),trim(postParam('responsable')),trim(postParam('institucion')),postParam('fecha')?:(null),postParam('estatus'),trim(postParam('descripcion')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM dt_transferencias WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
