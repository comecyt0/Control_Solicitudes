<?php
/**
 * COMECyT — Hub de Jurisdicción (Fase 3)
 * Panel centralizado para Directores de Área y Dirección General.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$adminEmail = $_SESSION['admin_email'] ?? '';
$adminRol   = $_SESSION['admin_rol']   ?? '';

// 1. Identificar al Líder y su Jurisdicción
$stmtL = $pdo->prepare("
    SELECT p.cve_personal, p.nombre, p.rol_jefatura, a.adscipcion, a.cve_area as area_base_id
    FROM cat_personal p
    LEFT JOIN cat_areas a ON p.cve_area = a.cve_area
    WHERE p.correo_institucional = ? OR p.correo_personal = ?
    LIMIT 1
");
$stmtL->execute([$adminEmail, $adminEmail]);
$leader = $stmtL->fetch(PDO::FETCH_ASSOC);

if (!$leader || (empty($leader['rol_jefatura']) && $adminRol !== 'superadmin')) {
    // Si no es un líder identificado ni superadmin, no tiene nada que hacer aquí
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

$isDG = (strtoupper($leader['rol_jefatura'] ?? '') === 'DIRECTOR GENERAL' || $adminRol === 'superadmin');
$adscripcionLeader = $leader['adscipcion'] ?? '';

// 2. Obtener Áreas bajo su Jurisdicción
if ($isDG) {
    // El Director General ve TODO (excepto áreas técnicas si se desea, por ahora todo)
    $stmtA = $pdo->query("SELECT * FROM cat_areas ORDER BY cve_area ASC");
    $jurisdiccion = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    $tituloHub = "Jurisdicción Global — Dirección General";
} else {
    // Directores de Área ven su propia adscripción
    $stmtA = $pdo->prepare("SELECT * FROM cat_areas WHERE adscipcion = ? ORDER BY cve_area ASC");
    $stmtA->execute([$adscripcionLeader]);
    $jurisdiccion = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    $tituloHub = "Mi Jurisdicción — " . ($adscripcionLeader ?: 'Departamento');
}

$pageTitle  = 'Hub de Jurisdicción';
$activeMenu = 'jurisdiccion';

require_once __DIR__ . '/../includes/header_admin.php';
?>

<style>
.juris-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.hero-juris {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 24px;
    padding: 60px 40px;
    margin-bottom: 40px;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 45px rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hero-juris::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
}

.hero-content h1 {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 10px;
    background: linear-gradient(to right, #fff, #cbd5e1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.hero-content p {
    font-size: 1.1rem;
    opacity: 0.8;
}

.juris-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
}

.area-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 30px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    color: #1e293b;
    position: relative;
    overflow: hidden;
}

.dark-mode .area-card {
    background: rgba(30, 41, 59, 0.4);
    border-color: rgba(255, 255, 255, 0.05);
    color: #f1f5f9;
}

.area-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 50px rgba(0,0,0,0.1);
    background: rgba(255, 255, 255, 0.95);
}

.dark-mode .area-card:hover {
    background: rgba(30, 41, 59, 0.8);
}

.area-badge {
    position: absolute;
    top: 20px; right: 20px;
    background: #e2e8f0;
    padding: 5px 12px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
}

.area-icon {
    width: 60px; height: 60px;
    background: rgba(51, 65, 85, 0.1);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 20px;
    color: #334155;
}

.dark-mode .area-icon {
    background: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
}

.area-info h3 {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 8px;
    line-height: 1.2;
}

.area-info p {
    font-size: 0.9rem;
    opacity: 0.7;
    margin-bottom: 25px;
    min-height: 2.7rem;
}

.btn-enter {
    background: #334155;
    color: white;
    text-decoration: none;
    padding: 12px 20px;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-enter:hover {
    background: #1e293b;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 100px 20px;
}

.empty-state i {
    font-size: 5rem;
    color: #cbd5e1;
    margin-bottom: 20px;
}

</style>

<div class="juris-container">
    <div class="hero-juris">
        <div class="hero-content">
            <h1><?= esc($tituloHub) ?></h1>
            <p>Supervisión y acceso directo a los paneles departamentales bajo su cargo.</p>
        </div>
        <div style="font-size: 6rem; opacity: 0.1;">
            <i class="fa-solid fa-sitemap"></i>
        </div>
    </div>

    <?php if (empty($jurisdiccion)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <h3>No se encontraron áreas asociadas</h3>
            <p>Verifique los ajustes de su perfil o contacte a sistemas.</p>
        </div>
    <?php else: ?>
        <div class="juris-grid">
            <?php foreach ($jurisdiccion as $area): 
                // Mapa de iconos por área (pueden ser dinámicos luego)
                $icon = 'fa-folder';
                $id = (int)$area['cve_area'];
                if ($id === 1) $icon = 'fa-microchip';
                elseif ($id === 2) $icon = 'fa-user-tie';
                elseif ($id === 4) $icon = 'fa-landmark';
                elseif ($id === 16) $icon = 'fa-bullhorn';
                elseif ($id === 17) $icon = 'fa-hand-holding-dollar';
                elseif ($id === 18) $icon = 'fa-briefcase';
                elseif ($id === 19) $icon = 'fa-scale-balanced';
                elseif ($id === 20) $icon = 'fa-box-archive';
            ?>
                <div class="area-card">
                    <span class="area-badge">ID: <?= $id ?></span>
                    <div class="area-icon">
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="area-info">
                        <h3><?= esc($area['des_area'] ?: $area['nom_area']) ?></h3>
                        <p><?= esc($area['adscipcion'] ?: 'Adscripción General') ?></p>
                    </div>
                    <a href="<?= BASE_URL ?>public/router.php?demo_area=<?= $id ?>" class="btn-enter">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Entrar al Panel
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
