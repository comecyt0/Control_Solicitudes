<?php
/**
 * API Casos de Igualdad — Jurídico Administrativo
 */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

verificarSesionAdmin();
validarCsrfPost();

$pdo     = getConnection();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$accion  = postParam('accion');
$back    = BASE_URL . 'areas/juridico_igualdad/igualdad.php';

if ($accion === 'crear') {
    $tipo           = postParam('tipo', 'otro');
    $fechaRecepcion = postParam('fecha_recepcion', date('Y-m-d'));
    $notas          = trim(postParam('notas'));

    // Generar folio único EQ-YYYY-NNNN
    $anio = date('Y');
    $count = (int) $pdo->query("SELECT COUNT(*) FROM ja_casos_igualdad WHERE EXTRACT(YEAR FROM created_at) = $anio")->fetchColumn();
    $folio = 'EQ-' . $anio . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO ja_casos_igualdad (folio, tipo, fecha_recepcion, notas, creado_por) VALUES (?,?,?,?,?)")
        ->execute([$folio, $tipo, $fechaRecepcion, $notas, $adminId]);

    header("Location: {$back}?flash=creado"); exit;

} elseif ($accion === 'editar') {
    $id          = (int) postParam('id');
    $tipo        = postParam('tipo', 'otro');
    $estatus     = postParam('estatus', 'recibido');
    $fechaCierre = postParam('fecha_cierre') ?: null;
    $notas       = trim(postParam('notas'));

    if ($id > 0) {
        $pdo->prepare("UPDATE ja_casos_igualdad SET tipo=?, estatus=?, fecha_cierre=?, notas=? WHERE id=?")
            ->execute([$tipo, $estatus, $fechaCierre, $notas, $id]);
    }
    header("Location: {$back}?flash=editado"); exit;

} elseif ($accion === 'eliminar') {
    $id = (int) postParam('id');
    if ($id > 0) {
        $pdo->prepare("DELETE FROM ja_casos_igualdad WHERE id=?")->execute([$id]);
    }
    header("Location: {$back}?flash=eliminado"); exit;
}

header("Location: {$back}"); exit;
