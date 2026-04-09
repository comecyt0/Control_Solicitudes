<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Departamento Administrativo — Centro de Control
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$cve_area_adm = 18;

// KPI 1: Personal del Área
$stmtP = $pdo->prepare("SELECT COUNT(*) FROM cat_personal WHERE cve_area = ? AND activo = TRUE");
$stmtP->execute([$cve_area_adm]);
$totalPersonal = (int) $stmtP->fetchColumn();

// KPI 2: Tareas Pendientes (Kanban)
$stmtT = $pdo->prepare("SELECT COUNT(*) FROM sb_kanban_tareas WHERE cve_area = ? AND estatus != 'completada'");
$stmtT->execute([$cve_area_adm]);
$tareasPendientes = (int) $stmtT->fetchColumn();

// KPI 3: Documentos en Archivo
$stmtRepo = $pdo->query("SELECT COUNT(*) FROM adm_repo_archivos");
$totalArchivos = (int) $stmtRepo->fetchColumn();

$pageTitle  = 'Control Administrativo';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root {
    --adm-primary: #334155;
    --adm-accent: #94a3b8;
    --adm-bg-soft: rgba(51, 65, 85, 0.04);
}

.hero-adm {
    background: linear-gradient(135deg, var(--adm-primary) 0%, #1e293b 100%);
    border-radius: 20px;
    padding: 50px;
    margin-bottom: 30px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 20px 40px rgba(51, 65, 85, 0.2);
    position: relative;
    overflow: hidden;
}

.hero-adm::after {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(148, 163, 184, 0.1) 0%, transparent 70%);
    top: -50px; right: -50px;
    border-radius: 50%;
}

.hero-adm-content h1 {
    font-size: 2.8rem;
    font-weight: 800;
    margin-bottom: 10px;
    color: var(--adm-accent);
}

.hero-adm-content p {
    font-size: 1.2rem;
    opacity: 0.9;
    max-width: 600px;
}

.adm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.adm-card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 20px;
    text-decoration: none;
    color: inherit;
}

.adm-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.05);
    border-color: var(--adm-accent);
}

.adm-icon {
    width: 65px; height: 65px;
    border-radius: 15px;
    background: var(--adm-bg-soft);
    color: var(--adm-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.adm-info h3 {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--adm-primary);
    line-height: 1;
}

.adm-info p {
    font-size: 0.95rem;
    color: #64748b;
    font-weight: 500;
    margin-top: 5px;
}

.section-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--adm-primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}

.action-card {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 24px;
    border-radius: 16px;
    text-align: center;
    color: var(--adm-primary);
    transition: all 0.2s ease;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.action-card i { font-size: 1.6rem; color: var(--adm-accent); }

.action-card:hover {
    background: var(--adm-primary);
    color: white;
}

.action-card:hover i { color: white; }
</style>

<div class="hero-adm">
    <div class="hero-adm-content">
        <h1>Centro de Control Administrativo</h1>
        <p>Gestión eficiente de recursos humanos, materiales y flujos de trabajo internos del Consejo.</p>
    </div>
    <div style="font-size: 7rem; opacity: 0.15; color: var(--adm-accent);">
        <i class="fa-solid fa-briefcase"></i>
    </div>
</div>

<div class="adm-grid">
    <div class="adm-card">
        <div class="adm-icon"><i class="fa-solid fa-users"></i></div>
        <div class="adm-info">
            <h3><?= $totalPersonal ?></h3>
            <p>Personal del Área</p>
        </div>
    </div>
    <a href="calendario_editorial.php#kanban" class="adm-card">
        <div class="adm-icon" style="color: #0ea5e9; background: #e0f2fe;"><i class="fa-solid fa-list-check"></i></div>
        <div class="adm-info">
            <h3><?= $tareasPendientes ?></h3>
            <p>Compromisos Pendientes</p>
        </div>
    </a>
    <a href="repositorio.php" class="adm-card">
        <div class="adm-icon" style="color: #10b981; background: #dcfce7;"><i class="fa-solid fa-file-invoice"></i></div>
        <div class="adm-info">
            <h3><?= $totalArchivos ?></h3>
            <p>Archivos en Repositorio</p>
        </div>
    </a>
</div>

<h2 class="section-title"><i class="fa-solid fa-rocket" style="color:var(--adm-accent);"></i> Acciones Rápidas</h2>

<div class="quick-actions-grid">
    <a href="calendario_editorial.php" class="action-card">
        <i class="fa-solid fa-calendar-day"></i>
        <span>Agenda Operativa</span>
    </a>
    <a href="repositorio.php" class="action-card">
        <i class="fa-solid fa-folder-open"></i>
        <span>Archivo Digital</span>
    </a>
    <a href="../../admin/personal.php?filtro_area=18" class="action-card">
        <i class="fa-solid fa-user-gear"></i>
        <span>Gestión de Personal</span>
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>