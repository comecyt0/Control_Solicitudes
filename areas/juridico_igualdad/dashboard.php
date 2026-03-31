<?php
/**
 * COMECyT — Panel Jurídico Administrativo
 * Dashboard Ejecutivo del Área
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo     = getConnection();
$cveArea = 17; // Dirección Jurídico-Administrativa

// KPIs
$totalContratos  = (int) $pdo->query("SELECT COUNT(*) FROM ja_contratos WHERE estatus = 'activo'")->fetchColumn();
$tareasActivas   = (int) $pdo->query("SELECT COUNT(*) FROM sb_kanban_tareas WHERE cve_area = $cveArea AND estatus != 'completada'")->fetchColumn();
$casosAbiertos   = (int) $pdo->query("SELECT COUNT(*) FROM ja_casos_igualdad WHERE estatus NOT IN ('resuelto','cerrado')")->fetchColumn();
$totalPersonal   = (int) $pdo->query("SELECT COUNT(*) FROM cat_personal WHERE cve_area = $cveArea AND activo = TRUE")->fetchColumn();
$adquisiciones   = (int) $pdo->query("SELECT COUNT(*) FROM ja_adquisiciones WHERE estatus = 'en_proceso'")->fetchColumn();

// Próximos vencimientos de contratos
$stmtV = $pdo->prepare(
    "SELECT titulo, contraparte, fecha_fin FROM ja_contratos WHERE estatus = 'activo' AND fecha_fin >= CURRENT_DATE ORDER BY fecha_fin ASC LIMIT 5"
);
$stmtV->execute();
$proxVencimientos = $stmtV->fetchAll();

$pageTitle  = 'Panel Jurídico Administrativo';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root {
    --ja-primary : #1e3a5f;
    --ja-dark    : #142845;
    --ja-accent  : #B19A6D;
    --ja-soft    : rgba(30, 58, 95, 0.05);
    --ja-border  : rgba(30, 58, 95, 0.15);
}

.hero-ja {
    background: linear-gradient(135deg, var(--ja-primary) 0%, var(--ja-dark) 100%);
    border-radius: 20px;
    padding: 44px 52px;
    margin-bottom: 30px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 20px 40px rgba(30, 58, 95, .25);
    position: relative;
    overflow: hidden;
}

.hero-ja::after {
    content: '';
    position: absolute;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(177,154,109,.12) 0%, transparent 70%);
    top: -80px; right: -60px;
    border-radius: 50%;
}

.hero-ja h1 { font-size: 2.6rem; font-weight: 800; color: var(--ja-accent); margin: 0 0 10px; }
.hero-ja p  { margin: 0; opacity: .9; font-size: 1.05rem; max-width: 560px; }

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 34px;
}

.kpi-card {
    background: #fff;
    border-radius: 18px;
    padding: 26px 24px;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all .25s;
    text-decoration: none;
    color: inherit;
}

.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(30,58,95,.1);
    border-color: var(--ja-accent);
}

.kpi-icon {
    width: 60px; height: 60px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 14px;
    font-size: 1.7rem;
    flex-shrink: 0;
}

.kpi-val  { font-size: 2.4rem; font-weight: 800; color: var(--ja-primary); line-height: 1; }
.kpi-lbl  { font-size: .9rem; color: #64748b; margin-top: 4px; font-weight: 500; }

.ja-section-title {
    font-size: 1.25rem; font-weight: 700;
    color: var(--ja-primary);
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 16px;
}
.ja-section-title i { color: var(--ja-accent); }

.quick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 36px;
}

.quick-btn {
    display: flex; flex-direction: column;
    align-items: center; gap: 10px;
    padding: 22px 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    font-size: .88rem;
    font-weight: 600;
    color: var(--ja-primary);
    text-decoration: none;
    transition: all .2s;
    text-align: center;
}

.quick-btn i { font-size: 1.6rem; color: var(--ja-accent); }
.quick-btn:hover { background: var(--ja-primary); color: #fff; border-color: var(--ja-primary); }
.quick-btn:hover i { color: var(--ja-accent); }

.venc-table { width: 100%; border-collapse: collapse; }
.venc-table th { text-align: left; padding: 12px 18px; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
.venc-table td { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; font-size: .88rem; color: #334155; }
.venc-table tr:last-child td { border-bottom: none; }
.venc-table tr:hover td { background: #f8fafc; }

.badge-estatus {
    padding: 3px 10px; border-radius: 20px;
    font-size: .75rem; font-weight: 600;
}
.badge-pronto  { background: #fef3c7; color: #d97706; }
.badge-vigente { background: #dcfce7; color: #16a34a; }
.badge-vencido { background: #fee2e2; color: #dc2626; }
</style>

<!-- HERO -->
<div class="hero-ja">
    <div>
        <h1>Dirección Jurídico Administrativa</h1>
        <p>Centro de gestión jurídica, administrativa y de igualdad del COMECyT. Controla contratos, normatividad, adquisiciones y protocolos de género.</p>
    </div>
    <i class="fa-solid fa-scale-balanced" style="font-size:6rem;opacity:.12;color:var(--ja-accent);flex-shrink:0;"></i>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <a href="contratos.php" class="kpi-card">
        <div class="kpi-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fa-solid fa-file-signature"></i></div>
        <div>
            <div class="kpi-val"><?= $totalContratos ?></div>
            <div class="kpi-lbl">Contratos Activos</div>
        </div>
    </a>
    <a href="agenda.php" class="kpi-card">
        <div class="kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-list-check"></i></div>
        <div>
            <div class="kpi-val"><?= $tareasActivas ?></div>
            <div class="kpi-lbl">Tareas en Progreso</div>
        </div>
    </a>
    <a href="igualdad.php" class="kpi-card">
        <div class="kpi-icon" style="background:#fce7f3;color:#db2777;"><i class="fa-solid fa-venus-mars"></i></div>
        <div>
            <div class="kpi-val"><?= $casosAbiertos ?></div>
            <div class="kpi-lbl">Casos Igualdad Abiertos</div>
        </div>
    </a>
    <a href="adquisiciones.php" class="kpi-card">
        <div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="fa-solid fa-cart-shopping"></i></div>
        <div>
            <div class="kpi-val"><?= $adquisiciones ?></div>
            <div class="kpi-lbl">Adquisiciones en Proceso</div>
        </div>
    </a>
    <div class="kpi-card" style="cursor:default;">
        <div class="kpi-icon" style="background:var(--ja-soft);color:var(--ja-primary);"><i class="fa-solid fa-users"></i></div>
        <div>
            <div class="kpi-val"><?= $totalPersonal ?></div>
            <div class="kpi-lbl">Personal del Área</div>
        </div>
    </div>
</div>

<!-- Accesos Rápidos -->
<h2 class="ja-section-title"><i class="fa-solid fa-bolt"></i> Accesos Rápidos</h2>
<div class="quick-grid">
    <a href="agenda.php"        class="quick-btn"><i class="fa-solid fa-calendar-week"></i>Agenda y Kanban</a>
    <a href="personal.php"      class="quick-btn"><i class="fa-solid fa-id-card"></i>Personal</a>
    <a href="contratos.php"     class="quick-btn"><i class="fa-solid fa-file-signature"></i>Contratos</a>
    <a href="normatividad.php"  class="quick-btn"><i class="fa-solid fa-scale-balanced"></i>Normatividad</a>
    <a href="igualdad.php"      class="quick-btn"><i class="fa-solid fa-venus-mars"></i>Igualdad</a>
    <a href="adquisiciones.php" class="quick-btn"><i class="fa-solid fa-cart-shopping"></i>Adquisiciones</a>
</div>

<!-- Tabla de Próximos Vencimientos -->
<h2 class="ja-section-title"><i class="fa-solid fa-clock"></i> Próximos Vencimientos de Contratos</h2>
<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
    <?php if (empty($proxVencimientos)): ?>
        <div style="padding:50px;text-align:center;color:#94a3b8;">
            <i class="fa-solid fa-circle-check" style="font-size:2.5rem;opacity:.35;margin-bottom:12px;display:block;"></i>
            Sin contratos próximos a vencer.
        </div>
    <?php else: ?>
    <table class="venc-table">
        <thead><tr><th>Contrato</th><th>Contraparte</th><th>Fecha Fin</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($proxVencimientos as $v):
            $dias = (int) (new DateTime($v['fecha_fin']))->diff(new DateTime())->format('%r%a');
            $cls = $dias <= 30 ? 'badge-pronto' : 'badge-vigente';
            $lbl = $dias <= 30 ? "Vence en $dias días" : 'Vigente';
        ?>
            <tr>
                <td style="font-weight:600;color:#0f172a;"><?= esc($v['titulo']) ?></td>
                <td><?= esc($v['contraparte'] ?: '—') ?></td>
                <td><?= date('d/m/Y', strtotime($v['fecha_fin'])) ?></td>
                <td><span class="badge-estatus <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>