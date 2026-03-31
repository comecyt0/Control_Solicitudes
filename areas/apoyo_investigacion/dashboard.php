<?php
/**
 * COMECyT — Panel Apoyo a la Investigación Científica
 * Dashboard Ejecutivo del Área — cve_area = 9
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();

$pdo     = getConnection();
$cveArea = 9;

// KPIs
$proyActivos   = (int) $pdo->query("SELECT COUNT(*) FROM inv_proyectos WHERE estatus = 'activo'")->fetchColumn();
$investigadores= (int) $pdo->query("SELECT COUNT(*) FROM inv_investigadores WHERE activo = TRUE")->fetchColumn();
$anioActual    = date('Y');
$publicaciones = (int) $pdo->query("SELECT COUNT(*) FROM inv_publicaciones WHERE anio = $anioActual")->fetchColumn();
$convActivas   = (int) $pdo->query("SELECT COUNT(*) FROM fin_convocatorias WHERE estatus = 'activa'")->fetchColumn();
$tareasActivas = (int) $pdo->query("SELECT COUNT(*) FROM sb_kanban_tareas WHERE cve_area = $cveArea AND estatus != 'completada'")->fetchColumn();
$totalPersonal = (int) $pdo->query("SELECT COUNT(*) FROM cat_personal WHERE cve_area = $cveArea AND activo = TRUE")->fetchColumn();

// Proyectos próximos a terminar
$stmtProy = $pdo->prepare("SELECT nombre, lider, fecha_fin, estatus FROM inv_proyectos WHERE estatus = 'activo' AND fecha_fin IS NOT NULL ORDER BY fecha_fin ASC LIMIT 5");
$stmtProy->execute();
$proxFinales = $stmtProy->fetchAll();

// Últimas publicaciones
$stmtPub = $pdo->prepare("SELECT titulo, tipo, anio, autores FROM inv_publicaciones ORDER BY created_at DESC LIMIT 5");
$stmtPub->execute();
$ultimasPub = $stmtPub->fetchAll();

$pageTitle  = 'Panel de Investigación';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root {
    --inv-primary : #3730a3;
    --inv-dark    : #1e1b4b;
    --inv-accent  : #B19A6D;
    --inv-soft    : rgba(55,48,163,.06);
    --inv-border  : rgba(55,48,163,.15);
}
.hero-inv {
    background: linear-gradient(135deg, var(--inv-primary) 0%, var(--inv-dark) 100%);
    border-radius: 20px; padding: 44px 52px; margin-bottom: 30px;
    color: #fff; display: flex; justify-content: space-between; align-items: center;
    box-shadow: 0 20px 40px rgba(55,48,163,.25); position: relative; overflow: hidden;
}
.hero-inv::after { content:'';position:absolute;width:350px;height:350px;background:radial-gradient(circle,rgba(177,154,109,.12) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%; }
.hero-inv h1 { font-size:2.6rem;font-weight:800;color:var(--inv-accent);margin:0 0 10px; }
.hero-inv p  { margin:0;opacity:.9;font-size:1.05rem;max-width:560px; }
.kpi-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:34px; }
.kpi-card { background:#fff;border-radius:18px;padding:26px 22px;border:1px solid #e2e8f0;display:flex;align-items:center;gap:16px;transition:all .25s;text-decoration:none;color:inherit; }
.kpi-card:hover { transform:translateY(-4px);box-shadow:0 12px 28px rgba(55,48,163,.1);border-color:var(--inv-accent); }
.kpi-icon { width:58px;height:58px;display:flex;align-items:center;justify-content:center;border-radius:14px;font-size:1.6rem;flex-shrink:0; }
.kpi-val  { font-size:2.2rem;font-weight:800;color:var(--inv-primary);line-height:1; }
.kpi-lbl  { font-size:.85rem;color:#64748b;margin-top:4px;font-weight:500; }
.inv-section-title { font-size:1.2rem;font-weight:700;color:var(--inv-primary);display:flex;align-items:center;gap:10px;margin-bottom:16px; }
.inv-section-title i { color:var(--inv-accent); }
.quick-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px;margin-bottom:36px; }
.quick-btn { display:flex;flex-direction:column;align-items:center;gap:10px;padding:22px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;font-size:.88rem;font-weight:600;color:var(--inv-primary);text-decoration:none;text-align:center;transition:all .2s; }
.quick-btn i { font-size:1.6rem;color:var(--inv-accent); }
.quick-btn:hover { background:var(--inv-primary);color:#fff;border-color:var(--inv-primary); }
.quick-btn:hover i { color:var(--inv-accent); }
.inv-table { width:100%;border-collapse:collapse; }
.inv-table th { text-align:left;padding:12px 18px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0; }
.inv-table td { padding:13px 18px;border-bottom:1px solid #f1f5f9;font-size:.87rem;color:#334155; }
.inv-table tr:last-child td { border-bottom:none; }
.inv-table tr:hover td { background:#f8fafc; }
.badge-tipo { background:#ede9fe;color:#6d28d9;padding:3px 9px;border-radius:12px;font-size:.73rem;font-weight:700; }
</style>

<!-- HERO -->
<div class="hero-inv">
    <div>
        <h1>Apoyo a la Investigación Científica</h1>
        <p>Gestión de proyectos, investigadores, publicaciones y fondos de ciencia y tecnología del COMECyT.</p>
    </div>
    <i class="fa-solid fa-flask" style="font-size:6rem;opacity:.12;color:var(--inv-accent);flex-shrink:0;"></i>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <a href="proyectos.php" class="kpi-card">
        <div class="kpi-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fa-solid fa-diagram-project"></i></div>
        <div><div class="kpi-val"><?= $proyActivos ?></div><div class="kpi-lbl">Proyectos Activos</div></div>
    </a>
    <a href="investigadores.php" class="kpi-card">
        <div class="kpi-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fa-solid fa-user-astronaut"></i></div>
        <div><div class="kpi-val"><?= $investigadores ?></div><div class="kpi-lbl">Investigadores Activos</div></div>
    </a>
    <a href="publicaciones.php" class="kpi-card">
        <div class="kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-book-open"></i></div>
        <div><div class="kpi-val"><?= $publicaciones ?></div><div class="kpi-lbl">Publicaciones <?= $anioActual ?></div></div>
    </a>
    <a href="convocatorias.php" class="kpi-card">
        <div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="fa-solid fa-satellite-dish"></i></div>
        <div><div class="kpi-val"><?= $convActivas ?></div><div class="kpi-lbl">Convocatorias Abiertas</div></div>
    </a>
    <a href="agenda.php" class="kpi-card">
        <div class="kpi-icon" style="background:var(--inv-soft);color:var(--inv-primary);"><i class="fa-solid fa-list-check"></i></div>
        <div><div class="kpi-val"><?= $tareasActivas ?></div><div class="kpi-lbl">Tareas del Área</div></div>
    </a>
    <div class="kpi-card" style="cursor:default;">
        <div class="kpi-icon" style="background:var(--inv-soft);color:var(--inv-primary);"><i class="fa-solid fa-users"></i></div>
        <div><div class="kpi-val"><?= $totalPersonal ?></div><div class="kpi-lbl">Personal del Área</div></div>
    </div>
</div>

<!-- Accesos Rápidos -->
<h2 class="inv-section-title"><i class="fa-solid fa-bolt"></i> Accesos Rápidos</h2>
<div class="quick-grid">
    <a href="agenda.php"        class="quick-btn"><i class="fa-solid fa-calendar-week"></i>Agenda y Kanban</a>
    <a href="personal.php"      class="quick-btn"><i class="fa-solid fa-id-card"></i>Personal</a>
    <a href="proyectos.php"     class="quick-btn"><i class="fa-solid fa-diagram-project"></i>Proyectos</a>
    <a href="investigadores.php"class="quick-btn"><i class="fa-solid fa-user-astronaut"></i>Investigadores</a>
    <a href="publicaciones.php" class="quick-btn"><i class="fa-solid fa-book-open"></i>Publicaciones</a>
    <a href="convocatorias.php" class="quick-btn"><i class="fa-solid fa-satellite-dish"></i>Convocatorias</a>
</div>

<!-- Proyectos próximos a terminar -->
<?php if (!empty($proxFinales)): ?>
<h2 class="inv-section-title"><i class="fa-solid fa-hourglass-half"></i> Proyectos Próximos a Finalizar</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);margin-bottom:30px;">
    <table class="inv-table">
        <thead><tr><th>Proyecto</th><th>Líder</th><th>Fecha Fin</th><th>Estatus</th></tr></thead>
        <tbody>
        <?php foreach ($proxFinales as $p): $dias=(int)(new DateTime($p['fecha_fin']))->diff(new DateTime())->format('%r%a'); ?>
        <tr>
            <td style="font-weight:600;color:#0f172a;"><?= esc($p['nombre']) ?></td>
            <td><?= esc($p['lider'] ?: '—') ?></td>
            <td style="font-size:.82rem;"><?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></td>
            <td><span style="background:<?= $dias <= 30 ? '#fef3c7' : '#dcfce7' ?>;color:<?= $dias <= 30 ? '#d97706' : '#16a34a' ?>;padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:600;"><?= $dias <= 30 ? "Cierra en $dias días" : 'En tiempo' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Últimas publicaciones -->
<?php if (!empty($ultimasPub)): ?>
<h2 class="inv-section-title"><i class="fa-solid fa-scroll"></i> Publicaciones Recientes</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
    <table class="inv-table">
        <thead><tr><th>Título</th><th>Tipo</th><th>Autores</th><th>Año</th></tr></thead>
        <tbody>
        <?php foreach ($ultimasPub as $pub): ?>
        <tr>
            <td style="font-weight:600;color:#0f172a;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= esc($pub['titulo']) ?></td>
            <td><span class="badge-tipo"><?= ucfirst(esc($pub['tipo'])) ?></span></td>
            <td style="font-size:.82rem;"><?= esc(mb_strimwidth($pub['autores'] ?? '—', 0, 50, '...')) ?></td>
            <td><?= esc($pub['anio'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>