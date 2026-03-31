<?php
/**
 * COMECyT Control de Solicitudes
 * Cabecera HTML del panel de administracion
 *
 * Uso: require_once ROOT . '/includes/header_admin.php';
 * Variables esperadas (definir antes de incluir):
 *   $pageTitle   string  Titulo de la pagina (para <title> y breadcrumb)
 *   $activeMenu  string  Clave del menu activo: 'dashboard'|'solicitudes'|'pendientes'|'en_proceso'|'completadas'
 */

if (!isset($pageTitle))  $pageTitle  = 'Panel de Administracion';
if (!isset($activeMenu)) $activeMenu = '';

$adminNombre = getNombreAdmin();

// --- Cortafuegos de Ingeniería (Routing Firewall) Phase 4 ---
if (isset($_SESSION['area_slug_activa'])) {
    $script_path = $_SERVER['SCRIPT_NAME'];
    $slug_esperado = $_SESSION['area_slug_activa'];
    
    // Si la URL actual está dentro del directorio /areas/ (ej. /areas/archivo/dashboard.php)
    if (strpos($script_path, '/areas/') !== false) {
        // PERO NO contiene el slug exacto del usuario (ej. su slug es 'sistemas' o 'direccion_general')
        if (strpos($script_path, '/areas/' . $slug_esperado . '/') === false) {
            header("Location: " . BASE_URL . "public/router.php");
            exit;
        }

        // BLOQUEO DE ÁREAS EN DESARROLLO:
        // Si el área NO es Difusión (ya funcional) y el usuario está en /areas/
        // Solo puede ver el dashboard.php. Cualquier otro archivo redirecciona al dashboard.
        $areas_funcionales = ['sistemas', 'difusion', 'direccion_general', 'juridico_igualdad', 'financiamiento', 'apoyo_investigacion', 'formacion_rrhh', 'desarrollo_tecnologico', 'juridico'];
        if (!in_array($slug_esperado, $areas_funcionales)) {
            $current_file = basename($script_path);
            if ($current_file !== 'dashboard.php') {
                header("Location: dashboard.php");
                exit;
            }
        }

    } else if (strpos($script_path, '/admin/') !== false) {
        // O si intenta acceder al admin de sistemas puro pero su slug NO es sistemas
        if ($slug_esperado !== 'sistemas') {
            header("Location: " . BASE_URL . "public/router.php");
            exit;
        }
    }
}
// -------------------------------------------------------------

// Cargar preferencia de dark mode del admin
$darkMode = (int) ($_SESSION['admin_dark_mode'] ?? 0);
if (!isset($_SESSION['admin_dark_mode'])) {
    try {
        $pdoTmp = getConnection();
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        if ($adminId > 0) {
            $stmtDM = $pdoTmp->prepare('SELECT COALESCE(dark_mode,0) FROM administradores WHERE id = ?');
            $stmtDM->execute([$adminId]);
            $darkMode = (int) $stmtDM->fetchColumn();
            $_SESSION['admin_dark_mode'] = $darkMode;
        }
    } catch (Throwable $e) {
        $darkMode = 0;
    }
}

// -------------------------------------------------------------
// Notificaciones globales (Badges de sidebar)
// -------------------------------------------------------------
$countCalendario = 0;
try {
    $pdoHeader = getConnection();
    $stmtC = $pdoHeader->query("SELECT COUNT(*) FROM sb_calendario_solicitudes WHERE estatus = 'pendiente'");
    $countCalendario = (int) $stmtC->fetchColumn();
} catch (Throwable $e) {
    error_log("[Header] Fallo al contar solicitudes: " . $e->getMessage());
}

