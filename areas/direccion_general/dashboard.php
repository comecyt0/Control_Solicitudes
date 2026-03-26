<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Dirección General — Dashboard Ejecutivo
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// Estadísticas Globales para Dirección
$stmtS = $pdo->query("SELECT COUNT(*) FROM sb_calendario_solicitudes WHERE estatus = 'pendiente'");
$pendientesGlobal = (int) $stmtS->fetchColumn();

$stmtE = $pdo->query("SELECT COUNT(*) FROM eventos WHERE fecha_inicio >= CURRENT_DATE");
$proximosEventos = (int) $stmtE->fetchColumn();

// Tareas del Área (DG)
$stmtT = $pdo->query("SELECT COUNT(*) FROM df_tareas WHERE cve_area = 4 AND estatus != 'completada'");
$tareasPendientes = (int) $stmtT->fetchColumn();

$pageTitle  = 'Dashboard de Dirección General';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root {
    --dg-primary: #662331;
    --dg-accent: #B19A6D;
    --dg-bg-soft: rgba(102, 35, 49, 0.03);
}

.hero-dg {
    background: linear-gradient(135deg, var(--dg-primary) 0%, #4d1a25 100%);
    border-radius: 20px;
    padding: 50px;
    margin-bottom: 30px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 20px 40px rgba(102, 35, 49, 0.2);
    position: relative;
    overflow: hidden;
}

.hero-dg::after {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(177, 154, 109, 0.1) 0%, transparent 70%);
    top: -50px; right: -50px;
    border-radius: 50%;
}

.hero-dg-content h1 {
    font-size: 2.8rem;
    font-weight: 800;
    margin-bottom: 10px;
    color: var(--dg-accent);
}

.hero-dg-content p {
    font-size: 1.2rem;
    opacity: 0.9;
    max-width: 600px;
}

.dg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.dg-card {
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

.dg-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.05);
    border-color: var(--dg-accent);
}

.dg-icon {
    width: 70px; height: 70px;
    border-radius: 15px;
    background: var(--dg-bg-soft);
    color: var(--dg-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.dg-info h3 {
    font-size: 2.4rem;
    font-weight: 800;
    color: var(--dg-primary);
    line-height: 1;
}

.dg-info p {
    font-size: 1rem;
    color: #64748b;
    font-weight: 500;
    margin-top: 5px;
}

.dg-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.dg-section-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--dg-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.dg-section-title i { color: var(--dg-accent); }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.action-btn {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 20px;
    border-radius: 14px;
    text-align: center;
    font-weight: 600;
    color: var(--dg-primary);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.action-btn i { font-size: 1.5rem; color: var(--dg-accent); }

.action-btn:hover {
    background: var(--dg-primary);
    color: white;
    border-color: var(--dg-primary);
}

.action-btn:hover i { color: white; }
</style>

<div class="hero-dg">
    <div class="hero-dg-content">
        <h1>Centro de Mando Dirección</h1>
        <p>Visión estratégica y operativa del Consejo. Gestione las solicitudes institucionales y supervise el cumplimiento de objetivos.</p>
    </div>
    <div style="font-size: 7rem; opacity: 0.15; color: var(--dg-accent);">
        <i class="fa-solid fa-chess-knight"></i>
    </div>
</div>

<div class="dg-grid">
    <a href="gestion_solicitudes.php" class="dg-card">
        <div class="dg-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="dg-info">
            <h3><?= $pendientesGlobal ?></h3>
            <p>Solicitudes de Espacio Pendientes</p>
        </div>
    </a>
    <a href="calendario_editorial.php" class="dg-card">
        <div class="dg-icon" style="color: #0ea5e9; background: #e0f2fe;"><i class="fa-solid fa-clipboard-list"></i></div>
        <div class="dg-info">
            <h3><?= $tareasPendientes ?></h3>
            <p>Tareas y Compromisos del Área</p>
        </div>
    </a>
    <div class="dg-card">
        <div class="dg-icon" style="color: #16a34a; background: #dcfce7;"><i class="fa-solid fa-earth-americas"></i></div>
        <div class="dg-info">
            <h3><?= $proximosEventos ?></h3>
            <p>Eventos Activos en el Sistema</p>
        </div>
    </div>
</div>

<div class="dg-section-header">
    <h2 class="dg-section-title"><i class="fa-solid fa-bolt"></i> Acciones Estratégicas</h2>
</div>

<div class="quick-actions">
    <a href="gestion_solicitudes.php" class="action-btn">
        <i class="fa-solid fa-user-check"></i>
        Aprobar Solicitudes
    </a>
    <a href="calendario_editorial.php" class="action-btn">
        <i class="fa-solid fa-calendar-days"></i>
        Agenda Direccional
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>