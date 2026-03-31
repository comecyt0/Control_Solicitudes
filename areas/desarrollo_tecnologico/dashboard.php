<?php
/**
 * COMECyT — Panel Desarrollo Tecnológico y Vinculación
 * Dashboard — cve_area = 12
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $cveArea = 12;

$proyActivos   = (int)$pdo->query("SELECT COUNT(*) FROM dt_proyectos WHERE estatus IN('activo','en_desarrollo')")->fetchColumn();
$conveniosVig  = (int)$pdo->query("SELECT COUNT(*) FROM dt_convenios WHERE estatus='vigente'")->fetchColumn();
$transferencias= (int)$pdo->query("SELECT COUNT(*) FROM dt_transferencias WHERE estatus='en_proceso'")->fetchColumn();
$tareasPend    = (int)$pdo->query("SELECT COUNT(*) FROM sb_kanban_tareas WHERE cve_area=$cveArea AND estatus!='completada'")->fetchColumn();
$totalProyAnio = (int)$pdo->query("SELECT COUNT(*) FROM dt_proyectos WHERE EXTRACT(YEAR FROM created_at)=".date('Y'))->fetchColumn();
$totalConvenios= (int)$pdo->query("SELECT COUNT(*) FROM dt_convenios")->fetchColumn();

// Proyectos recientes
$stmtP = $pdo->prepare("SELECT nombre, tipo, lider, estatus, tecnologias FROM dt_proyectos ORDER BY created_at DESC LIMIT 5");
$stmtP->execute(); $proxProyectos = $stmtP->fetchAll();

// Convenios próximos a vencer
$stmtC = $pdo->prepare("SELECT folio, institucion, tipo, fecha_fin, estatus FROM dt_convenios WHERE estatus='vigente' AND fecha_fin IS NOT NULL ORDER BY fecha_fin ASC LIMIT 5");
$stmtC->execute(); $proxConvenios = $stmtC->fetchAll();

$pageTitle = 'Panel DT'; $activeMenu = 'dashboard';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--dt-primary:#6d28d9;--dt-dark:#4c1d95;--dt-light:#7c3aed;--dt-accent:#B19A6D;--dt-soft:rgba(109,40,217,.06);}
.hero-dt{background:linear-gradient(135deg,var(--dt-primary) 0%,var(--dt-dark) 100%);border-radius:20px;padding:44px 52px;margin-bottom:30px;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 20px 40px rgba(109,40,217,.25);position:relative;overflow:hidden;}
.hero-dt::after{content:'';position:absolute;width:360px;height:360px;background:radial-gradient(circle,rgba(177,154,109,.1) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;}
.hero-dt h1{font-size:2.4rem;font-weight:800;color:var(--dt-accent);margin:0 0 10px;}
.hero-dt p{margin:0;opacity:.9;font-size:1.05rem;max-width:540px;}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:34px;}
.kpi-card{background:#fff;border-radius:18px;padding:26px 22px;border:1px solid #e2e8f0;display:flex;align-items:center;gap:16px;transition:all .25s;text-decoration:none;color:inherit;}
.kpi-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(109,40,217,.1);border-color:var(--dt-accent);}
.kpi-icon{width:58px;height:58px;display:flex;align-items:center;justify-content:center;border-radius:14px;font-size:1.6rem;flex-shrink:0;}
.kpi-val{font-size:2.2rem;font-weight:800;color:var(--dt-primary);line-height:1;}
.kpi-lbl{font-size:.85rem;color:#64748b;margin-top:4px;font-weight:500;}
.quick-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px;margin-bottom:36px;}
.quick-btn{display:flex;flex-direction:column;align-items:center;gap:10px;padding:22px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;font-size:.88rem;font-weight:600;color:var(--dt-primary);text-decoration:none;text-align:center;transition:all .2s;}
.quick-btn i{font-size:1.6rem;color:var(--dt-accent);}
.quick-btn:hover{background:var(--dt-primary);color:#fff;border-color:var(--dt-primary);}
.quick-btn:hover i{color:var(--dt-accent);}
.section-title{font-size:1.2rem;font-weight:700;color:var(--dt-primary);display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.section-title i{color:var(--dt-accent);}
.dt-table{width:100%;border-collapse:collapse;}
.dt-table th{text-align:left;padding:11px 16px;font-size:.77rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.dt-table td{padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:.86rem;color:#334155;}
.dt-table tr:last-child td{border-bottom:none;}
.dt-table tr:hover td{background:#f8fafc;}
.pill{display:inline-block;padding:2px 9px;border-radius:10px;font-size:.72rem;font-weight:600;background:#ede9fe;color:#6d28d9;margin:1px;}
.badge-activo{background:#dcfce7;color:#16a34a;}.badge-en_desarrollo{background:#e0f2fe;color:#0284c7;}.badge-pausado{background:#fef3c7;color:#d97706;}.badge-concluido{background:#f1f5f9;color:#475569;}
.badge-vigente{background:#dcfce7;color:#16a34a;}.badge-vencido{background:#fee2e2;color:#dc2626;}.badge-rescindido{background:#f1f5f9;color:#475569;}
.badge-pill{padding:3px 9px;border-radius:20px;font-size:.73rem;font-weight:600;}
</style>

<div class="hero-dt">
    <div>
        <h1>Desarrollo Tecnológico y Vinculación</h1>
        <p>Gestión de proyectos tecnológicos, convenios de colaboración y transferencia tecnológica del COMECyT.</p>
    </div>
    <i class="fa-solid fa-microchip" style="font-size:6rem;opacity:.12;color:var(--dt-accent);flex-shrink:0;"></i>
</div>

<div class="kpi-grid">
    <a href="proyectos.php" class="kpi-card"><div class="kpi-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fa-solid fa-diagram-project"></i></div><div><div class="kpi-val"><?=$proyActivos?></div><div class="kpi-lbl">Proyectos Activos</div></div></a>
    <a href="convenios.php" class="kpi-card"><div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="fa-solid fa-handshake"></i></div><div><div class="kpi-val"><?=$conveniosVig?></div><div class="kpi-lbl">Convenios Vigentes</div></div></a>
    <a href="transferencias.php" class="kpi-card"><div class="kpi-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fa-solid fa-flask"></i></div><div><div class="kpi-val"><?=$transferencias?></div><div class="kpi-lbl">Transferencias Activas</div></div></a>
    <a href="agenda.php" class="kpi-card"><div class="kpi-icon" style="background:var(--dt-soft);color:var(--dt-primary);"><i class="fa-solid fa-list-check"></i></div><div><div class="kpi-val"><?=$tareasPend?></div><div class="kpi-lbl">Tareas Pendientes</div></div></a>
    <div class="kpi-card" style="cursor:default;"><div class="kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-calendar-plus"></i></div><div><div class="kpi-val"><?=$totalProyAnio?></div><div class="kpi-lbl">Proyectos <?=date('Y')?></div></div></div>
    <div class="kpi-card" style="cursor:default;"><div class="kpi-icon" style="background:#fce7f3;color:#db2777;"><i class="fa-solid fa-file-signature"></i></div><div><div class="kpi-val"><?=$totalConvenios?></div><div class="kpi-lbl">Convenios Total</div></div></div>
</div>

<h2 class="section-title"><i class="fa-solid fa-bolt"></i> Accesos Rápidos</h2>
<div class="quick-grid">
    <a href="agenda.php"        class="quick-btn"><i class="fa-solid fa-calendar-week"></i>Agenda y Kanban</a>
    <a href="personal.php"      class="quick-btn"><i class="fa-solid fa-id-card"></i>Personal</a>
    <a href="proyectos.php"     class="quick-btn"><i class="fa-solid fa-diagram-project"></i>Proyectos</a>
    <a href="convenios.php"     class="quick-btn"><i class="fa-solid fa-handshake"></i>Convenios</a>
    <a href="transferencias.php"class="quick-btn"><i class="fa-solid fa-flask"></i>Transferencias</a>
    <a href="reportes.php"      class="quick-btn"><i class="fa-solid fa-chart-bar"></i>Reportes</a>
</div>

<?php if(!empty($proxProyectos)): ?>
<h2 class="section-title"><i class="fa-solid fa-diagram-project"></i> Proyectos Recientes</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);margin-bottom:30px;">
<table class="dt-table"><thead><tr><th>Nombre</th><th>Tipo</th><th>Líder</th><th>Tecnologías</th><th>Estatus</th></tr></thead><tbody>
<?php foreach($proxProyectos as $p): $cls='badge-'.str_replace(' ','_',$p['estatus']); $techs=array_filter(array_map('trim',explode(',',$p['tecnologias']??''))); ?>
<tr>
    <td style="font-weight:600;color:#0f172a;"><?=esc($p['nombre'])?></td>
    <td><span class="badge-pill" style="background:#ede9fe;color:#6d28d9;"><?=ucfirst(esc($p['tipo']))?></span></td>
    <td style="font-size:.82rem;"><?=esc($p['lider']?:'—')?></td>
    <td><?php foreach($techs as $t): ?><span class="pill"><?=esc($t)?></span><?php endforeach; if(empty($techs)) echo '—'; ?></td>
    <td><span class="badge-pill <?=$cls?>"><?=ucfirst(str_replace('_',' ',$p['estatus']))?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php if(!empty($proxConvenios)): ?>
<h2 class="section-title"><i class="fa-solid fa-handshake"></i> Convenios Próximos a Vencer</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<table class="dt-table"><thead><tr><th>Folio</th><th>Institución</th><th>Tipo</th><th>Vence</th><th>Estatus</th></tr></thead><tbody>
<?php foreach($proxConvenios as $c): $hoy=new DateTime(); $fin=new DateTime($c['fecha_fin']); $dias=$hoy->diff($fin)->days; $alerta=$dias<=30?'color:#dc2626;font-weight:700;':''; ?>
<tr>
    <td style="font-family:monospace;font-size:.78rem;"><?=esc($c['folio'])?></td>
    <td style="font-weight:600;"><?=esc($c['institucion'])?></td>
    <td><span class="badge-pill" style="background:#e0f2fe;color:#0369a1;"><?=ucfirst(esc($c['tipo']))?></span></td>
    <td style="font-size:.8rem;<?=$alerta?>"><?=date('d/m/Y',strtotime($c['fecha_fin']))?></td>
    <td><span class="badge-pill badge-vigente">Vigente</span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>