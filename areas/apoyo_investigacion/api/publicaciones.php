<?php
/** API Publicaciones — Investigación */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/apoyo_investigacion/publicaciones.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT pub.*,proy.nombre AS proyecto FROM inv_publicaciones pub LEFT JOIN inv_proyectos proy ON pub.proyecto_id=proy.id ORDER BY pub.anio DESC,pub.titulo")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="publicaciones_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Título','Autores','Tipo','Editorial','Año','DOI/ISBN','URL','Proyecto']);
    foreach($rows as $r) fputcsv($out,[$r['id'],$r['titulo'],$r['autores'],$r['tipo'],$r['editorial'],$r['anio'],$r['doi_isbn'],$r['url_acceso'],$r['proyecto']]);
    fclose($out);exit;
}

validarCsrfPost();
if ($accion === 'crear') {
    $proyId=!empty($_POST['proyecto_id'])?(int)$_POST['proyecto_id']:null;
    $pdo->prepare("INSERT INTO inv_publicaciones (titulo,autores,tipo,editorial,anio,doi_isbn,url_acceso,proyecto_id,creado_por) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('titulo')),trim(postParam('autores')),postParam('tipo','articulo'),trim(postParam('editorial')),postParam('anio')?:(null),trim(postParam('doi_isbn')),trim(postParam('url_acceso')),$proyId,$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');$proyId=!empty($_POST['proyecto_id'])?(int)$_POST['proyecto_id']:null;
    if($id>0) $pdo->prepare("UPDATE inv_publicaciones SET titulo=?,autores=?,tipo=?,editorial=?,anio=?,doi_isbn=?,url_acceso=?,proyecto_id=? WHERE id=?")
        ->execute([trim(postParam('titulo')),trim(postParam('autores')),postParam('tipo'),trim(postParam('editorial')),postParam('anio')?:(null),trim(postParam('doi_isbn')),trim(postParam('url_acceso')),$proyId,$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM inv_publicaciones WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
