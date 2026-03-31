<?php
/** API Proyectos — DT */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/desarrollo_tecnologico/proyectos.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM dt_proyectos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="proyectos_dt_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Nombre','Tipo','Líder','Tecnologías','Inicio','Fin','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['id'],$r['nombre'],$r['tipo'],$r['lider'],$r['tecnologias'],$r['fecha_inicio'],$r['fecha_fin'],$r['estatus']]);
    fclose($out);exit;
}
validarCsrfPost();
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO dt_proyectos (nombre,tipo,lider,tecnologias,fecha_inicio,fecha_fin,estatus,descripcion,creado_por) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('nombre')),postParam('tipo','plataforma'),trim(postParam('lider')),trim(postParam('tecnologias')),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus','activo'),trim(postParam('descripcion')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE dt_proyectos SET nombre=?,tipo=?,lider=?,tecnologias=?,fecha_inicio=?,fecha_fin=?,estatus=?,descripcion=? WHERE id=?")
        ->execute([trim(postParam('nombre')),postParam('tipo'),trim(postParam('lider')),trim(postParam('tecnologias')),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus'),trim(postParam('descripcion')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM dt_proyectos WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
