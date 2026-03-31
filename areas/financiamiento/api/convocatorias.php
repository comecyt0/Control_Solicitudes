<?php
/** API Convocatorias — Financiamiento */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/financiamiento/convocatorias.php';
$accion = getParam('accion') ?: postParam('accion');

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $rows = $pdo->query("SELECT * FROM fin_convocatorias ORDER BY fecha_cierre ASC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="convocatorias_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','Título','Dependencia','Tipo','Monto Máx.','Inicio','Cierre','Estatus']);
    foreach ($rows as $r) fputcsv($out, [$r['id'],$r['titulo'],$r['dependencia'],$r['tipo'],$r['monto_max'],$r['fecha_inicio'],$r['fecha_cierre'],$r['estatus']]);
    fclose($out); exit;
}

validarCsrfPost();
$adminId = (int)($_SESSION['admin_id'] ?? 0);
if ($accion === 'crear') {
    $pdo->prepare("INSERT INTO fin_convocatorias (titulo,dependencia,tipo,monto_max,fecha_inicio,fecha_cierre,estatus,descripcion,url_info,creado_por) VALUES(?,?,?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('titulo')),trim(postParam('dependencia')),postParam('tipo','fondo'),postParam('monto_max')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_cierre')?:(null),postParam('estatus','activa'),trim(postParam('descripcion')),trim(postParam('url_info')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');
    if($id>0){
        $pdo->prepare("UPDATE fin_convocatorias SET titulo=?,dependencia=?,tipo=?,monto_max=?,fecha_inicio=?,fecha_cierre=?,estatus=?,descripcion=?,url_info=? WHERE id=?")
            ->execute([trim(postParam('titulo')),trim(postParam('dependencia')),postParam('tipo'),postParam('monto_max')?:(null),postParam('fecha_inicio')?:(null),postParam('fecha_cierre')?:(null),postParam('estatus'),trim(postParam('descripcion')),trim(postParam('url_info')),$id]);
    }
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id'); if($id>0) $pdo->prepare("DELETE FROM fin_convocatorias WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