// Personalización de Chat por Área
$chat_area_label = 'Equipo TI';
if (isset($_SESSION['user_area'])) {
    $chat_area_label = 'Equipo de ' . $_SESSION['user_area'];
} elseif (isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] !== 'sistemas') {
    $chat_area_label = 'Administración';
}
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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_extra.css">
    <script src="<?= BASE_URL ?>assets/js/ui-frames.js?v=<?= time() ?>"></script>
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="layout-admin<?= $darkMode ? ' dark-mode' : '' ?>" id="bodyRoot">
<?php require_once __DIR__ . '/loader.php'; ?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">
            <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT">
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu principal">
        <?php $slug_menu = $_SESSION['area_slug_activa'] ?? 'sistemas'; ?>
        
        <div class="nav-group">
            <span class="nav-group-label">Principal</span>
            <a href="<?= BASE_URL ?><?= $slug_menu === 'sistemas' ? 'admin' : 'areas/'.$slug_menu ?>/dashboard.php"
               class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie nav-icon"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" onclick="toggleAsistenteIA(); return false;" class="nav-link">
                <i class="fa-solid fa-robot nav-icon"></i>
                <span>Asistente IA</span>
            </a>
        </div>

        <?php if ($slug_menu === 'sistemas'): ?>
        <!-- Menu Exclusivo Unidad de Sistemas -->
        <div class="nav-group">
            <span class="nav-group-label">Gestion</span>
            <a href="<?= BASE_URL ?>admin/solicitudes.php" class="nav-link <?= $activeMenu === 'solicitudes' ? 'active' : '' ?>">
                <i class="fa-solid fa-list-ul nav-icon"></i><span>Todas las Solicitudes</span>
            </a>
            <a href="<?= BASE_URL ?>admin/solicitudes.php?estatus=pendiente" class="nav-link <?= $activeMenu === 'pendientes' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock nav-icon"></i><span>Pendientes</span>
            </a>
            <a href="<?= BASE_URL ?>admin/solicitudes.php?estatus=en_proceso" class="nav-link <?= $activeMenu === 'en_proceso' ? 'active' : '' ?>">
                <i class="fa-solid fa-bolt nav-icon"></i><span>En Proceso</span>
            </a>
            <a href="<?= BASE_URL ?>admin/solicitudes.php?estatus=completada" class="nav-link <?= $activeMenu === 'completadas' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check nav-icon"></i><span>Completadas</span>
            </a>
            <a href="<?= BASE_URL ?>admin/reportes.php" class="nav-link <?= $activeMenu === 'reportes' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line nav-icon"></i><span>Reportes</span>
            </a>
        </div>
        <div class="nav-group">
            <span class="nav-group-label">Intranet</span>
            <a href="<?= BASE_URL ?>admin/anuncios.php" class="nav-link <?= $activeMenu === 'anuncios' ? 'active' : '' ?>">
                <i class="fa-solid fa-bullhorn nav-icon"></i><span>Gestión de Anuncios</span>
            </a>
            <a href="<?= BASE_URL ?>admin/calendario.php" class="nav-link <?= $activeMenu === 'calendario' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days nav-icon"></i><span>Calendario Global</span>
            </a>
            <a href="<?= BASE_URL ?>admin/login_alertas.php" class="nav-link <?= $activeMenu === 'login_alertas' ? 'active' : '' ?>">
                <i class="fa-solid fa-triangle-exclamation nav-icon"></i><span>Alertas de Login</span>
            </a>
        </div>
        <div class="nav-group">
            <span class="nav-group-label">Gestión ERP</span>
            <a href="<?= BASE_URL ?>admin/personal.php" class="nav-link <?= $activeMenu === 'personal' ? 'active' : '' ?>">
                <i class="fa-solid fa-address-card nav-icon"></i><span>Personal</span>
            </a>
            <a href="<?= BASE_URL ?>admin/equipos.php" class="nav-link <?= $activeMenu === 'equipos' ? 'active' : '' ?>">
                <i class="fa-solid fa-laptop-code nav-icon"></i><span>Control de Equipos</span>
            </a>
        </div>
        <div class="nav-group">
            <span class="nav-group-label">Servicio Social</span>
            <a href="<?= BASE_URL ?>admin/servicio_social.php" class="nav-link <?= $activeMenu === 'servicio_social' ? 'active' : '' ?>">
                <i class="fa-solid fa-graduation-cap nav-icon"></i><span>Servicio Social</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'difusion'): ?>
        <!-- Menu Exclusivo Difusión y Comunicación Social -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos de Difusión</span>
            <a href="<?= BASE_URL ?>areas/difusion/repositorio.php" class="nav-link <?= $activeMenu === 'repositorio' ? 'active' : '' ?>">
                <i class="fa-solid fa-photo-film nav-icon"></i><span>Repositorio Multimedia</span>
            </a>
            <a href="<?= BASE_URL ?>areas/difusion/anuncios.php" class="nav-link <?= $activeMenu === 'anuncios' ? 'active' : '' ?>">
                <i class="fa-solid fa-bullhorn nav-icon"></i><span>Publicador de Banners</span>
            </a>
            <a href="<?= BASE_URL ?>areas/difusion/calendario_editorial.php" class="nav-link <?= $activeMenu === 'calendario' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week nav-icon"></i><span>Calendario Editorial</span>
            </a>
            <a href="<?= BASE_URL ?>areas/difusion/login_alertas.php" class="nav-link <?= $activeMenu === 'login_alertas' ? 'active' : '' ?>">
                <i class="fa-solid fa-triangle-exclamation nav-icon"></i><span>Alertas de Login</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'direccion_general'): ?>
        <!-- Menu Exclusivo Dirección General -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos de Dirección</span>
            <a href="<?= BASE_URL ?>areas/direccion_general/gestion_solicitudes.php" class="nav-link <?= $activeMenu === 'solicitudes' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-check nav-icon"></i><span>Gestión Editorial</span>
            </a>
            <a href="<?= BASE_URL ?>areas/direccion_general/calendario_editorial.php" class="nav-link <?= $activeMenu === 'calendario' ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-list nav-icon"></i><span>Agenda y Tareas</span>
            </a>
            <a href="<?= BASE_URL ?>areas/direccion_general/repositorio.php" class="nav-link <?= $activeMenu === 'repositorio' ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-tree nav-icon"></i><span>Repositorio de Documentos</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'juridico_igualdad'): ?>
        <!-- Menu Exclusivo Jurídico Administrativo -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos Jurídico-Admin</span>
            <a href="<?= BASE_URL ?>areas/juridico_igualdad/agenda.php" class="nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week nav-icon"></i><span>Agenda y Kanban</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico_igualdad/personal.php" class="nav-link <?= $activeMenu === 'personal' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card nav-icon"></i><span>Personal del Área</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico_igualdad/contratos.php" class="nav-link <?= $activeMenu === 'contratos' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-signature nav-icon"></i><span>Contratos</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico_igualdad/normatividad.php" class="nav-link <?= $activeMenu === 'normatividad' ? 'active' : '' ?>">
                <i class="fa-solid fa-scale-balanced nav-icon"></i><span>Normatividad</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico_igualdad/igualdad.php" class="nav-link <?= $activeMenu === 'igualdad' ? 'active' : '' ?>">
                <i class="fa-solid fa-venus-mars nav-icon"></i><span>Igualdad y Género</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico_igualdad/adquisiciones.php" class="nav-link <?= $activeMenu === 'adquisiciones' ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping nav-icon"></i><span>Adquisiciones</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'financiamiento'): ?>
        <!-- Menu Exclusivo Financiamiento -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos Financiamiento</span>
            <a href="<?= BASE_URL ?>areas/financiamiento/agenda.php" class="nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week nav-icon"></i><span>Agenda y Kanban</span>
            </a>
            <a href="<?= BASE_URL ?>areas/financiamiento/personal.php" class="nav-link <?= $activeMenu === 'personal' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card nav-icon"></i><span>Personal del Área</span>
            </a>
            <a href="<?= BASE_URL ?>areas/financiamiento/convocatorias.php" class="nav-link <?= $activeMenu === 'convocatorias' ? 'active' : '' ?>">
                <i class="fa-solid fa-bullhorn nav-icon"></i><span>Convocatorias</span>
            </a>
            <a href="<?= BASE_URL ?>areas/financiamiento/becas.php" class="nav-link <?= $activeMenu === 'becas' ? 'active' : '' ?>">
                <i class="fa-solid fa-graduation-cap nav-icon"></i><span>Becas y Apoyos</span>
            </a>
            <a href="<?= BASE_URL ?>areas/financiamiento/presupuesto.php" class="nav-link <?= $activeMenu === 'presupuesto' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie nav-icon"></i><span>Presupuesto</span>
            </a>
            <a href="<?= BASE_URL ?>areas/financiamiento/reportes.php" class="nav-link <?= $activeMenu === 'reportes' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-chart-column nav-icon"></i><span>Reportes</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'apoyo_investigacion'): ?>
        <!-- Menu Exclusivo Apoyo Investigación Científica -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos Investigación</span>
            <a href="<?= BASE_URL ?>areas/apoyo_investigacion/agenda.php" class="nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week nav-icon"></i><span>Agenda y Kanban</span>
            </a>
            <a href="<?= BASE_URL ?>areas/apoyo_investigacion/personal.php" class="nav-link <?= $activeMenu === 'personal' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card nav-icon"></i><span>Personal del Área</span>
            </a>
            <a href="<?= BASE_URL ?>areas/apoyo_investigacion/proyectos.php" class="nav-link <?= $activeMenu === 'proyectos' ? 'active' : '' ?>">
                <i class="fa-solid fa-diagram-project nav-icon"></i><span>Proyectos</span>
            </a>
            <a href="<?= BASE_URL ?>areas/apoyo_investigacion/investigadores.php" class="nav-link <?= $activeMenu === 'investigadores' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-astronaut nav-icon"></i><span>Investigadores</span>
            </a>
            <a href="<?= BASE_URL ?>areas/apoyo_investigacion/publicaciones.php" class="nav-link <?= $activeMenu === 'publicaciones' ? 'active' : '' ?>">
                <i class="fa-solid fa-book-open nav-icon"></i><span>Publicaciones</span>
            </a>
            <a href="<?= BASE_URL ?>areas/apoyo_investigacion/convocatorias.php" class="nav-link <?= $activeMenu === 'convocatorias' ? 'active' : '' ?>">
                <i class="fa-solid fa-satellite-dish nav-icon"></i><span>Convocatorias</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'formacion_rrhh'): ?>
        <!-- Menu Exclusivo Formación RRHH -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos RRHH</span>
            <a href="<?= BASE_URL ?>areas/formacion_rrhh/agenda.php" class="nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week nav-icon"></i><span>Agenda y Kanban</span>
            </a>
            <a href="<?= BASE_URL ?>areas/formacion_rrhh/personal.php" class="nav-link <?= $activeMenu === 'personal' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card nav-icon"></i><span>Personal del Área</span>
            </a>
            <a href="<?= BASE_URL ?>areas/formacion_rrhh/cursos.php" class="nav-link <?= $activeMenu === 'cursos' ? 'active' : '' ?>">
                <i class="fa-solid fa-chalkboard-user nav-icon"></i><span>Cursos y Capacitación</span>
            </a>
            <a href="<?= BASE_URL ?>areas/formacion_rrhh/becas.php" class="nav-link <?= $activeMenu === 'becas' ? 'active' : '' ?>">
                <i class="fa-solid fa-award nav-icon"></i><span>Becas y Apoyos</span>
            </a>
            <a href="<?= BASE_URL ?>areas/formacion_rrhh/igualdad.php" class="nav-link <?= $activeMenu === 'igualdad' ? 'active' : '' ?>">
                <i class="fa-solid fa-scale-balanced nav-icon"></i><span>Igualdad de Género</span>
            </a>
            <a href="<?= BASE_URL ?>areas/formacion_rrhh/reportes.php" class="nav-link <?= $activeMenu === 'reportes' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-bar nav-icon"></i><span>Reportes</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'desarrollo_tecnologico'): ?>
        <!-- Menu Exclusivo Desarrollo Tecnológico y Vinculación -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos Des. Tecnológico</span>
            <a href="<?= BASE_URL ?>areas/desarrollo_tecnologico/agenda.php" class="nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week nav-icon"></i><span>Agenda y Kanban</span>
            </a>
            <a href="<?= BASE_URL ?>areas/desarrollo_tecnologico/personal.php" class="nav-link <?= $activeMenu === 'personal' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card nav-icon"></i><span>Personal del Área</span>
            </a>
            <a href="<?= BASE_URL ?>areas/desarrollo_tecnologico/proyectos.php" class="nav-link <?= $activeMenu === 'proyectos' ? 'active' : '' ?>">
                <i class="fa-solid fa-diagram-project nav-icon"></i><span>Proyectos Tecnológicos</span>
            </a>
            <a href="<?= BASE_URL ?>areas/desarrollo_tecnologico/convenios.php" class="nav-link <?= $activeMenu === 'convenios' ? 'active' : '' ?>">
                <i class="fa-solid fa-handshake nav-icon"></i><span>Convenios y Vinculación</span>
            </a>
            <a href="<?= BASE_URL ?>areas/desarrollo_tecnologico/transferencias.php" class="nav-link <?= $activeMenu === 'transferencias' ? 'active' : '' ?>">
                <i class="fa-solid fa-flask nav-icon"></i><span>Transferencia Tecnológica</span>
            </a>
            <a href="<?= BASE_URL ?>areas/desarrollo_tecnologico/reportes.php" class="nav-link <?= $activeMenu === 'reportes' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-bar nav-icon"></i><span>Reportes</span>
            </a>
        </div>

        <?php elseif ($slug_menu === 'juridico'): ?>
        <!-- Menu Exclusivo Asuntos Jurídicos -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos Jurídicos</span>
            <a href="<?= BASE_URL ?>areas/juridico/agenda.php" class="nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week nav-icon"></i><span>Agenda y Kanban</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico/personal.php" class="nav-link <?= $activeMenu === 'personal' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card nav-icon"></i><span>Personal del Área</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico/expedientes.php" class="nav-link <?= $activeMenu === 'expedientes' ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-open nav-icon"></i><span>Expedientes Jurídicos</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico/dictamenes.php" class="nav-link <?= $activeMenu === 'dictamenes' ? 'active' : '' ?>">
                <i class="fa-solid fa-scroll nav-icon"></i><span>Dictámenes y Opiniones</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico/acuerdos.php" class="nav-link <?= $activeMenu === 'acuerdos' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-signature nav-icon"></i><span>Acuerdos y Resoluciones</span>
            </a>
            <a href="<?= BASE_URL ?>areas/juridico/reportes.php" class="nav-link <?= $activeMenu === 'reportes' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-bar nav-icon"></i><span>Reportes</span>
            </a>
        </div>

        <?php else: ?>
        <!-- Menu Fallback para Áreas en Construcción -->
        <div class="nav-group">
            <span class="nav-group-label">Módulos en Desarrollo</span>
            <a href="#" class="nav-link" style="opacity:0.5; cursor:not-allowed;">
                <i class="fa-solid fa-hammer nav-icon"></i><span>Más herramientas pronto</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!($slug_menu === 'difusion' && $activeMenu === 'calendario')): ?>
        <div class="nav-group">
            <span class="nav-group-label">Acceso Publico</span>
            <a href="<?= BASE_URL ?>public/index.php" class="nav-link">
                <i class="fa-solid fa-arrow-up-right-from-square nav-icon"></i>
                <span>Vista Intranet</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <?php require_once __DIR__ . '/sidebar_footer.php'; ?>
