<?php
/**
 * COMECyT — Reportes (Asuntos Jurídicos)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$totalExped   = (int)$pdo->query("SELECT COUNT(*) FROM aj_expedientes")->fetchColumn();
$expedActivos = (int)$pdo->query("SELECT COUNT(*) FROM aj_expedientes WHERE estatus IN('activo','en_proceso')")->fetchColumn();
$totalDict    = (int)$pdo->query("SELECT COUNT(*) FROM aj_dictamenes")->fetchColumn();
$dictEmitidos = (int)$pdo->query("SELECT COUNT(*) FROM aj_dictamenes WHERE estatus='emitido'")->fetchColumn();
$totalAcuerd  = (int)$pdo->query("SELECT COUNT(*) FROM aj_acuerdos")->fetchColumn();
$acuerdVig    = (int)$pdo->query("SELECT COUNT(*) FROM aj_acuerdos WHERE estatus='vigente'")->fetchColumn();

$expedPorTipo = $pdo->query("SELECT tipo,COUNT(*) AS total FROM aj_expedientes GROUP BY tipo ORDER BY total DESC")->fetchAll();
$dictPorMat   = $pdo->query("SELECT materia,COUNT(*) AS total FROM aj_dictamenes GROUP BY materia ORDER BY total DESC")->fetchAll();
$acuerdPorTipo= $pdo->query("SELECT tipo,COUNT(*) AS total FROM aj_acuerdos GROUP BY tipo ORDER BY total DESC")->fetchAll();

$pageTitle='Reportes'; $activeMenu='reportes';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--aj-primary:#991b1b;--aj-accent:#B19A6D;}
.rpt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;}
.rpt-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px 20px;box-shadow:0 4px 8px rgba(0,0,0,.04);}
.rpt-card-label{font-size:.8rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.rpt-card-val{font-size:2rem;font-weight:800;color:var(--aj-primary);}
.export-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;}
.export-btn{display:flex;align-items:center;gap:8px;padding:12px 20px;border-radius:12px;border:1.5px solid;font-size:.88rem;font-weight:600;text-decoration:none;transition:all .2s;}
.export-btn:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.table-section{background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04);margin-bottom:24px;}
.table-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;color:var(--aj-primary);font-size:1rem;display:flex;align-items:center;gap:8px;}
.rpt-table{width:100%;border-collapse:collapse;}
.rpt-table th{text-align:left;padding:10px 16px;font-size:.78rem;text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:1.5px solid #e2e8f0;}
.rpt-table td{padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:.87rem;}
.rpt-table tr:last-child td{border-bottom:none;}
</style>

<div style="margin-bottom:28px;">
    <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-chart-bar" style="color:#991b1b;"></i> Reportes y Estadísticas</h2>
    <p style="color:#64748b;margin:0;">Resumen general del Departamento de Asuntos Jurídicos.</p>
</div>

<div class="rpt-grid">
    <div class="rpt-card"><div class="rpt-card-label">Expedientes Totales</div><div class="rpt-card-val"><?=$totalExped?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Expedientes Activos</div><div class="rpt-card-val" style="color:#0284c7;"><?=$expedActivos?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Dictámenes Emitidos</div><div class="rpt-card-val" style="color:#16a34a;"><?=$dictEmitidos?>/<?=$totalDict?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Acuerdos Vigentes</div><div class="rpt-card-val"><?=$acuerdVig?>/<?=$totalAcuerd?></div></div>
</div>

<h3 style="font-size:1.1rem;font-weight:700;color:var(--aj-primary);margin-bottom:14px;"><i class="fa-solid fa-download" style="color:var(--aj-accent);"></i> Exportar Datos</h3>
<div class="export-row">
    <a href="api/expedientes.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#fff1f2;color:#991b1b;border-color:#fca5a5;"><i class="fa-solid fa-folder-open"></i> Expedientes (CSV)</a>
    <a href="api/dictamenes.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#fef3c7;color:#d97706;border-color:#fde68a;"><i class="fa-solid fa-scroll"></i> Dictámenes (CSV)</a>
    <a href="api/acuerdos.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#dcfce7;color:#16a34a;border-color:#86efac;"><i class="fa-solid fa-file-signature"></i> Acuerdos (CSV)</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
    <?php if(!empty($expedPorTipo)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-folder-open" style="color:var(--aj-accent);"></i> Expedientes por Tipo</div>
    <table class="rpt-table"><thead><tr><th>Tipo</th><th>Total</th></tr></thead><tbody>
    <?php foreach($expedPorTipo as $r): ?><tr><td><?=ucfirst(esc($r['tipo']))?></td><td><span style="background:#fff1f2;color:#991b1b;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>

    <?php if(!empty($dictPorMat)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-scroll" style="color:var(--aj-accent);"></i> Dictámenes por Materia</div>
    <table class="rpt-table"><thead><tr><th>Materia</th><th>Total</th></tr></thead><tbody>
    <?php foreach($dictPorMat as $r): ?><tr><td><?=ucfirst(esc($r['materia']?:'Sin materia'))?></td><td><span style="background:#fef3c7;color:#d97706;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>

    <?php if(!empty($acuerdPorTipo)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-file-signature" style="color:var(--aj-accent);"></i> Acuerdos por Tipo</div>
    <table class="rpt-table"><thead><tr><th>Tipo</th><th>Total</th></tr></thead><tbody>
    <?php foreach($acuerdPorTipo as $r): ?><tr><td><?=ucfirst(esc($r['tipo']))?></td><td><span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
