<?php
/** API Investigadores — Investigación */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/apoyo_investigacion/investigadores.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM inv_investigadores ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="investigadores_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Nombre','CVU','Nivel SNI','Institución','Especialidad','Correo','Activo']);
    foreach($rows as $r) fputcsv($out,[$r['id'],$r['nombre'],$r['cvu'],$r['nivel_sni'],$r['institucion'],$r['especialidad'],$r['correo'],$r['activo']?'Sí':'No']);
    fclose($out);exit;
}

validarCsrfPost();
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO inv_investigadores (nombre,cvu,nivel_sni,institucion,especialidad,correo,activo,creado_por) VALUES(?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('nombre')),trim(postParam('cvu')),postParam('nivel_sni')?:(null),trim(postParam('institucion')),trim(postParam('especialidad')),trim(postParam('correo')),(int)postParam('activo',1)===1,$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE inv_investigadores SET nombre=?,cvu=?,nivel_sni=?,institucion=?,especialidad=?,correo=?,activo=? WHERE id=?")
        ->execute([trim(postParam('nombre')),trim(postParam('cvu')),postParam('nivel_sni')?:(null),trim(postParam('institucion')),trim(postParam('especialidad')),trim(postParam('correo')),(int)postParam('activo',1)===1,$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM inv_investigadores WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
