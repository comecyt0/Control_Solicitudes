<?php
/**
 * COMECyT — Panel Financiamiento
 * Dashboard Ejecutivo del Área
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo     = getConnection();
$cveArea = 15;

// KPIs
$convActivas  = (int) $pdo->query("SELECT COUNT(*) FROM fin_convocatorias WHERE estatus = 'activa'")->fetchColumn();
$becasVig     = (int) $pdo->query("SELECT COUNT(*) FROM fin_becas WHERE estatus_pago = 'pendiente'")->fetchColumn();
$tareasActivas= (int) $pdo->query("SELECT COUNT(*) FROM sb_kanban_tareas WHERE cve_area = $cveArea AND estatus != 'completada'")->fetchColumn();
$totalPersonal= (int) $pdo->query("SELECT COUNT(*) FROM cat_personal WHERE cve_area = $cveArea AND activo = TRUE")->fetchColumn();

// Presupuesto global del año
$anioActual = date('Y');
$stmtP = $pdo->query("SELECT COALESCE(SUM(monto_asignado),0) AS asig, COALESCE(SUM(monto_ejercido),0) AS ejec FROM fin_presupuesto WHERE anio = $anioActual");
$pres  = $stmtP->fetch();
$pctPres = $pres['asig'] > 0 ? round(($pres['ejec'] / $pres['asig']) * 100) : 0;

// Próximas convocatorias que cierran
$stmtC = $pdo->prepare("SELECT titulo, dependencia, fecha_cierre FROM fin_convocatorias WHERE estatus = 'activa' AND fecha_cierre >= CURRENT_DATE ORDER BY fecha_cierre ASC LIMIT 5");
$stmtC->execute();
$proxCierres = $stmtC->fetchAll();

$pageTitle  = 'Panel de Financiamiento';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root {
    --fin-primary : #064e3b;
    --fin-dark    : #022c22;
    --fin-accent  : #B19A6D;
    --fin-soft    : rgba(6,78,59,.05);
    --fin-border  : rgba(6,78,59,.15);
}

.hero-fin {
    background: linear-gradient(135deg, var(--fin-primary) 0%, var(--fin-dark) 100%);
    border-radius: 20px;
    padding: 44px 52px;
    margin-bottom: 30px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 20px 40px rgba(6,78,59,.25);
    position: relative;
    overflow: hidden;
}
.hero-fin::after {
    content: '';
    position: absolute;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(177,154,109,.12) 0%, transparent 70%);
    top: -80px; right: -60px; border-radius: 50%;
}
.hero-fin h1 { font-size: 2.6rem; font-weight: 800; color: var(--fin-accent); margin: 0 0 10px; }
.hero-fin p  { margin: 0; opacity: .9; font-size: 1.05rem; max-width: 560px; }

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 20px;
    margin-bottom: 34px;
}
.kpi-card {
    background: #fff; border-radius: 18px; padding: 26px 24px;
    border: 1px solid #e2e8f0; display: flex; align-items: center;
    gap: 18px; transition: all .25s; text-decoration: none; color: inherit;
}
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(6,78,59,.1); border-color: var(--fin-accent); }
.kpi-icon { width:60px;height:60px;display:flex;align-items:center;justify-content:center;border-radius:14px;font-size:1.7rem;flex-shrink:0; }
.kpi-val  { font-size: 2.4rem; font-weight: 800; color: var(--fin-primary); line-height: 1; }
.kpi-lbl  { font-size: .9rem; color: #64748b; margin-top: 4px; font-weight: 500; }

.pres-bar-wrap { height: 10px; background: #e2e8f0; border-radius: 10px; margin-top: 6px; overflow: hidden; }
.pres-bar      { height: 100%; border-radius: 10px; background: linear-gradient(90deg, var(--fin-primary), #059669); transition: width .8s; }

.fin-section-title { font-size:1.25rem;font-weight:700;color:var(--fin-primary);display:flex;align-items:center;gap:10px;margin-bottom:16px; }
.fin-section-title i { color:var(--fin-accent); }

.quick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
    gap: 14px; margin-bottom: 36px;
}
.quick-btn {
    display:flex;flex-direction:column;align-items:center;gap:10px;
    padding:22px 16px;background:#fff;border:1px solid #e2e8f0;
    border-radius:14px;font-size:.88rem;font-weight:600;
    color:var(--fin-primary);text-decoration:none;text-align:center;transition:all .2s;
}
.quick-btn i { font-size:1.6rem;color:var(--fin-accent); }
.quick-btn:hover { background:var(--fin-primary);color:#fff;border-color:var(--fin-primary); }
.quick-btn:hover i { color:var(--fin-accent); }

.conv-table { width:100%;border-collapse:collapse; }
.conv-table th { text-align:left;padding:12px 18px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0; }
.conv-table td { padding:14px 18px;border-bottom:1px solid #f1f5f9;font-size:.88rem;color:#334155; }
.conv-table tr:last-child td { border-bottom:none; }
.conv-table tr:hover td { background:#f8fafc; }
.badge-pronto  { background:#fef3c7;color:#d97706;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600; }
.badge-vigente { background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600; }
</style>

<!-- HERO -->
<div class="hero-fin">
    <div>
        <h1>Departamento de Financiamiento</h1>
        <p>Gestión de convocatorias, becas institucionales, presupuesto y control de recursos del COMECyT.</p>
    </div>
    <i class="fa-solid fa-sack-dollar" style="font-size:6rem;opacity:.12;color:var(--fin-accent);flex-shrink:0;"></i>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <a href="convocatorias.php" class="kpi-card">
        <div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="fa-solid fa-bullhorn"></i></div>
        <div><div class="kpi-val"><?= $convActivas ?></div><div class="kpi-lbl">Convocatorias Activas</div></div>
    </a>
    <a href="becas.php" class="kpi-card">
        <div class="kpi-icon" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-graduation-cap"></i></div>
        <div><div class="kpi-val"><?= $becasVig ?></div><div class="kpi-lbl">Becas Pendientes de Pago</div></div>
    </a>
    <a href="agenda.php" class="kpi-card">
        <div class="kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-list-check"></i></div>
        <div><div class="kpi-val"><?= $tareasActivas ?></div><div class="kpi-lbl">Tareas en Progreso</div></div>
    </a>
    <a href="presupuesto.php" class="kpi-card">
        <div class="kpi-icon" style="background:var(--fin-soft);color:var(--fin-primary);"><i class="fa-solid fa-chart-pie"></i></div>
        <div>
            <div class="kpi-val"><?= $pctPres ?>%</div>
            <div class="kpi-lbl">Presupuesto Ejercido <?= $anioActual ?></div>
            <div class="pres-bar-wrap"><div class="pres-bar" style="width:<?= min($pctPres,100) ?>%;"></div></div>
        </div>
    </a>
    <div class="kpi-card" style="cursor:default;">
        <div class="kpi-icon" style="background:var(--fin-soft);color:var(--fin-primary);"><i class="fa-solid fa-users"></i></div>
        <div><div class="kpi-val"><?= $totalPersonal ?></div><div class="kpi-lbl">Personal del Área</div></div>
    </div>
</div>

<!-- Accesos Rápidos -->
<h2 class="fin-section-title"><i class="fa-solid fa-bolt"></i> Accesos Rápidos</h2>
<div class="quick-grid">
    <a href="agenda.php"         class="quick-btn"><i class="fa-solid fa-calendar-week"></i>Agenda y Kanban</a>
    <a href="personal.php"       class="quick-btn"><i class="fa-solid fa-id-card"></i>Personal</a>
    <a href="convocatorias.php"  class="quick-btn"><i class="fa-solid fa-bullhorn"></i>Convocatorias</a>
    <a href="becas.php"          class="quick-btn"><i class="fa-solid fa-graduation-cap"></i>Becas y Apoyos</a>
    <a href="presupuesto.php"    class="quick-btn"><i class="fa-solid fa-chart-pie"></i>Presupuesto</a>
    <a href="reportes.php"       class="quick-btn"><i class="fa-solid fa-file-chart-column"></i>Reportes</a>
</div>

<!-- Próximos Cierres -->
<h2 class="fin-section-title"><i class="fa-solid fa-clock"></i> Próximos Cierres de Convocatoria</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
    <?php if (empty($proxCierres)): ?>
        <div style="padding:50px;text-align:center;color:#94a3b8;">
            <i class="fa-solid fa-circle-check" style="font-size:2.5rem;opacity:.35;margin-bottom:12px;display:block;"></i>
            Sin convocatorias próximas a cerrar.
        </div>
    <?php else: ?>
    <table class="conv-table">
        <thead><tr><th>Convocatoria</th><th>Dependencia</th><th>Cierre</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($proxCierres as $c):
            $dias = (int)(new DateTime($c['fecha_cierre']))->diff(new DateTime())->format('%r%a');
            $cls = $dias <= 15 ? 'badge-pronto' : 'badge-vigente';
            $lbl = $dias <= 15 ? "Cierra en $dias días" : 'Vigente';
        ?>
        <tr>
            <td style="font-weight:600;color:#0f172a;"><?= esc($c['titulo']) ?></td>
            <td><?= esc($c['dependencia'] ?: '—') ?></td>
            <td><?= date('d/m/Y', strtotime($c['fecha_cierre'])) ?></td>
            <td><span class="<?= $cls ?>"><?= $lbl ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>