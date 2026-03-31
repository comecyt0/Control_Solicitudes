<?php
/**
 * COMECyT — Panel Formación de Recursos Humanos
 * Dashboard — cve_area = 10
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection(); $cveArea = 10;

$cursosActivos  = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_cursos WHERE estatus IN('programado','en_curso')")->fetchColumn();
$totalBecas     = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_becas WHERE estatus='activa'")->fetchColumn();
$accionesIg     = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_igualdad WHERE estatus != 'concluida'")->fetchColumn();
$tareasPend     = (int)$pdo->query("SELECT COUNT(*) FROM sb_kanban_tareas WHERE cve_area=$cveArea AND estatus!='completada'")->fetchColumn();
$totalPersonal  = (int)$pdo->query("SELECT COUNT(*) FROM cat_personal WHERE cve_area=$cveArea AND activo=TRUE")->fetchColumn();
$cursosAno      = (int)$pdo->query("SELECT COUNT(*) FROM rrhh_cursos WHERE EXTRACT(YEAR FROM created_at)=".date('Y'))->fetchColumn();

// Próximos cursos
$stmtC = $pdo->prepare("SELECT nombre, modalidad, fecha_inicio, fecha_fin, inscritos, cupo FROM rrhh_cursos WHERE estatus IN('programado','en_curso') AND fecha_inicio IS NOT NULL ORDER BY fecha_inicio ASC LIMIT 5");
$stmtC->execute(); $proxCursos = $stmtC->fetchAll();

// Acciones igualdad recientes
$stmtI = $pdo->prepare("SELECT folio, tipo, estatus, responsable FROM rrhh_igualdad ORDER BY created_at DESC LIMIT 5");
$stmtI->execute(); $accionesRec = $stmtI->fetchAll();

$pageTitle = 'Panel RRHH'; $activeMenu = 'dashboard';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--rrhh-primary:#0f766e;--rrhh-dark:#134e4a;--rrhh-accent:#B19A6D;--rrhh-soft:rgba(15,118,110,.06);}
.hero-rrhh{background:linear-gradient(135deg,var(--rrhh-primary) 0%,var(--rrhh-dark) 100%);border-radius:20px;padding:44px 52px;margin-bottom:30px;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 20px 40px rgba(15,118,110,.25);position:relative;overflow:hidden;}
.hero-rrhh::after{content:'';position:absolute;width:350px;height:350px;background:radial-gradient(circle,rgba(177,154,109,.12) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;}
.hero-rrhh h1{font-size:2.4rem;font-weight:800;color:var(--rrhh-accent);margin:0 0 10px;}
.hero-rrhh p{margin:0;opacity:.9;font-size:1.05rem;max-width:540px;}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:34px;}
.kpi-card{background:#fff;border-radius:18px;padding:26px 22px;border:1px solid #e2e8f0;display:flex;align-items:center;gap:16px;transition:all .25s;text-decoration:none;color:inherit;}
.kpi-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(15,118,110,.1);border-color:var(--rrhh-accent);}
.kpi-icon{width:58px;height:58px;display:flex;align-items:center;justify-content:center;border-radius:14px;font-size:1.6rem;flex-shrink:0;}
.kpi-val{font-size:2.2rem;font-weight:800;color:var(--rrhh-primary);line-height:1;}
.kpi-lbl{font-size:.85rem;color:#64748b;margin-top:4px;font-weight:500;}
.quick-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px;margin-bottom:36px;}
.quick-btn{display:flex;flex-direction:column;align-items:center;gap:10px;padding:22px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;font-size:.88rem;font-weight:600;color:var(--rrhh-primary);text-decoration:none;text-align:center;transition:all .2s;}
.quick-btn i{font-size:1.6rem;color:var(--rrhh-accent);}
.quick-btn:hover{background:var(--rrhh-primary);color:#fff;border-color:var(--rrhh-primary);}
.quick-btn:hover i{color:var(--rrhh-accent);}
.section-title{font-size:1.2rem;font-weight:700;color:var(--rrhh-primary);display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.section-title i{color:var(--rrhh-accent);}
.rrhh-table{width:100%;border-collapse:collapse;}
.rrhh-table th{text-align:left;padding:12px 18px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.rrhh-table td{padding:13px 18px;border-bottom:1px solid #f1f5f9;font-size:.87rem;color:#334155;}
.rrhh-table tr:last-child td{border-bottom:none;}
.rrhh-table tr:hover td{background:#f8fafc;}
.cupo-bar{height:8px;background:#e2e8f0;border-radius:10px;margin-top:4px;overflow:hidden;}
.cupo-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#0f766e,#14b8a6);}
</style>

<div class="hero-rrhh">
    <div>
        <h1>Formación de Recursos Humanos</h1>
        <p>Gestión de cursos, capacitaciones, becas educativas y programa de igualdad de género del COMECyT.</p>
    </div>
    <i class="fa-solid fa-graduation-cap" style="font-size:6rem;opacity:.12;color:var(--rrhh-accent);flex-shrink:0;"></i>
</div>

<div class="kpi-grid">
    <a href="cursos.php" class="kpi-card"><div class="kpi-icon" style="background:#ccfbf1;color:#0f766e;"><i class="fa-solid fa-chalkboard-user"></i></div><div><div class="kpi-val"><?=$cursosActivos?></div><div class="kpi-lbl">Cursos Activos</div></div></a>
    <a href="becas.php" class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-award"></i></div><div><div class="kpi-val"><?=$totalBecas?></div><div class="kpi-lbl">Becas Vigentes</div></div></a>
    <a href="igualdad.php" class="kpi-card"><div class="kpi-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fa-solid fa-scale-balanced"></i></div><div><div class="kpi-val"><?=$accionesIg?></div><div class="kpi-lbl">Acciones Igualdad</div></div></a>
    <a href="agenda.php" class="kpi-card"><div class="kpi-icon" style="background:var(--rrhh-soft);color:var(--rrhh-primary);"><i class="fa-solid fa-list-check"></i></div><div><div class="kpi-val"><?=$tareasPend?></div><div class="kpi-lbl">Tareas Pendientes</div></div></a>
    <div class="kpi-card" style="cursor:default;"><div class="kpi-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fa-solid fa-users"></i></div><div><div class="kpi-val"><?=$totalPersonal?></div><div class="kpi-lbl">Personal del Área</div></div></div>
    <div class="kpi-card" style="cursor:default;"><div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="fa-solid fa-calendar-check"></i></div><div><div class="kpi-val"><?=$cursosAno?></div><div class="kpi-lbl">Cursos <?=date('Y')?></div></div></div>
</div>

<h2 class="section-title"><i class="fa-solid fa-bolt"></i> Accesos Rápidos</h2>
<div class="quick-grid">
    <a href="agenda.php"   class="quick-btn"><i class="fa-solid fa-calendar-week"></i>Agenda y Kanban</a>
    <a href="personal.php" class="quick-btn"><i class="fa-solid fa-id-card"></i>Personal</a>
    <a href="cursos.php"   class="quick-btn"><i class="fa-solid fa-chalkboard-user"></i>Cursos</a>
    <a href="becas.php"    class="quick-btn"><i class="fa-solid fa-award"></i>Becas</a>
    <a href="igualdad.php" class="quick-btn"><i class="fa-solid fa-scale-balanced"></i>Igualdad</a>
    <a href="reportes.php" class="quick-btn"><i class="fa-solid fa-chart-bar"></i>Reportes</a>
</div>

<?php if(!empty($proxCursos)): ?>
<h2 class="section-title"><i class="fa-solid fa-chalkboard-user"></i> Próximos Cursos y Capacitaciones</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);margin-bottom:30px;">
<table class="rrhh-table"><thead><tr><th>Curso</th><th>Modalidad</th><th>Fecha</th><th>Cupo / Inscritos</th></tr></thead><tbody>
<?php foreach($proxCursos as $c): $pct=$c['cupo']>0?min(100,round($c['inscritos']/$c['cupo']*100)):0; ?>
<tr>
    <td style="font-weight:600;color:#0f172a;"><?=esc($c['nombre'])?></td>
    <td><span style="background:#ccfbf1;color:#0f766e;padding:2px 8px;border-radius:10px;font-size:.75rem;font-weight:600;"><?=ucfirst(esc($c['modalidad']))?></span></td>
    <td style="font-size:.82rem;"><?=$c['fecha_inicio']?date('d/m/Y',strtotime($c['fecha_inicio'])):'S/F'?></td>
    <td><?=($c['inscritos']??0).' / '.($c['cupo']??'∞')?><div class="cupo-bar"><div class="cupo-fill" style="width:<?=$pct?>%"></div></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php if(!empty($accionesRec)): ?>
<h2 class="section-title"><i class="fa-solid fa-scale-balanced"></i> Acciones de Igualdad Recientes</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<table class="rrhh-table"><thead><tr><th>Folio</th><th>Tipo</th><th>Responsable</th><th>Estatus</th></tr></thead><tbody>
<?php foreach($accionesRec as $a): $colEst=['pendiente'=>'#fef3c7|#d97706','en_proceso'=>'#e0f2fe|#0284c7','concluida'=>'#dcfce7|#16a34a'][$a['estatus']]??'#f1f5f9|#475569';[$bg,$fg]=explode('|',$colEst); ?>
<tr>
    <td style="font-family:monospace;font-size:.8rem;"><?=esc($a['folio'])?></td>
    <td><?=esc($a['tipo'])?></td>
    <td><?=esc($a['responsable']?:'—')?></td>
    <td><span style="background:<?=$bg?>;color:<?=$fg?>;padding:3px 10px;border-radius:12px;font-size:.72rem;font-weight:600;"><?=ucfirst(str_replace('_',' ',$a['estatus']))?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>