</aside>

<!-- Overlay para sidebar movil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Chat and IA Assistant now handled by chat_widget.php -->

<script>
    // -? Ocultar Loader al cargar la página ---------------------------?
    window.addEventListener('load', function () {
        const loader = document.getElementById('globalLoader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 300);
        }
    });
</script>

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
            <!-- Búsqueda Global -->
            <div id="globalSearchWrapper" style="position:relative;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="text" id="globalSearchInput"
                           placeholder="Buscar solicitud, persona, equipo..."
                           autocomplete="off"
                           style="width:0;opacity:0;transition:width var(--transition-base),opacity var(--transition-base);border:0;padding:0;background:transparent;font-family:inherit;font-size:0.85rem;"
                           oninput="buscarGlobal(this.value)" onfocus="expandirBuscador()" onblur="setTimeout(()=>contraerBuscador(),200)">
                    <button id="btnBuscadorGlobal" class="topbar-btn" title="Búsqueda global" onclick="toggleBuscador()" style="flex-shrink:0;">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
                <!-- Panel de resultados -->
                <div id="globalSearchResults"
                     style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:340px;
                            background:var(--bg-card);border:1px solid var(--border-color);
                            border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);z-index:9998;
                            max-height:400px;overflow-y:auto;">
                </div>
            </div>

            <!-- Dark Mode Toggle -->
            <button id="darkModeToggle" class="topbar-btn" title="Cambiar tema"
                    onclick="toggleDarkMode()" style="flex-shrink:0;">
                <i class="fa-solid <?= $darkMode ? 'fa-sun' : 'fa-moon' ?>"></i>
            </button>

            <!-- El componente Universal Notification Bell se inyectará aquí automáticamente -->

            <!-- Botón Chat Equipo TI -->
            <button class="topbar-btn" id="chatToggleBtn" onclick="toggleChat()" title="Chat del equipo"
                    style="position:relative; border:none; cursor:pointer; background:none;">
                <i class="fa-solid fa-comments"></i>
                <span id="chatBadge" style="display:none; position:absolute; top:-5px; right:-5px;
                      min-width:16px; height:16px; border-radius:999px; background:#ef4444;
                      color:#fff; font-size:0.6rem; font-weight:700;
                      align-items:center; justify-content:center; padding:0 3px;
                      border:2px solid var(--bg-base,#0f0f1a);">0</span>
            </button>

            <!-- Botón Asistente IA -->
            <button class="topbar-btn" id="iaToggleBtn" onclick="toggleAsistenteIA()" title="Asistente IA COMECyT"
                    style="position:relative; border:none; cursor:pointer; background:rgba(177,154,109,0.12); border-radius:8px; color:#B19A6D;">
                <i class="fa-solid fa-robot"></i>
                <span id="iaBadgeOnline"
                      style="display:inline-block; position:absolute; top:-3px; right:-3px;
                             width:8px; height:8px; border-radius:50%; background:#22c55e;
                             border:2px solid var(--bg-base,#0f0f1a);"></span>
            </button>

            <?php require_once __DIR__ . '/chat_widget.php'; ?>
        </div>
    </header>

    <!-- Contenido de la pagina -->
    <main class="page-content">

<script>
const CSRF_HEADER_TOKEN = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
const BASE_URL_JS       = '<?= BASE_URL ?>';

function toggleDarkMode() {
    const body      = document.getElementById('bodyRoot');
    const isDark    = body.classList.toggle('dark-mode');
    const icon      = document.querySelector('#darkModeToggle i');
    if (icon) { icon.className = 'fa-solid ' + (isDark ? 'fa-sun' : 'fa-moon'); }
    const fd = new FormData();
    fd.append('csrf_token', CSRF_HEADER_TOKEN);
    fd.append('dark_mode', isDark ? '1' : '0');
    fetch(BASE_URL_JS + 'admin/api/toggle_darkmode.php', { method:'POST', body:fd }).catch(()=>{});
}

// -? Búsqueda Global -----------------------------------------------?
let _searchTimeout = null;
let _buscadorExpanded = false;

function toggleBuscador() {
    if (_buscadorExpanded) contraerBuscador(); else expandirBuscador();
}

function expandirBuscador() {
    const inp    = document.getElementById('globalSearchInput');
    const res    = document.getElementById('globalSearchResults');
    _buscadorExpanded = true;
    inp.style.width   = '220px';
    inp.style.opacity = '1';
    inp.style.border  = '1px solid var(--border-accent)';
    inp.style.borderRadius = '6px';
    inp.style.padding = '6px 10px';
    inp.style.background = 'var(--bg-card)';
    inp.style.color  = 'var(--text-primary)';
    inp.focus();
}

function contraerBuscador() {
    const inp = document.getElementById('globalSearchInput');
    const res = document.getElementById('globalSearchResults');
    if (inp.value.trim() === '') {
        _buscadorExpanded = false;
        inp.style.width   = '0';
        inp.style.opacity = '0';
        inp.style.border  = '0';
        inp.style.padding = '0';
        inp.style.background = 'transparent';
    }
    res.style.display = 'none';
}

const ICONOS_TIPO = { solicitud:'fa-file-lines', personal:'fa-id-card', equipo:'fa-laptop' };
const LABEL_TIPO  = { solicitud:'Solicitud', personal:'Personal', equipo:'Equipo' };

function buscarGlobal(q) {
    clearTimeout(_searchTimeout);
    const res = document.getElementById('globalSearchResults');
    if (q.trim().length < 2) { res.style.display = 'none'; return; }
    _searchTimeout = setTimeout(() => {
        fetch(BASE_URL_JS + 'admin/api/busqueda_global.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.ok || !data.resultados.length) {
                    res.innerHTML = '<p style="padding:16px;color:var(--text-muted);font-size:0.85rem;">Sin resultados para "' + q + '"</p>';
                    res.style.display = 'block';
                    return;
                }
                const grupos = {};
                data.resultados.forEach(r => {
                    if (!grupos[r.tipo_res]) grupos[r.tipo_res] = [];
                    grupos[r.tipo_res].push(r);
                });
                let html = '';
                Object.keys(grupos).forEach(tipo => {
                    html += `<div style="padding:8px 14px 4px;font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);border-bottom:1px solid var(--border-color);">${LABEL_TIPO[tipo] || tipo}</div>`;
                    grupos[tipo].forEach(item => {
                        html += `<a href="${item.url}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;text-decoration:none;border-bottom:1px solid var(--border-color-light);transition:background var(--transition-fast);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                            <i class="fa-solid ${item.icono}" style="color:var(--color-accent);width:16px;text-align:center;flex-shrink:0;"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.label}</div>
                                <div style="font-size:0.72rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.sub}</div>
                            </div>
                        </a>`;
                    });
                });
                res.innerHTML = html;
                res.style.display = 'block';
            }).catch(() => { res.style.display = 'none'; });
    }, 300);
}

    // -? Auto-abrir chat si viene de notificación ----------------
    document.addEventListener('DOMContentLoaded', () => {
        if (new URLSearchParams(window.location.search).get('openChat') === '1') {
            setTimeout(() => { if (typeof toggleChat === 'function') toggleChat(); }, 500);
        }
    });

</script>
<script src="<?= BASE_URL ?>assets/js/NotificationBell.js?v=<?= time() ?>"></script>

