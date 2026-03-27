<?php
/**
 * COMECyT Control de Solicitudes
 * Cabecera HTML del panel de Usuario Regular (Personal)
 *
 * Uso: require_once ROOT . '/includes/header_user.php';
 * Variables esperadas (definir antes de incluir):
 *   $pageTitle   string  Titulo de la pagina (para <title> y breadcrumb)
 *   $activeMenu  string  Clave del menu activo: 'nueva_solicitud'|'historial'|'equipos'
 */

if (!isset($pageTitle))  $pageTitle  = 'Panel de Usuario';
if (!isset($activeMenu)) $activeMenu = '';

$userNombre = $_SESSION['user_nombre'] ?? $_SESSION['admin_nombre'] ?? 'Usuario';
$userArea   = $_SESSION['user_area'] ?? $_SESSION['admin_area'] ?? 'General';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($pageTitle) ?> | COMECyT Intranet</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/MARCA.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/NotificationBell.css">
    <script>window.BASE_URL = "<?= BASE_URL ?>";</script>
    <script src="<?= BASE_URL ?>assets/js/ui-frames.js"></script>
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="layout-admin"> <!-- Reutilizando layout-admin para aprovechar la cuadrícula Sidebar/Main -->
<?php require_once __DIR__ . '/loader.php'; ?>

<?php if (empty($hideSidebar)): ?>
<!-- Sidebar Usuario -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">
            <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT">
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu principal">
        <div class="nav-group">
            <span class="nav-group-label">Acciones</span>
            <a href="<?= BASE_URL ?>public/index.php"
               class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-house nav-icon" style="color:var(--color-primary)"></i>
                <span>Inicio / Intranet</span>
            </a>
            <a href="<?= BASE_URL ?>public/nueva_solicitud.php"
               class="nav-link <?= $activeMenu === 'nueva_solicitud' ? 'active' : '' ?>">
                <i class="fa-solid fa-plus-circle nav-icon"></i>
                <span>Registrar Solicitud</span>
            </a>
        </div>

        <div class="nav-group">
            <span class="nav-group-label">Mi Cuenta</span>
            <a href="<?= BASE_URL ?>public/historial.php"
               class="nav-link <?= $activeMenu === 'historial' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock-rotate-left nav-icon"></i>
                <span>Historial de Solicitudes</span>
            </a>
            <a href="<?= BASE_URL ?>public/equipos_usuario.php"
               class="nav-link <?= $activeMenu === 'equipos' ? 'active' : '' ?>">
                <i class="fa-solid fa-laptop nav-icon"></i>
                <span>Mis Equipos Asignados</span>
            </a>
            <a href="<?= BASE_URL ?>public/calendario.php"
               class="nav-link <?= $activeMenu === 'calendario' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days nav-icon"></i>
                <span>Calendario Institucional</span>
            </a>
            <a href="<?= BASE_URL ?>public/calendario.php?solicitar=1"
               class="nav-link">
                <i class="fa-solid fa-calendar-plus nav-icon" style="color: #b45309;"></i>
                <span>Agendar Espacio</span>
            </a>
            <a href="<?= BASE_URL ?>public/galeria_institucional.php"
               class="nav-link <?= $activeMenu === 'galeria' ? 'active' : '' ?>">
                <i class="fa-solid fa-photo-film nav-icon"></i>
                <span>Galería Institucional</span>
            </a>
        </div>
    </nav>

    <?php require_once __DIR__ . '/sidebar_footer.php'; ?>
</aside>

<!-- Overlay para sidebar movil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php require_once __DIR__ . '/help_widget.php'; ?>
<?php endif; ?>

<!-- Contenido principal -->
<div class="main-wrapper" <?php if (!empty($hideSidebar)) echo 'style="margin-left: 0; width: 100%; transition: none;"'; ?>>
    <!-- Topbar -->
    <header class="topbar" <?php if (!empty($hideSidebar)) echo 'style="left: 0; width: 100%; padding-left: 24px; transition: none;"'; ?>>
        <?php if (empty($hideSidebar)): ?>
        <button class="menu-toggle" id="menuToggle" aria-label="Abrir menu" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <?php endif; ?>
        
        <div class="topbar-title" <?php if (!empty($hideSidebar)) echo 'style="display: flex; align-items: center;"'; ?>>
            <?php if (!empty($hideSidebar)): ?>
                <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT" style="height: 38px; margin-right: 15px; border-right: 1px solid #e2e8f0; padding-right: 15px;">
            <?php endif; ?>
            <h1 <?php if (!empty($hideSidebar)) echo 'style="margin:0; font-size:1.1rem;"'; ?>><?= esc($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
            <!-- El componente Universal Notification Bell se inyectará aquí automáticamente -->
            <?php 
                $cve_area_actual = (int)($_SESSION['user_cve_area'] ?? $_SESSION['admin_cve_area'] ?? 1);
                // Si el cve_area no está en sesión pero es admin, intentamos inferir o permitimos el botón estándar
                if (!empty($_SESSION['admin_id']) || $cve_area_actual > 1):
                    $labelPanel = !empty($_SESSION['admin_id']) ? 'Panel de Admin' : 'Mi Área';
                    $urlPanel   = !empty($_SESSION['admin_id']) && $cve_area_actual <= 1 ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'public/router.php';
            ?>
                <a href="<?= $urlPanel ?>" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-shapes"></i> <?= $labelPanel ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($hideSidebar)): ?>
                <div style="display: flex; gap: 8px;">
                    <a href="<?= BASE_URL ?>public/historial.php" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-user"></i> Mi Cuenta
                    </a>
                    <a href="<?= BASE_URL ?>admin/logout.php" class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #fca5a5; background: #fef2f2;" title="Cerrar sesion">
                        <i class="fa-solid fa-right-from-bracket"></i> Salir
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <main class="content-area" <?php if (!empty($hideSidebar)) echo 'style="max-width: 1400px; margin: 0 auto; padding-top: 100px;"'; ?>>
    <!-- El script de la campana se inyecta al final para asegurar que el DOM esté listo -->
    <script src="<?= BASE_URL ?>assets/js/NotificationBell.js?v=<?= time() ?>"></script>
