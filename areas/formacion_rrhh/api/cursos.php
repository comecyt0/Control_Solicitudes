<?php
/** API Cursos — RRHH */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/formacion_rrhh/cursos.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM rrhh_cursos ORDER BY fecha_inicio ASC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="cursos_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Nombre','Modalidad','Ponente','Horas','Inicio','Fin','Cupo','Inscritos','Estatus']);
    foreach($rows as $r) fputcsv($out,[$r['id'],$r['nombre'],$r['modalidad'],$r['ponente'],$r['horas'],$r['fecha_inicio'],$r['fecha_fin'],$r['cupo'],$r['inscritos'],$r['estatus']]);
    fclose($out);exit;
}
validarCsrfPost();
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO rrhh_cursos (nombre,modalidad,ponente,horas,fecha_inicio,fecha_fin,cupo,estatus,descripcion,creado_por) VALUES(?,?,?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('nombre')),postParam('modalidad','presencial'),trim(postParam('ponente')),postParam('horas')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('cupo')?:(null),postParam('estatus','programado'),trim(postParam('descripcion')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE rrhh_cursos SET nombre=?,modalidad=?,ponente=?,horas=?,fecha_inicio=?,fecha_fin=?,cupo=?,inscritos=?,estatus=?,descripcion=? WHERE id=?")
        ->execute([trim(postParam('nombre')),postParam('modalidad'),trim(postParam('ponente')),postParam('horas')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('cupo')?:(null),postParam('inscritos',0),postParam('estatus'),trim(postParam('descripcion')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM rrhh_cursos WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
