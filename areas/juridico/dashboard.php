<?php
/**
 * COMECyT — Panel Asuntos Jurídicos
 * Dashboard — cve_area = 19
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $cveArea = 19;

$expedActivos  = (int)$pdo->query("SELECT COUNT(*) FROM aj_expedientes WHERE estatus IN('activo','en_proceso')")->fetchColumn();
$dictPend      = (int)$pdo->query("SELECT COUNT(*) FROM aj_dictamenes WHERE estatus IN('pendiente','en_elaboracion')")->fetchColumn();
$acuerdVig     = (int)$pdo->query("SELECT COUNT(*) FROM aj_acuerdos WHERE estatus='vigente'")->fetchColumn();
$tareasPend    = (int)$pdo->query("SELECT COUNT(*) FROM sb_kanban_tareas WHERE cve_area=$cveArea AND estatus!='completada'")->fetchColumn();
$totalExpedAnio= (int)$pdo->query("SELECT COUNT(*) FROM aj_expedientes WHERE EXTRACT(YEAR FROM created_at)=".date('Y'))->fetchColumn();
$totalDictAnio = (int)$pdo->query("SELECT COUNT(*) FROM aj_dictamenes WHERE EXTRACT(YEAR FROM created_at)=".date('Y'))->fetchColumn();

// Expedientes activos recientes
$stmtE = $pdo->prepare("SELECT folio, tipo, asunto, estatus FROM aj_expedientes WHERE estatus IN('activo','en_proceso') ORDER BY created_at DESC LIMIT 5");
$stmtE->execute(); $expedRecientes = $stmtE->fetchAll();

// Dictámenes pendientes
$stmtD = $pdo->prepare("SELECT folio, titulo, area_solicitante, estatus FROM aj_dictamenes WHERE estatus IN('pendiente','en_elaboracion') ORDER BY fecha_solicitud ASC LIMIT 5");
$stmtD->execute(); $dictPendientes = $stmtD->fetchAll();

$pageTitle = 'Panel Jurídico'; $activeMenu = 'dashboard';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--aj-primary:#991b1b;--aj-dark:#7f1d1d;--aj-light:#b91c1c;--aj-accent:#B19A6D;--aj-soft:rgba(153,27,27,.06);}
.hero-aj{background:linear-gradient(135deg,var(--aj-primary) 0%,var(--aj-dark) 100%);border-radius:20px;padding:44px 52px;margin-bottom:30px;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 20px 40px rgba(153,27,27,.25);position:relative;overflow:hidden;}
.hero-aj::after{content:'';position:absolute;width:360px;height:360px;background:radial-gradient(circle,rgba(177,154,109,.1) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;}
.hero-aj h1{font-size:2.4rem;font-weight:800;color:var(--aj-accent);margin:0 0 10px;}
.hero-aj p{margin:0;opacity:.9;font-size:1.05rem;max-width:540px;}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:34px;}
.kpi-card{background:#fff;border-radius:18px;padding:26px 22px;border:1px solid #e2e8f0;display:flex;align-items:center;gap:16px;transition:all .25s;text-decoration:none;color:inherit;}
.kpi-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(153,27,27,.1);border-color:var(--aj-accent);}
.kpi-icon{width:58px;height:58px;display:flex;align-items:center;justify-content:center;border-radius:14px;font-size:1.6rem;flex-shrink:0;}
.kpi-val{font-size:2.2rem;font-weight:800;color:var(--aj-primary);line-height:1;}
.kpi-lbl{font-size:.85rem;color:#64748b;margin-top:4px;font-weight:500;}
.quick-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px;margin-bottom:36px;}
.quick-btn{display:flex;flex-direction:column;align-items:center;gap:10px;padding:22px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;font-size:.88rem;font-weight:600;color:var(--aj-primary);text-decoration:none;text-align:center;transition:all .2s;}
.quick-btn i{font-size:1.6rem;color:var(--aj-accent);}
.quick-btn:hover{background:var(--aj-primary);color:#fff;border-color:var(--aj-primary);}
.quick-btn:hover i{color:var(--aj-accent);}
.section-title{font-size:1.2rem;font-weight:700;color:var(--aj-primary);display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.section-title i{color:var(--aj-accent);}
.aj-table{width:100%;border-collapse:collapse;}
.aj-table th{text-align:left;padding:11px 16px;font-size:.77rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.aj-table td{padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:.86rem;color:#334155;}
.aj-table tr:last-child td{border-bottom:none;}
.aj-table tr:hover td{background:#f8fafc;}
.badge-activo{background:#dcfce7;color:#16a34a;}.badge-en_proceso{background:#e0f2fe;color:#0284c7;}.badge-resuelto{background:#f1f5f9;color:#475569;}.badge-archivado{background:#f1f5f9;color:#94a3b8;}
.badge-pendiente{background:#fef3c7;color:#d97706;}.badge-en_elaboracion{background:#e0f2fe;color:#0284c7;}.badge-emitido{background:#dcfce7;color:#16a34a;}
.badge-vigente{background:#dcfce7;color:#16a34a;}.badge-cumplido{background:#f1f5f9;color:#475569;}.badge-cancelado{background:#fee2e2;color:#dc2626;}
.badge-pill{padding:3px 9px;border-radius:20px;font-size:.73rem;font-weight:600;}
.folio-tag{font-family:monospace;background:#fff1f2;color:#991b1b;padding:3px 9px;border-radius:8px;font-size:.78rem;font-weight:600;}
</style>

<div class="hero-aj">
    <div>
        <h1>Asuntos Jurídicos</h1>
        <p>Gestión de expedientes jurídicos, dictámenes y opiniones, acuerdos y resoluciones institucionales.</p>
    </div>
    <i class="fa-solid fa-gavel" style="font-size:6rem;opacity:.12;color:var(--aj-accent);flex-shrink:0;"></i>
</div>

<div class="kpi-grid">
    <a href="expedientes.php" class="kpi-card"><div class="kpi-icon" style="background:#fff1f2;color:#991b1b;"><i class="fa-solid fa-folder-open"></i></div><div><div class="kpi-val"><?=$expedActivos?></div><div class="kpi-lbl">Expedientes Activos</div></div></a>
    <a href="dictamenes.php" class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-scroll"></i></div><div><div class="kpi-val"><?=$dictPend?></div><div class="kpi-lbl">Dictámenes Pendientes</div></div></a>
    <a href="acuerdos.php" class="kpi-card"><div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="fa-solid fa-file-signature"></i></div><div><div class="kpi-val"><?=$acuerdVig?></div><div class="kpi-lbl">Acuerdos Vigentes</div></div></a>
    <a href="agenda.php" class="kpi-card"><div class="kpi-icon" style="background:var(--aj-soft);color:var(--aj-primary);"><i class="fa-solid fa-list-check"></i></div><div><div class="kpi-val"><?=$tareasPend?></div><div class="kpi-lbl">Tareas Pendientes</div></div></a>
    <div class="kpi-card" style="cursor:default;"><div class="kpi-icon" style="background:#fff1f2;color:#991b1b;"><i class="fa-solid fa-calendar-plus"></i></div><div><div class="kpi-val"><?=$totalExpedAnio?></div><div class="kpi-lbl">Expedientes <?=date('Y')?></div></div></div>
    <div class="kpi-card" style="cursor:default;"><div class="kpi-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fa-solid fa-pen-nib"></i></div><div><div class="kpi-val"><?=$totalDictAnio?></div><div class="kpi-lbl">Dictámenes <?=date('Y')?></div></div></div>
</div>

<h2 class="section-title"><i class="fa-solid fa-bolt"></i> Accesos Rápidos</h2>
<div class="quick-grid">
    <a href="agenda.php"       class="quick-btn"><i class="fa-solid fa-calendar-week"></i>Agenda y Kanban</a>
    <a href="personal.php"     class="quick-btn"><i class="fa-solid fa-id-card"></i>Personal</a>
    <a href="expedientes.php"  class="quick-btn"><i class="fa-solid fa-folder-open"></i>Expedientes</a>
    <a href="dictamenes.php"   class="quick-btn"><i class="fa-solid fa-scroll"></i>Dictámenes</a>
    <a href="acuerdos.php"     class="quick-btn"><i class="fa-solid fa-file-signature"></i>Acuerdos</a>
    <a href="reportes.php"     class="quick-btn"><i class="fa-solid fa-chart-bar"></i>Reportes</a>
</div>

<?php if(!empty($expedRecientes)): ?>
<h2 class="section-title"><i class="fa-solid fa-folder-open"></i> Expedientes Activos Recientes</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);margin-bottom:30px;">
<table class="aj-table"><thead><tr><th>Folio</th><th>Tipo</th><th>Asunto</th><th>Estatus</th></tr></thead><tbody>
<?php foreach($expedRecientes as $e): ?>
<tr>
    <td><span class="folio-tag"><?=esc($e['folio'])?></span></td>
    <td><span class="badge-pill" style="background:#fff1f2;color:#991b1b;"><?=ucfirst(esc($e['tipo']))?></span></td>
    <td style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.82rem;"><?=esc(mb_strimwidth($e['asunto']??'—',0,70,'...'))?></td>
    <td><span class="badge-pill badge-<?=$e['estatus']?>"><?=ucfirst(str_replace('_',' ',$e['estatus']))?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php if(!empty($dictPendientes)): ?>
<h2 class="section-title"><i class="fa-solid fa-scroll"></i> Dictámenes en Proceso</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<table class="aj-table"><thead><tr><th>Folio</th><th>Título</th><th>Área Solicitante</th><th>Estatus</th></tr></thead><tbody>
<?php foreach($dictPendientes as $d): ?>
<tr>
    <td><span class="folio-tag"><?=esc($d['folio'])?></span></td>
    <td style="font-size:.82rem;font-weight:600;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($d['titulo'])?></td>
    <td style="font-size:.82rem;"><?=esc($d['area_solicitante']?:'—')?></td>
    <td><span class="badge-pill badge-<?=$d['estatus']?>"><?=ucfirst(str_replace('_',' ',$d['estatus']))?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>