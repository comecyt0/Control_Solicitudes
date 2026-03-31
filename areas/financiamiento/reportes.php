<?php
/**
 * COMECyT — Reportes (Financiamiento)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$anioActual = date('Y');
// Stats
$totalBecas     = (int)$pdo->query("SELECT COUNT(*) FROM fin_becas")->fetchColumn();
$totalPagadas   = (int)$pdo->query("SELECT COUNT(*) FROM fin_becas WHERE estatus_pago='pagado'")->fetchColumn();
$totalMontoBecas= $pdo->query("SELECT COALESCE(SUM(monto),0) FROM fin_becas")->fetchColumn();
$totalConvOpen  = (int)$pdo->query("SELECT COUNT(*) FROM fin_convocatorias WHERE estatus='activa'")->fetchColumn();
$totalConv      = (int)$pdo->query("SELECT COUNT(*) FROM fin_convocatorias")->fetchColumn();
$totalAsig  = $pdo->query("SELECT COALESCE(SUM(monto_asignado),0) FROM fin_presupuesto WHERE anio=$anioActual")->fetchColumn();
$totalEjerc = $pdo->query("SELECT COALESCE(SUM(monto_ejercido),0) FROM fin_presupuesto WHERE anio=$anioActual")->fetchColumn();
$pctPres = $totalAsig > 0 ? round(($totalEjerc/$totalAsig)*100) : 0;

// Becas por tipo
$becasPorTipo = $pdo->query("SELECT tipo_apoyo, COUNT(*) as total, COALESCE(SUM(monto),0) as monto_total FROM fin_becas GROUP BY tipo_apoyo ORDER BY total DESC")->fetchAll();
// Convocatorias por tipo
$convPorTipo = $pdo->query("SELECT tipo, COUNT(*) as total FROM fin_convocatorias GROUP BY tipo ORDER BY total DESC")->fetchAll();

$pageTitle='Reportes';$activeMenu='reportes';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--fin-primary:#064e3b;--fin-accent:#B19A6D;}
.rpt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;}
.rpt-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px 20px;box-shadow:0 4px 8px rgba(0,0,0,.04);}
.rpt-card-label{font-size:.8rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.rpt-card-val{font-size:2rem;font-weight:800;color:var(--fin-primary);}
.export-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;}
.export-btn{display:flex;align-items:center;gap:8px;padding:12px 20px;border-radius:12px;border:1.5px solid;font-size:.88rem;font-weight:600;text-decoration:none;transition:all .2s;}
.export-btn:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.table-card{background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04);margin-bottom:24px;}
.table-card-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;color:var(--fin-primary);font-size:1rem;display:flex;align-items:center;gap:8px;}
.rpt-table{width:100%;border-collapse:collapse;}
.rpt-table th{text-align:left;padding:10px 16px;font-size:.78rem;text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:1.5px solid #e2e8f0;}
.rpt-table td{padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:.87rem;}
.rpt-table tr:last-child td{border-bottom:none;}
</style>

<div style="margin-bottom:28px;">
    <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-file-chart-column" style="color:#064e3b;"></i> Reportes y Estadísticas</h2>
    <p style="color:#64748b;margin:0;">Resumen general del área de Financiamiento.</p>
</div>

<!-- KPIs Generales -->
<div class="rpt-grid">
    <div class="rpt-card"><div class="rpt-card-label">Total Becas</div><div class="rpt-card-val"><?=$totalBecas?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Becas Pagadas</div><div class="rpt-card-val" style="color:#059669;"><?=$totalPagadas?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Monto Total Becas</div><div class="rpt-card-val">$<?=number_format($totalMontoBecas,0)?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Convocatorias Activas</div><div class="rpt-card-val"><?=$totalConvOpen?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Total Convocatorias</div><div class="rpt-card-val"><?=$totalConv?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Presupuesto <?=$anioActual?> Ejercido</div><div class="rpt-card-val"><?=$pctPres?>%</div></div>
</div>

<!-- Exportar CSV -->
<h3 style="font-size:1.1rem;font-weight:700;color:var(--fin-primary);margin-bottom:14px;"><i class="fa-solid fa-download" style="color:var(--fin-accent);"></i> Exportar Datos</h3>
<div class="export-row">
    <a href="api/becas.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#dcfce7;color:#064e3b;border-color:#86efac;">
        <i class="fa-solid fa-graduation-cap"></i> Becas y Apoyos (CSV)
    </a>
    <a href="api/convocatorias.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;">
        <i class="fa-solid fa-bullhorn"></i> Convocatorias (CSV)
    </a>
    <a href="api/presupuesto.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:rgba(6,78,59,.05);color:var(--fin-primary);border-color:rgba(6,78,59,.2);">
        <i class="fa-solid fa-chart-pie"></i> Presupuesto <?=$anioActual?> (CSV)
    </a>
</div>

<!-- Tabla Becas por Tipo -->
<?php if(!empty($becasPorTipo)): ?>
<div class="table-card">
    <div class="table-card-header"><i class="fa-solid fa-chart-bar" style="color:var(--fin-accent);"></i> Becas por Tipo de Apoyo</div>
    <table class="rpt-table">
        <thead><tr><th>Tipo de Apoyo</th><th>Cantidad</th><th>Monto Total</th></tr></thead>
        <tbody>
        <?php foreach($becasPorTipo as $bt): ?>
            <tr>
                <td style="font-weight:600;"><?=esc(ucwords(str_replace('_',' ',$bt['tipo_apoyo']?:'Sin tipo')))?></td>
                <td><span style="background:#e0f2fe;color:#0284c7;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$bt['total']?></span></td>
                <td>$<?=number_format($bt['monto_total'],0)?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Tabla Convocatorias por Tipo -->
<?php if(!empty($convPorTipo)): ?>
<div class="table-card">
    <div class="table-card-header"><i class="fa-solid fa-chart-bar" style="color:var(--fin-accent);"></i> Convocatorias por Tipo</div>
    <table class="rpt-table">
        <thead><tr><th>Tipo</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach($convPorTipo as $ct): ?>
            <tr>
                <td style="font-weight:600;"><?=esc(ucfirst($ct['tipo']))?></td>
                <td><span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$ct['total']?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
