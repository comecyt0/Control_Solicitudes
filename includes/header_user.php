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
    <title><?= esc($pageTitle) ?> — COMECyT Control de Solicitudes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="layout-admin"> <!-- Reutilizando layout-admin para aprovechar la cuadrícula Sidebar/Main -->
<?php require_once __DIR__ . '/loader.php'; ?>

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
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar" style="background: var(--color-info);">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="admin-details">
                <span class="admin-name" title="<?= esc($userNombre) ?>"><?= esc($userNombre) ?></span>
                <span class="admin-role" title="<?= esc($userArea) ?>"><?= esc($userArea) ?></span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>admin/logout.php" class="btn-logout" title="Cerrar sesion" style="color: var(--color-danger); border-color: transparent;">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</aside>

<!-- Overlay para sidebar movil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php require_once __DIR__ . '/help_widget.php'; ?>

<!-- Contenido principal -->
<div class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar">
        <button class="menu-toggle" id="menuToggle" aria-label="Abrir menu" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title">
            <h1><?= esc($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
            <?php if (!empty($_SESSION['admin_id'])): ?>
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-shapes"></i> Panel de Admin
                </a>
            <?php endif; ?>
        </div>
    </header>
    
    <main class="content-area">
