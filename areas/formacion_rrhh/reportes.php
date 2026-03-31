<?php
/**
 * COMECyT — Reportes (RRHH)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$totalCursos   = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_cursos")->fetchColumn();
$cursosActivos = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_cursos WHERE estatus IN('programado','en_curso')")->fetchColumn();
$totalHoras    = $pdo->query("SELECT COALESCE(SUM(horas),0) FROM rrhh_cursos")->fetchColumn();
$totalBecas    = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_becas")->fetchColumn();
$becasActivas  = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_becas WHERE estatus='activa'")->fetchColumn();
$totalMonto    = $pdo->query("SELECT COALESCE(SUM(monto),0) FROM rrhh_becas WHERE estatus='activa'")->fetchColumn();
$totalIg       = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_igualdad")->fetchColumn();
$igConcluid    = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_igualdad WHERE estatus='concluida'")->fetchColumn();

$cursosPorMod  = $pdo->query("SELECT modalidad,COUNT(*) AS total FROM rrhh_cursos GROUP BY modalidad ORDER BY total DESC")->fetchAll();
$becasPorTipo  = $pdo->query("SELECT tipo,COUNT(*) AS total,COALESCE(SUM(monto),0) AS monto FROM rrhh_becas GROUP BY tipo ORDER BY total DESC")->fetchAll();
$igPorEst      = $pdo->query("SELECT estatus,COUNT(*) AS total FROM rrhh_igualdad GROUP BY estatus ORDER BY total DESC")->fetchAll();

$pageTitle='Reportes'; $activeMenu='reportes';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--rrhh-primary:#0f766e;--rrhh-accent:#B19A6D;}
.rpt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;}
.rpt-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px 20px;box-shadow:0 4px 8px rgba(0,0,0,.04);}
.rpt-card-label{font-size:.8rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.rpt-card-val{font-size:2rem;font-weight:800;color:var(--rrhh-primary);}
.export-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;}
.export-btn{display:flex;align-items:center;gap:8px;padding:12px 20px;border-radius:12px;border:1.5px solid;font-size:.88rem;font-weight:600;text-decoration:none;transition:all .2s;}
.export-btn:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.table-section{background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04);margin-bottom:24px;}
.table-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;color:var(--rrhh-primary);font-size:1rem;display:flex;align-items:center;gap:8px;}
.rpt-table{width:100%;border-collapse:collapse;}
.rpt-table th{text-align:left;padding:10px 16px;font-size:.78rem;text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:1.5px solid #e2e8f0;}
.rpt-table td{padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:.87rem;}
.rpt-table tr:last-child td{border-bottom:none;}
</style>

<div style="margin-bottom:28px;">
    <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-chart-bar" style="color:#0f766e;"></i> Reportes y Estadísticas</h2>
    <p style="color:#64748b;margin:0;">Resumen general del área de Formación de Recursos Humanos.</p>
</div>

<div class="rpt-grid">
    <div class="rpt-card"><div class="rpt-card-label">Cursos Totales</div><div class="rpt-card-val"><?=$totalCursos?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Cursos Activos</div><div class="rpt-card-val" style="color:#0284c7;"><?=$cursosActivos?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Horas Acumuladas</div><div class="rpt-card-val"><?=$totalHoras?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Becas Vigentes</div><div class="rpt-card-val" style="color:#16a34a;"><?=$becasActivas?>/<?=$totalBecas?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Monto Becas Activas</div><div class="rpt-card-val">$<?=number_format($totalMonto,0)?></div></div>
    <div class="rpt-card"><div class="rpt-card-label">Acciones Igualdad</div><div class="rpt-card-val"><?=$igConcluid?>/<?=$totalIg?></div></div>
</div>

<h3 style="font-size:1.1rem;font-weight:700;color:var(--rrhh-primary);margin-bottom:14px;"><i class="fa-solid fa-download" style="color:var(--rrhh-accent);"></i> Exportar Datos</h3>
<div class="export-row">
    <a href="api/cursos.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#ccfbf1;color:#0f766e;border-color:#5eead4;"><i class="fa-solid fa-chalkboard-user"></i> Cursos (CSV)</a>
    <a href="api/becas.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#fef3c7;color:#d97706;border-color:#fde68a;"><i class="fa-solid fa-award"></i> Becas (CSV)</a>
    <a href="api/igualdad.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="export-btn" style="background:#ede9fe;color:#6d28d9;border-color:#c4b5fd;"><i class="fa-solid fa-scale-balanced"></i> Igualdad (CSV)</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
    <?php if(!empty($cursosPorMod)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-chart-bar" style="color:var(--rrhh-accent);"></i> Cursos por Modalidad</div>
    <table class="rpt-table"><thead><tr><th>Modalidad</th><th>Total</th></tr></thead><tbody>
    <?php foreach($cursosPorMod as $r): ?><tr><td><?=ucfirst(esc($r['modalidad']))?></td><td><span style="background:#ccfbf1;color:#0f766e;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    
    <?php if(!empty($becasPorTipo)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-chart-bar" style="color:var(--rrhh-accent);"></i> Becas por Tipo</div>
    <table class="rpt-table"><thead><tr><th>Tipo</th><th>Total</th><th>Monto</th></tr></thead><tbody>
    <?php foreach($becasPorTipo as $r): ?><tr><td><?=ucfirst(esc($r['tipo']))?></td><td><span style="background:#fef3c7;color:#d97706;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td><td>$<?=number_format($r['monto'],0)?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    
    <?php if(!empty($igPorEst)): ?>
    <div class="table-section"><div class="table-header"><i class="fa-solid fa-scale-balanced" style="color:var(--rrhh-accent);"></i> Acciones de Igualdad por Estatus</div>
    <table class="rpt-table"><thead><tr><th>Estatus</th><th>Total</th></tr></thead><tbody>
    <?php foreach($igPorEst as $r): ?><tr><td><?=ucfirst(str_replace('_',' ',esc($r['estatus'])))?></td><td><span style="background:#ede9fe;color:#6d28d9;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:700;"><?=$r['total']?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
