<?php
/** API Becas — Financiamiento */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $back = BASE_URL . 'areas/financiamiento/becas.php';
$accion = getParam('accion') ?: postParam('accion');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($accion === 'exportar_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = getParam('csrf_token');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) die('Token inválido');
    $estatus = getParam('estatus',''); $ciclo = getParam('ciclo','');
    $where='WHERE 1=1';$params=[];
    if($estatus){$where.=' AND b.estatus_pago=?';$params[]=$estatus;}
    if($ciclo){$where.=' AND b.ciclo=?';$params[]=$ciclo;}
    $stmt=$pdo->prepare("SELECT b.*,c.titulo AS conv_titulo FROM fin_becas b LEFT JOIN fin_convocatorias c ON b.convocatoria_id=c.id $where ORDER BY b.nombre");
    $stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="becas_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Beneficiario','Tipo','Monto','Ciclo','Estatus Pago','Convocatoria','Inicio','Fin']);
    foreach($rows as $r) fputcsv($out,[$r['id'],$r['nombre'],$r['tipo_apoyo'],$r['monto'],$r['ciclo'],$r['estatus_pago'],$r['conv_titulo'],$r['fecha_inicio'],$r['fecha_fin']]);
    fclose($out);exit;
}

validarCsrfPost();
if ($accion === 'crear') {
    $convId=!empty($_POST['convocatoria_id'])?(int)$_POST['convocatoria_id']:null;
    $pdo->prepare("INSERT INTO fin_becas (nombre,tipo_apoyo,monto,ciclo,estatus_pago,convocatoria_id,fecha_inicio,fecha_fin,notas,creado_por) VALUES(?,?,?,?,?,?,?,?,?,?)")
        ->execute([trim(postParam('nombre')),postParam('tipo_apoyo','otro'),postParam('monto')?:(null),trim(postParam('ciclo')),postParam('estatus_pago','pendiente'),$convId,postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),trim(postParam('notas')),$adminId]);
    header("Location: {$back}?flash=creado"); exit;
} elseif ($accion === 'editar') {
    $id=(int)postParam('id');$convId=!empty($_POST['convocatoria_id'])?(int)$_POST['convocatoria_id']:null;
    if($id>0){
        $pdo->prepare("UPDATE fin_becas SET nombre=?,tipo_apoyo=?,monto=?,ciclo=?,estatus_pago=?,convocatoria_id=?,fecha_inicio=?,fecha_fin=?,notas=? WHERE id=?")
            ->execute([trim(postParam('nombre')),postParam('tipo_apoyo'),postParam('monto')?:(null),trim(postParam('ciclo')),postParam('estatus_pago'),$convId,postParam('fecha_inicio')?:(null),postParam('fecha_fin')?:(null),trim(postParam('notas')),$id]);
    }
    header("Location: {$back}?flash=editado"); exit;
} elseif ($accion === 'eliminar') {
    $id=(int)postParam('id');if($id>0)$pdo->prepare("DELETE FROM fin_becas WHERE id=?")->execute([$id]);
    header("Location: {$back}?flash=eliminado"); exit;
}
header("Location: {$back}"); exit;
