<?php
/**
 * COMECyT — Cabecera del Portal de Servicio Social
 *
 * Variables esperadas antes de incluir:
 *   $pageTitle  string  Título de la página
 *   $activeMenu string  Clave: 'kanban' | 'asistencia' | 'companeros'
 */
if (!isset($pageTitle))  $pageTitle  = 'Portal Servicio Social';
if (!isset($activeMenu)) $activeMenu = '';

$ssNombre = getNombreSS();
$ssId     = (int) ($_SESSION['ss_id'] ?? 0);
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
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="layout-admin" id="bodyRoot">
<?php require_once __DIR__ . '/loader.php'; ?>

<!-- Sidebar SS -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">
            <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT">
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu Servicio Social">
        <div class="nav-group">
            <span class="nav-group-label">Mi Espacio</span>
            <a href="<?= BASE_URL ?>servicio_social/dashboard.php"
               class="nav-link <?= $activeMenu === 'kanban' ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check nav-icon"></i>
                <span>Mis Tareas</span>
            </a>
            <a href="<?= BASE_URL ?>servicio_social/dashboard.php?vista=asistencia"
               class="nav-link <?= $activeMenu === 'asistencia' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock nav-icon"></i>
                <span>Asistencia</span>
            </a>
            <a href="<?= BASE_URL ?>servicio_social/dashboard.php?vista=companeros"
               class="nav-link <?= $activeMenu === 'companeros' ? 'active' : '' ?>">
                <i class="fa-solid fa-users nav-icon"></i>
                <span>Mis Compañeros</span>
            </a>
        </div>
    </nav>

    <?php require_once __DIR__ . '/sidebar_footer.php'; ?>
</aside>

<!-- Overlay móvil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar" id="topbar">
        <button class="menu-toggle" id="menuToggle" aria-label="Abrir menu" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title">
            <h1 style="font-size: 1.125rem; font-weight: 600; flex: 1;"><?= esc($pageTitle) ?> <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 400; margin-left: 10px;">Portal Servicio Social</span></h1>
        </div>
        <div class="topbar-actions">
            <!-- El componente Universal Notification Bell se inyectará aquí automáticamente -->
            
            <!-- Botón Chat -->
            <button class="topbar-btn" id="chatToggleBtn" onclick="toggleChat()" title="Chat del equipo"
                    style="position:relative; border:none; cursor:pointer; background:none; color:var(--text-muted); padding:5px 8px; font-size:1.1rem;">
                <i class="fa-solid fa-comments"></i>
                <span id="chatBadge" style="display:none; position:absolute; top:-5px; right:-5px;
                      min-width:16px; height:16px; border-radius:999px; background:#ef4444;
                      color:#fff; font-size:0.6rem; font-weight:700;
                      align-items:center; justify-content:center; padding:0 3px;
                      border:2px solid var(--bg-card,#fff);">0</span>
            </button>

            <span style="font-size:0.85rem; color:var(--text-muted); display:flex; align-items:center; gap:6px; background:var(--bg-hover); padding:5px 12px; border-radius:20px; font-weight:500;">
                <i class="fa-solid fa-circle" style="color:#22c55e; font-size:0.55rem;"></i>
                <?= esc($ssNombre) ?>
            </span>

            <?php require_once __DIR__ . '/chat_widget.php'; ?>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="page-content" id="mainContent">

<script>
// -? Sidebar Integrado Móvil ---------------------------------------?
document.addEventListener('DOMContentLoaded', () => {
    const btnMenu = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        const active = sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        btnMenu.setAttribute('aria-expanded', active);
    }
    
    if (btnMenu)   btnMenu.addEventListener('click', toggleSidebar);
    if (overlay)   overlay.addEventListener('click', toggleSidebar);

    // -? Auto-abrir chat si viene de notificación ----------------
    if (new URLSearchParams(window.location.search).get('openChat') === '1') {
        setTimeout(() => { if (typeof toggleChat === 'function') toggleChat(); }, 500);
    }
});
</script>
<script src="<?= BASE_URL ?>assets/js/NotificationBell.js?v=<?= time() ?>"></script>
