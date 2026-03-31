<?php
/**
 * COMECyT — Reportes (Desarrollo Tecnológico)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$totalProy    = (int)$pdo->query("SELECT COUNT(*) FROM dt_proyectos")->fetchColumn();
$proyActivos  = (int)$pdo->query("SELECT COUNT(*) FROM dt_proyectos WHERE estatus IN('activo','en_desarrollo')")->fetchColumn();
$totalConv    = (int)$pdo->query("SELECT COUNT(*) FROM dt_convenios")->fetchColumn();
$convVigentes = (int)$pdo->query("SELECT COUNT(*) FROM dt_convenios WHERE estatus='vigente'")->fetchColumn();
$totalTrans   = (int)$pdo->query("SELECT COUNT(*) FROM dt_transferencias")->fetchColumn();
$transConcl   = (int)$pdo->query("SELECT COUNT(*) FROM dt_transferencias WHERE estatus='concluido'")->fetchColumn();

$proyPorTipo  = $pdo->query("SELECT tipo,COUNT(*) AS total FROM dt_proyectos GROUP BY tipo ORDER BY total DESC")->fetchAll();
$convPorTipo  = $pdo->query("SELECT tipo,COUNT(*) AS total FROM dt_convenios GROUP BY tipo ORDER BY total DESC")->fetchAll();
$transPorTipo = $pdo->query("SELECT tipo,COUNT(*) AS total FROM dt_transferencias GROUP BY tipo ORDER BY total DESC")->fetchAll();

$pageTitle='Reportes'; $activeMenu='reportes';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--dt-primary:#6d28d9;--dt-accent:#B19A6D;}
.rpt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;}
.rpt-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px 20px;box-shadow:0 4px 8px rgba(0,0,0,.04);}
.rpt-card-label{font-size:.8rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.rpt-card-val{font-size:2rem;font-weight:800;color:var(--dt-primary);}
.export-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;}
.export-btn{display:flex;align-items:center;gap:8px;padding:12px 20px;border-radius:12px;border:1.5px solid;font-size:.88rem;font-weight:600;text-decoration:none;transition:all .2s;}
.export-btn:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.table-section{background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04);margin-bottom:24px;}
.table-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;color:var(--dt-primary);font-size:1rem;display:flex;align-items:center;gap:8px;}
.rpt-table{width:100%;border-collapse:collapse;}
.rpt-table th{text-align:left;padding:10px 16px;font-size:.78rem;text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:1.5px solid #e2e8f0;}
.rpt-table td{padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:.87rem;}
.rpt-table tr:last-child td{border-bottom:none;}
</style>

<div style="margin-bottom:28px;">
    <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-chart-bar" style="color:#6d28d9;"></i> Reportes y Estadísticas</h2>
    <p style="color:#64748b;margin:0;">Resumen general del Departamento de Desarrollo Tecnológico y Vinculación.</p>
</div>

<div class="rpt-grid">
    <div class="rpt-card"><div class="rpt-card-label">Proyectos Totales</div><div class="rpt-card-val"><?=$totalProy?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Proyectos Activos</div><div class="rpt-card-val" style="color:#0284c7;"><?=$proyActivos?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Convenios Vigentes</div><div class="rpt-card-val" style="color:#16a34a;"><?=$convVigentes?>/<?=$totalConv?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Transferencias</div><div class="rpt-card-val"><?=$transConcl?>/<?=$totalTrans?></div></div>
</div>

<h3 style="font-size:1.1rem;font-weight:700;color:var(--dt-primary);margin-bottom:14px;"><i class="fa-solid fa-download" style="color:var(--dt-accent);"></i> Exportar Datos</h3>
<div class="export-row">
    <a href="api/proyectos.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#ede9fe;color:#6d28d9;border-color:#c4b5fd;"><i class="fa-solid fa-diagram-project"></i> Proyectos (CSV)</a>
    <a href="api/convenios.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#dcfce7;color:#16a34a;border-color:#86efac;"><i class="fa-solid fa-handshake"></i> Convenios (CSV)</a>
    <a href="api/transferencias.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#e0f2fe;color:#0284c7;border-color:#7dd3fc;"><i class="fa-solid fa-flask"></i> Transferencias (CSV)</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
    <?php if(!empty($proyPorTipo)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-chart-bar" style="color:var(--dt-accent);"></i> Proyectos por Tipo</div>
    <table class="rpt-table"><thead><tr><th>Tipo</th><th>Total</th></tr></thead><tbody>
    <?php foreach($proyPorTipo as $r): ?><tr><td><?=ucfirst(esc($r['tipo']))?></td><td><span style="background:#ede9fe;color:#6d28d9;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>

    <?php if(!empty($convPorTipo)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-handshake" style="color:var(--dt-accent);"></i> Convenios por Tipo</div>
    <table class="rpt-table"><thead><tr><th>Tipo</th><th>Total</th></tr></thead><tbody>
    <?php foreach($convPorTipo as $r): ?><tr><td><?=ucfirst(esc($r['tipo']))?></td><td><span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>

    <?php if(!empty($transPorTipo)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-flask" style="color:var(--dt-accent);"></i> Transferencias por Tipo</div>
    <table class="rpt-table"><thead><tr><th>Tipo</th><th>Total</th></tr></thead><tbody>
    <?php foreach($transPorTipo as $r): ?><tr><td><?=ucfirst(esc($r['tipo']))?></td><td><span style="background:#e0f2fe;color:#0284c7;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
