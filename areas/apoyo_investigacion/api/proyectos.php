<?php
/** API Proyectos — Investigación */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/apoyo_investigacion/proyectos.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);
validarCsrfPost();
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO inv_proyectos (nombre,lider,fondo,monto,fecha_inicio,fecha_fin,estatus,descripcion,creado_por) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('nombre')),trim(postParam('lider')),trim(postParam('fondo')),postParam('monto')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus','activo'),trim(postParam('descripcion')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0) $pdo->prepare("UPDATE inv_proyectos SET nombre=?,lider=?,fondo=?,monto=?,fecha_inicio=?,fecha_fin=?,estatus=?,descripcion=? WHERE id=?")
        ->execute([trim(postParam('nombre')),trim(postParam('lider')),trim(postParam('fondo')),postParam('monto')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),postParam('estatus'),trim(postParam('descripcion')),$id]);
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM inv_proyectos WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
