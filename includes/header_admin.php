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
        $areas_funcionales = ['sistemas', 'difusion', 'direccion_general'];
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

<!-- ==============================================================
     CHAT PANEL  Equipo TI + Mensajes Directos (DM)
     Incluye: canal grupal, DMs por admin, avatares con inicial
     ============================================================== -->
<!-- ==============================================================
     CHAT PANEL v4 — Equipo TI + Mensajes Directos (DM)
     ============================================================== -->
<div id="chatPanel"
     aria-hidden="true"
     style="display:none; position:fixed; top:62px; right:20px;
            width:580px; height:540px; min-width:320px; min-height:400px;
            z-index:9999; background:#ffffff; border:1px solid #e5e7eb;
            border-radius:16px; box-shadow:0 24px 64px rgba(102,35,49,0.22);
            flex-direction:row; overflow:hidden;
            font-family:Inter,'Segoe UI',system-ui,sans-serif;
            font-size:14px; color:#111827;">

    <!-- Control de redimensionamiento (Esq. inferior izquierda) -->
    <div id="chatResizeHandle" 
         title="Arrastrar para redimensionar"
         style="position:absolute; left:0; bottom:0; width:20px; height:20px; 
                cursor:sw-resize; z-index:100; opacity:0.6; 
                background:linear-gradient(135deg, #662331 35%, transparent 35%);">
    </div>

    <!-- Panel Izquierdo: Canales / Contactos DM -->
    <div id="chatSidebar"
         style="width:168px; flex-shrink:0; display:flex; flex-direction:column;
                background:linear-gradient(180deg,#3d1520 0%,#662331 100%);
                border-right:1px solid rgba(255,255,255,0.08);">

        <div style="padding:14px 12px 10px; border-bottom:1px solid rgba(255,255,255,0.1);">
            <span style="font-size:0.8rem; font-weight:700; color:#fff; display:flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-comments"></i> <?= $chat_area_label ?>
            </span>
            <span style="font-size:0.64rem; color:rgba(255,255,255,0.55); display:block; margin-top:2px;">COMECyT · Chat Interno</span>
        </div>

        <div style="padding:8px 8px 4px;">
            <span style="font-size:0.6rem; font-weight:700; color:rgba(255,255,255,0.4); text-transform:uppercase; padding:0 4px;">Canal</span>
        </div>
        <button id="chatBtnGrupal" onclick="chatSeleccionarCanal(null)"
                style="margin:2px 8px; padding:8px 10px; background:rgba(255,255,255,0.18); border:none; border-radius:10px; cursor:pointer; text-align:left; display:flex; align-items:center; gap:8px; color:#fff; font-family:inherit; font-size:0.78rem; font-weight:600;">
            <span style="width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fa-solid fa-users"></i>
            </span>
            <span>General</span>
        </button>

        <div style="padding:10px 8px 4px;">
            <span style="font-size:0.6rem; font-weight:700; color:rgba(255,255,255,0.4); text-transform:uppercase; padding:0 4px;">Mensajes Directos</span>
        </div>
        <div id="chatAdminList" style="flex:1; overflow-y:auto; padding:0 8px 8px; display:flex; flex-direction:column; gap:2px;"></div>

        <div style="padding:8px; border-top:1px solid rgba(255,255,255,0.1); display:flex; gap:6px; flex-shrink:0;">
            <button id="btnNuevaTarea" title="Nueva tarea Kanban" style="flex:1; padding:5px; border:1px solid rgba(255,255,255,0.2); border-radius:7px; background:rgba(255,255,255,0.08); color:rgba(255,255,255,0.85); font-size:0.65rem; font-weight:600; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:3px;">
                <i class="fa-solid fa-list-check"></i>Tarea
            </button>
            <button id="btnNuevoEvento" title="Nuevo evento Calendario" style="flex:1; padding:5px; border:1px solid rgba(177,154,109,0.35); border-radius:7px; background:rgba(177,154,109,0.1); color:rgba(255,205,130,0.9); font-size:0.65rem; font-weight:600; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:3px;">
                <i class="fa-solid fa-calendar-plus"></i>Evento
            </button>
        </div>
    </div>

    <!-- Panel Derecho: Mensajes -->
    <div class="chat-right-panel" style="flex:1; display:flex; flex-direction:column; min-width:0; position:relative;">
        <div id="chatPanelHeader" style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:#f8f9fc; border-bottom:1px solid #e5e7eb; flex-shrink:0; cursor:grab;">
            <button class="chat-mobile-back" onclick="chatVolverALista()" title="Volver a lista" 
                    style="display:none; background:none; border:none; color:#6b7280; font-size:1.1rem; cursor:pointer; padding:5px 8px 5px 0;">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div id="chatCanalAvatar" style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#662331,#8b2f42); display:flex; align-items:center; justify-content:center; font-size:0.75rem; color:#fff; flex-shrink:0; font-weight:700;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div style="flex:1; min-width:0;">
                <span id="chatCanalNombre" style="font-size:0.88rem; font-weight:700; color:#1f2937; display:block; line-height:1.2;">General</span>
                <span id="chatCanalSub" style="font-size:0.68rem; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Canal grupal</span>
            </div>
            <button onclick="toggleChat()" title="Cerrar" style="background:#f3f4f6; border:none; color:#6b7280; width:26px; height:26px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="chatMessages" style="flex:1; overflow-y:auto; padding:12px 12px 4px; display:flex; flex-direction:column; gap:6px; scroll-behavior:smooth; background:#f9fafb;"></div>

        <!-- Tooltip de Emojis Expandido -->
        <div id="chatEmojiPicker" style="display:none; position:absolute; bottom:60px; right:12px; width:260px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); z-index:1000; padding:12px;">
            <div style="font-size:0.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                <span>Selector de Emojis</span>
                <i class="fa-solid fa-face-smile"></i>
            </div>
            <div id="chatEmojiGrid" style="display:grid; grid-template-columns:repeat(8,1fr); gap:4px; font-size:1.25rem; text-align:center; max-height:180px; overflow-y:auto;">
                <!-- Se llena dinámicamente o estáticamente aquí -->
                <span onclick="insertarEmoji('😊')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">😊</span>
                <span onclick="insertarEmoji('😂')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">😂</span>
                <span onclick="insertarEmoji('🤣')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🤣</span>
                <span onclick="insertarEmoji('😍')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">😍</span>
                <span onclick="insertarEmoji('🤔')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🤔</span>
                <span onclick="insertarEmoji('😅')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">😅</span>
                <span onclick="insertarEmoji('😎')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">😎</span>
                <span onclick="insertarEmoji('😭')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">😭</span>
                
                <span onclick="insertarEmoji('👍')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">👍</span>
                <span onclick="insertarEmoji('🙌')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🙌</span>
                <span onclick="insertarEmoji('👏')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">👏</span>
                <span onclick="insertarEmoji('🤝')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🤝</span>
                <span onclick="insertarEmoji('💪')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">💪</span>
                <span onclick="insertarEmoji('🙏')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🙏</span>
                <span onclick="insertarEmoji('👀')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">👀</span>
                <span onclick="insertarEmoji('✨')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">✨</span>

                <span onclick="insertarEmoji('🔥')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🔥</span>
                <span onclick="insertarEmoji('✅')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">✅</span>
                <span onclick="insertarEmoji('⚠️')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">⚠️</span>
                <span onclick="insertarEmoji('💡')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">💡</span>
                <span onclick="insertarEmoji('📌')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">📌</span>
                <span onclick="insertarEmoji('🚀')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🚀</span>
                <span onclick="insertarEmoji('💻')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">💻</span>
                <span onclick="insertarEmoji('🕒')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🕒</span>

                <span onclick="insertarEmoji('🎉')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🎉</span>
                <span onclick="insertarEmoji('📢')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">📢</span>
                <span onclick="insertarEmoji('🛠️')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🛠️</span>
                <span onclick="insertarEmoji('🔍')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">🔍</span>
                <span onclick="insertarEmoji('📦')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">📦</span>
                <span onclick="insertarEmoji('📋')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">📋</span>
                <span onclick="insertarEmoji('📎')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">📎</span>
                <span onclick="insertarEmoji('📅')" style="cursor:pointer; padding:3px; border-radius:6px;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=''">📅</span>
            </div>
        </div>

        <!-- Selector de Reacciones (Mini-Picker para Burbujas) -->
        <div id="chatReactionPicker" style="display:none; position:fixed; background:#fff; border:1px solid #e5e7eb; border-radius:24px; box-shadow:0 8px 24px rgba(0,0,0,0.12); z-index:10000; padding:4px 8px; display:flex; gap:4px; align-items:center;">
            <!-- Se llena vía JS -->
        </div>

        <div style="display:flex; align-items:flex-end; gap:8px; padding:10px 12px; border-top:1px solid #e5e7eb; background:#fdf8f5; flex-shrink:0;">
            <button onclick="toggleEmojiPicker()" title="Emojis" style="background:none; border:none; color:#9ca3af; font-size:1.1rem; cursor:pointer; padding:6px 2px;">😊</button>
            <textarea id="chatInput" placeholder="Escribe un mensaje..." rows="1" maxlength="2000" onkeydown="chatKeyDown(event)" style="flex:1; resize:none; border:1px solid #e5e7eb; border-radius:12px; background:#ffffff; color:#111827; padding:8px 12px; font-size:0.83rem; line-height:1.4; max-height:90px; outline:none; font-family:inherit;"></textarea>
            <button onclick="enviarMensaje()" title="Enviar" style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#662331,#8b2f42); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL: Nueva Tarea Kanban desde Chat
     ================================================================ -->
<div id="modalTareaOverlay"
     onclick="cerrarModalTarea()"
     style="display:none; position:fixed; inset:0; z-index:99999;
            background:rgba(102,35,49,0.35); backdrop-filter:blur(3px);
            align-items:center; justify-content:center; padding:20px;">
    <div onclick="event.stopPropagation()"
         style="background:#ffffff; border:1px solid #e5e7eb;
                border-radius:14px; padding:24px; width:100%; max-width:420px;
                box-shadow:0 24px 64px rgba(102,35,49,0.25);
                font-family:Inter,'Segoe UI',system-ui,sans-serif;">
        <h3 style="margin:0 0 18px; font-size:1rem; color:#662331; font-weight:700; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-list-check"></i> Nueva Tarea Kanban
        </h3>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Título *</label>
            <input type="text" id="chatTareaTitulo" placeholder="¿Qué hay que hacer?" maxlength="255"
                   style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px;
                          background:#f9fafb; color:#111827; font-size:0.85rem; outline:none; box-sizing:border-box;">
        </div>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Descripción</label>
            <textarea id="chatTareaDesc" rows="2" placeholder="Detalle opcional..."
                      style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px;
                             background:#f9fafb; color:#111827; font-size:0.85rem; outline:none;
                             resize:vertical; font-family:inherit; box-sizing:border-box;"></textarea>
        </div>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Asignar a</label>
            <select id="chatTareaAsignado"
                    style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px;
                           background:#f9fafb; color:#111827; font-size:0.85rem; outline:none; box-sizing:border-box;">
                <option value="">-- Sin asignar -</option>
            </select>
        </div>
        <div style="margin-bottom:18px; display:flex; align-items:center; gap:10px;">
            <label style="font-size:0.8rem; font-weight:600; color:#374151;">Color</label>
            <input type="color" id="chatTareaColor" value="#662331"
                   style="height:34px; width:56px; border-radius:6px; border:1px solid #d1d5db; cursor:pointer; padding:2px;">
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="confirmarTarea()"
                    style="flex:1; padding:9px 16px; background:linear-gradient(135deg,#662331,#8b2f42);
                           color:#fff; border:none; border-radius:8px; font-size:0.85rem; font-weight:600;
                           cursor:pointer; font-family:inherit;">Crear Tarea</button>
            <button onclick="cerrarModalTarea()"
                    style="padding:9px 16px; background:#f3f4f6;
                           color:#374151; border:1px solid #d1d5db; border-radius:8px; font-size:0.85rem;
                           cursor:pointer; font-family:inherit;">Cancelar</button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL: Nuevo Evento de Calendario desde Chat
     ================================================================ -->
<div id="modalEventoOverlay"
     onclick="cerrarModalEvento()"
     style="display:none; position:fixed; inset:0; z-index:99999;
            background:rgba(102,35,49,0.35); backdrop-filter:blur(3px);
            align-items:center; justify-content:center; padding:20px;">
    <div onclick="event.stopPropagation()"
         style="background:#ffffff; border:1px solid #e5e7eb;
                border-radius:14px; padding:24px; width:100%; max-width:420px;
                box-shadow:0 24px 64px rgba(177,154,109,0.3);
                font-family:Inter,'Segoe UI',system-ui,sans-serif;">
        <h3 style="margin:0 0 18px; font-size:1rem; color:#7d6535; font-weight:700; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-calendar-plus"></i> Nuevo Evento
        </h3>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Título *</label>
            <input type="text" id="chatEventoTitulo" placeholder="Nombre del evento" maxlength="255"
                   style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px;
                          background:#f9fafb; color:#111827; font-size:0.85rem; outline:none; box-sizing:border-box;">
        </div>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Descripción</label>
            <textarea id="chatEventoDesc" rows="2" placeholder="Descripción opcional..."
                      style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px;
                             background:#f9fafb; color:#111827; font-size:0.85rem; outline:none;
                             resize:vertical; font-family:inherit; box-sizing:border-box;"></textarea>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Fecha inicio *</label>
                <input type="datetime-local" id="chatEventoInicio"
                       style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:8px;
                               background:#f9fafb; color:#111827; font-size:0.8rem; outline:none; box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Fecha fin</label>
                <input type="datetime-local" id="chatEventoFin"
                       style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:8px;
                               background:#f9fafb; color:#111827; font-size:0.8rem; outline:none; box-sizing:border-box;">
            </div>
        </div>
        <div style="margin-bottom:18px; display:flex; align-items:center; gap:10px;">
            <label style="font-size:0.8rem; font-weight:600; color:#374151;">Color</label>
            <input type="color" id="chatEventoColor" value="#B19A6D"
                   style="height:34px; width:56px; border-radius:6px; border:1px solid #d1d5db; cursor:pointer; padding:2px;">
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="confirmarEvento()"
                    style="flex:1; padding:9px 16px; background:linear-gradient(135deg,#9b865f,#B19A6D);
                           color:#fff; border:none; border-radius:8px; font-size:0.85rem; font-weight:600;
                           cursor:pointer; font-family:inherit;">Agendar Evento</button>
            <button onclick="cerrarModalEvento()"
                    style="padding:9px 16px; background:#f3f4f6;
                           color:#374151; border:1px solid #d1d5db; border-radius:8px; font-size:0.85rem;
                           cursor:pointer; font-family:inherit;">Cancelar</button>
        </div>
    </div>
</div>

<!-- ================================================================
     PANEL DERECHO: Asistente IA COMECyT (iaPanel)
     ================================================================ -->
<div id="iaPanel" style="display:none; position:fixed; right:350px; top:60px; width:340px; height:500px; background:#fff; z-index:9998; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); flex-direction:column; font-family:Inter,sans-serif;">
    <div id="iaPanelHeader" style="padding:12px 15px; background:linear-gradient(135deg,#9b865f,#B19A6D); border-radius:12px 12px 0 0; display:flex; align-items:center; gap:10px; cursor:grab;">
        <div style="width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; color:#fff;">
            <i class="fa-solid fa-robot"></i>
        </div>
        <div style="flex:1; display:flex; flex-direction:column;">
            <span style="font-size:0.9rem; font-weight:700; color:#fff;">Asistente IA COMECyT</span>
            <span style="font-size:0.7rem; color:rgba(255,255,255,0.8);">Ollama Local · Qwen 2.5</span>
        </div>
        <button onclick="toggleAsistenteIA()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:26px; height:26px; border-radius:50%; cursor:pointer;">
            <i class="fa-solid fa-xmark" style="font-size:0.8rem;"></i>
        </button>
    </div>
    <div id="iaMessages" style="flex:1; padding:15px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; background:#f9fafb;"></div>
    <div id="iaTyping" style="display:none; padding:8px 15px; font-size:0.75rem; color:#6b7280; background:#f9fafb; font-style:italic;">
        <i class="fa-solid fa-circle-notch fa-spin"></i> Escribiendo...
    </div>
    <div style="padding:10px 15px; border-top:1px solid #e5e7eb; background:#fff; display:flex; gap:8px;">
        <textarea id="iaInput" placeholder="Pregunta algo..." rows="1" style="flex:1; border:1px solid #d1d5db; border-radius:12px; padding:8px 12px; outline:none; font-size:0.85rem; resize:none;" onkeydown="iaKeyDown(event)"></textarea>
        <button onclick="iaEnviar()" style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#9b865f,#B19A6D); color:#fff; border:none; cursor:pointer; align-self:flex-end;">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>





<script>
/**
 * COMECyT  Chat Grupal + Mensajes Directos (DM) de Administradores
 * ----------------------------------------------------------------?
 * Features:
 *   · Canal grupal y canales DM por administrador
 *   · Avatares con inicial de nombre para cada admin
 *   · Polling activo en panel abierto, polling de fondo silencioso
 *   · Badge de no leídos + divisor visual de mensajes nuevos
 *   · Drag & drop del panel
 *   · Compatible 100% con PostgreSQL 15+ (timestamps via TO_CHAR backend)
 */
(function () {
    const ADMIN_ID   = '<?= $_SESSION['admin_id'] ? "A".$_SESSION['admin_id'] : "P".$_SESSION['user_id'] ?>';
    const API        = '<?= BASE_URL ?>admin/api/chat.php';
    const POLL_MS    = 6000;   // Reducción a 6s para mayor agilidad
    const BG_POLL_MS = 15000;  // Reducción a 15s (antes 30s)

    // --? Estado del módulo ---------------------------------------?
    let chatOpen          = false;
    let canalActual       = null;
    let ultimoId          = 0;
    let noLeidosCnt       = 0;
    let pollingTimer      = null;
    let bgPollingTimer    = null;
    let listaAdmins       = [];
    let csrfToken         = '<?= $_SESSION["csrf_token"] ?? "" ?>';
    const ADMIN_AREA      = <?= (int)($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0) ?>;

    // Paleta de colores para los avatares (circular rotatoria)
    const AVATAR_COLORS = [
        '#662331','#7c3aed','#059669','#b45309','#0369a1',
        '#be185d','#15803d','#c2410c','#1d4ed8','#6b21a8',
    ];

    // -? Inyectar estilos de animaciones y UI mejorada ----------?
    (function injectStyles() {
        const s = document.createElement('style');
        s.textContent = `
            @keyframes chatBadgePulse {
                0%,100% { transform:scale(1); }
                50%      { transform:scale(1.25); }
            }
            #chatBadge { animation:chatBadgePulse 1.8s ease-in-out infinite; }
            .chat-dm-btn { transition:background .15s, opacity .15s; }
            .chat-dm-btn:hover { background:rgba(255,255,255,0.13) !important; }
            .chat-dm-btn.activo { background:rgba(255,255,255,0.22) !important; }
            #chatBtnGrupal.activo { background:rgba(255,255,255,0.3) !important; }
            
            .chat-reacciones {
                display:flex; flex-wrap:wrap; gap:3px; margin-top:4px;
            }
            .chat-reaccion-chip {
                background:rgba(255,255,255,0.8); border:1px solid #e5e7eb;
                border-radius:10px; padding:1px 6px; font-size:0.75rem;
                display:flex; align-items:center; gap:3px; cursor:pointer;
                transition: transform 0.1s;
            }
            .chat-reaccion-chip:hover { transform: scale(1.1); background:#fff; }
            .chat-reaccion-yo { border-color:#662331; background:#fdf8f5; }
            
            .chat-bubble-tools {
                opacity:0; transition:opacity 0.2s; 
                position:absolute; top:0; right:-30px; display:flex; flex-direction:column; gap:4px;
            }
            .chat-bubble-wrap:hover .chat-bubble-tools { opacity:1; }
            .chat-bubble-btn {
                width:24px; height:24px; border-radius:50%; background:#fff;
                border:1px solid #e5e7eb; color:#9ca3af; display:flex;
                align-items:center; justify-content:center; cursor:pointer; font-size:0.7rem;
            }
            .chat-bubble-btn:hover { color:#662331; border-color:#662331; }

            /* --- Responsive Chat --- */
            @media (max-width: 650px) {
                #chatPanel {
                    width: calc(100% - 20px) !important;
                    height: calc(100% - 100px) !important;
                    right: 10px !important;
                    left: 10px !important;
                    top: 80px !important;
                    border-radius: 12px !important;
                }
                #chatSidebar {
                    width: 100% !important;
                    display: flex !important;
                    border-right: none !important;
                }
                #chatPanel.chat-mensajes-activos #chatSidebar {
                    display: none !important;
                }
                #chatPanel.chat-mensajes-activos .chat-right-panel {
                    display: flex !important;
                    width: 100% !important;
                }
                #chatPanel:not(.chat-mensajes-activos) .chat-right-panel {
                    display: none !important;
                }
                .chat-mobile-back {
                    display: flex !important;
                }
                /* Ajustes menores para ahorrar espacio */
                #chatAdminList { padding: 0 4px 4px !important; }
                .chat-dm-btn { margin: 2px 4px !important; }
                #chatInput { font-size: 16px !important; } /* Evita zoom en iOS */
            }
        `;
        document.head.appendChild(s);
    })();

    // -? Helpers de badge ----------------------------------------?
    function mostrarBadge(n) {
        const b = document.getElementById('chatBadge');
        if (!b) return;
        b.textContent   = n > 99 ? '99+' : n;
        b.style.display = 'flex';
    }
    function ocultarBadge() {
        const b = document.getElementById('chatBadge');
        if (b) { b.style.display = 'none'; b.textContent = '0'; }
    }

    // -? Color determinístico por ID de admin --------------------?
    function colorAdmin(id) {
        let num = 0;
        if (typeof id === 'string') {
            for (let i = 0; i < id.length; i++) num += id.charCodeAt(i);
        } else {
            num = id;
        }
        return AVATAR_COLORS[(num + 1) % AVATAR_COLORS.length];
    }

    // -? Volver a lista en móvil ----------------------------------?
    window.chatVolverALista = function() {
        document.getElementById('chatPanel').classList.remove('chat-mensajes-activos');
    };

    // -? Crear chip de avatar con inicial ------------------------?
    function crearAvatar(nombre, adminId, size = 28) {
        const inicial = nombre ? nombre.trim().charAt(0).toUpperCase() : '?';
        const bg      = colorAdmin(adminId);
        const span    = document.createElement('span');
        span.style.cssText = [
            `width:${size}px`, `height:${size}px`, 'border-radius:50%',
            `background:${bg}`, 'display:flex', 'align-items:center',
            'justify-content:center', 'flex-shrink:0',
            `font-size:${Math.round(size * 0.42)}px`, 'color:#fff',
            'font-weight:700', 'user-select:none',
        ].join(';');
        span.textContent = inicial;
        return span;
    }

    // -? Abrir/cerrar el panel ------------------------------------?
    window.toggleChat = function () {
        chatOpen = !chatOpen;
        const panel = document.getElementById('chatPanel');
        panel.style.display = chatOpen ? 'flex' : 'none';
        panel.setAttribute('aria-hidden', String(!chatOpen));

        if (chatOpen) {
            detenerBgPolling();
            cargarAdmins();
            limpiarMensajes();
            cargarMensajes(true, true);
            iniciarPolling();
            ocultarBadge();
            noLeidosCnt = 0;
            setTimeout(() => document.getElementById('chatInput')?.focus(), 200);
        } else {
            detenerPolling();
            primerIdNoLeido = 0;
            iniciarBgPolling();
        }
    };

    // -? Seleccionar canal (null = grupal, N = DM con admin N) ---?
    window.chatSeleccionarCanal = function (adminId) {
        canalActual = adminId;
        ultimoId    = 0;       // Reiniciar cursor de paginación al cambiar canal

        // Activar vista de mensajes en móvil
        document.getElementById('chatPanel').classList.add('chat-mensajes-activos');

        // Actualizar header del canal activo
        const avatar  = document.getElementById('chatCanalAvatar');
        const nombre  = document.getElementById('chatCanalNombre');
        const sub     = document.getElementById('chatCanalSub');

        if (adminId === null) {
            // Canal grupal
            canalNombreActual = 'General';
            avatar.innerHTML  = '<i class="fa-solid fa-users" style="font-size:0.8rem;"></i>';
            avatar.style.background = 'linear-gradient(135deg,#662331,#8b2f42)';
            nombre.textContent = 'General';
            sub.textContent    = 'Canal grupal · <?= $chat_area_label ?>';
            document.getElementById('chatInput').placeholder = 'Escribe al equipo--';
        } else {
            // adminId es string con prefijo (ej. 'A5' o 'P12')
            const admin = listaAdmins.find(a => String(a.id) === String(adminId));
            canalNombreActual = admin ? admin.nombre.split(' ')[0] : 'Mensaje Directo';
            avatar.innerHTML  = '';
            avatar.style.background = 'transparent';
            avatar.appendChild(crearAvatar(admin ? admin.nombre : '?', adminId, 32));
            nombre.textContent = admin ? admin.nombre : 'DM';
            sub.textContent    = 'Mensaje directo · Solo visible para ustedes dos';
            document.getElementById('chatInput').placeholder = `Mensaje privado a ${canalNombreActual}--`;
        }

        // Resaltar botón activo en el sidebar
        document.querySelectorAll('.chat-dm-btn').forEach(b => b.classList.remove('activo'));
        const btnGrupal = document.getElementById('chatBtnGrupal');
        if (adminId === null) {
            btnGrupal.classList.add('activo');
        } else {
            const btn = document.getElementById(`chatDMbtn_${adminId}`);
            if (btn) btn.classList.add('activo');
            btnGrupal.classList.remove('activo');
        }

        limpiarMensajes();
        cargarMensajes(true, false);
    };

    function limpiarMensajes() {
        const zona = document.getElementById('chatMessages');
        zona.innerHTML = '<div id="chatLoadingState" style="color:#9ca3af;font-size:0.8rem;text-align:center;padding:24px 0;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando mensajes...</div>';
    }

    // -? Polling activo -------------------------------------------?
    function iniciarPolling() {
        detenerPolling();
        pollingTimer = setInterval(() => cargarMensajes(false, false), POLL_MS);
    }
    function detenerPolling() {
        if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
    }

    // -? Polling de fondo -----------------------------------------?
    function iniciarBgPolling() {
        detenerBgPolling();
        bgPollingTimer = setInterval(verificarNoLeidos, BG_POLL_MS);
    }
    function detenerBgPolling() {
        if (bgPollingTimer) { clearInterval(bgPollingTimer); bgPollingTimer = null; }
    }

    function verificarNoLeidos() {
        fetch(API + '?accion=listar&desde=' + ultimoId)
            .then(r => r.json())
            .then(data => {
                if (!data.ok || !data.mensajes.length) return;
                data.mensajes.forEach(m => {
                    const mid = parseInt(m.id);
                    if (primerIdNoLeido === 0) primerIdNoLeido = mid;
                    ultimoId = Math.max(ultimoId, mid);
                });
                noLeidosCnt += data.mensajes.length;
                mostrarBadge(noLeidosCnt);
            })
            .catch(() => {});
    }

    // -? Cargar y renderizar mensajes ----------------------------?
    function cargarMensajes(scroll, insertDivider) {
        let url = API + '?accion=listar&desde=' + ultimoId;
        if (canalActual !== null) url += '&destinatario=' + canalActual;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;

                const zona = document.getElementById('chatMessages');
                const loadingEl = document.getElementById('chatLoadingState');
                if (loadingEl) loadingEl.remove();

                if (!data.mensajes.length) {
                    if (scroll) zona.scrollTop = zona.scrollHeight;
                    return;
                }

                data.mensajes.forEach(m => {
                    const mid = parseInt(m.id);
                    zona.appendChild(renderBurbuja(m));
                    ultimoId = Math.max(ultimoId, mid);
                });

                if (scroll) zona.scrollTop = zona.scrollHeight;
                
                // Si el chat está abierto, persistir lectura en servidor
                if (chatOpen && data.mensajes.length > 0) {
                    marcarLeidoServidor(ultimoId);
                }
            })
            .catch(() => {});
    }

    function marcarLeidoServidor(id) {
        const fd = new FormData();
        fd.append('accion', 'marcar_leido');
        fd.append('ultimo_id', id);
        fetch(API, { method: 'POST', body: fd }).catch(()=>{});
    }

    function crearDivisorNoLeidos(n) {
        const el  = document.createElement('div');
        el.id     = 'chatDivisorNoLeidos';
        el.style.cssText = 'display:flex;align-items:center;gap:8px;margin:8px 0;flex-shrink:0;';
        const txt = n > 0 ? `${n} mensaje${n > 1 ? 's' : ''} nuevo${n > 1 ? 's' : ''}` : 'Nuevos';
        el.innerHTML = `
            <div style="flex:1;height:1px;background:rgba(102,35,49,0.25);"></div>
            <span style="font-size:0.68rem;font-weight:700;color:#662331;white-space:nowrap;
                         background:#fff;padding:2px 8px;border-radius:20px;
                         border:1px solid rgba(102,35,49,0.25);">${txt} </span>
            <div style="flex:1;height:1px;background:rgba(102,35,49,0.25);"></div>`;
        return el;
    }

    // -? Renderizar burbuja de mensaje con Reacciones -----------?
    function renderBurbuja(m) {
        // ADMIN_ID es string con prefijo (ej. 'A5' o 'P12'), comparar como string
        const propio = String(m.admin_id) === String(ADMIN_ID);
        const mid    = parseInt(m.id);
        const wrap   = document.createElement('div');
        wrap.className = 'chat-bubble-wrap';
        wrap.style.cssText = [
            'display:flex', 'gap:6px', 'max-width:88%', 'position:relative',
            propio ? 'align-self:flex-end;flex-direction:row-reverse' : 'align-self:flex-start;flex-direction:row',
        ].join(';');

        if (!propio) {
            wrap.appendChild(crearAvatar(m.admin_nombre, parseInt(m.admin_id), 26));
        }

        const col  = document.createElement('div');
        col.style.cssText = 'display:flex;flex-direction:column;' + (propio ? 'align-items:flex-end;' : 'align-items:flex-start;');

        let contenido = '';
        if (m.tipo === 'tarea' || m.tipo === 'evento') {
            const icon = m.tipo === 'tarea' ? 'fa-list-check' : 'fa-calendar-days';
            const color = m.tipo === 'tarea' ? '#6366f1' : '#0891b2';
            const bg = m.tipo === 'tarea' ? 'rgba(99,102,241,0.15)' : 'rgba(6,182,212,0.15)';
            const url = m.tipo === 'tarea' ? 'admin/kanban.php' : 'admin/calendario.php';
            contenido = `<a href="${BASE_URL_JS}${url}" style="display:inline-flex;align-items:center;gap:7px;padding:7px 12px;background:${bg};border:1px solid ${color}44;border-radius:10px;color:${color};font-size:0.8rem;font-weight:600;text-decoration:none;transition:transform 0.1s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fa-solid ${icon}"></i> ${escapeHtml(m.ref_titulo || m.mensaje)}
            </a>`;
        } else {
            const radius = propio ? '14px 14px 3px 14px' : '14px 14px 14px 3px';
            contenido = `<span style="display:inline-block;padding:8px 12px;background:${propio ? 'linear-gradient(135deg,#662331,#8b2f42)' : '#f0ece8'};border-radius:${radius};color:${propio ? '#fff' : '#111827'};font-size:0.83rem;line-height:1.45;word-break:break-word;white-space:pre-wrap;">${escapeHtml(m.mensaje)}</span>`;
        }

        // Reacciones
        let reacHtml = '';
        if (m.reacciones && m.reacciones.length > 0) {
            reacHtml = '<div class="chat-reacciones">';
            const counts = {};
            m.reacciones.forEach(r => {
                counts[r.emoji] = (counts[r.emoji] || 0) + 1;
                if (r.admin_id === ADMIN_ID) counts[r.emoji + '_yo'] = true;
            });
            Object.keys(counts).forEach(e => {
                if (e.endsWith('_yo')) return;
                const esMio = counts[e + '_yo'];
                reacHtml += `<div class="chat-reaccion-chip ${esMio ? 'chat-reaccion-yo' : ''}" onclick="reaccionar(${mid}, '${e}')">${e} <span>${counts[e]}</span></div>`;
            });
            reacHtml += '</div>';
        }

        // Herramientas (Reaccionar rápido)
        const tools = document.createElement('div');
        tools.className = 'chat-bubble-tools';
        tools.style.right = propio ? 'auto' : '-30px';
        tools.style.left  = propio ? '-30px' : 'auto';
        tools.innerHTML = `<button class="chat-bubble-btn" onclick="toggleReaccionesRapidas(event, ${mid})" title="Reaccionar"><i class="fa-solid fa-face-smile"></i></button>`;

        const meta = `<span style="font-size:0.64rem;color:#9ca3af;padding:2px 2px 0;">${propio ? m.hora : escapeHtml(m.admin_nombre) + ' · ' + m.hora}</span>`;
        
        col.innerHTML = contenido + reacHtml + meta;
        wrap.appendChild(col);
        wrap.appendChild(tools);
        return wrap;
    }

    window.toggleReaccionesRapidas = function(e, mid) {
        e.stopPropagation();
        const p = document.getElementById('chatReactionPicker');
        if (p.style.display === 'flex' && p.dataset.mid == mid) {
            p.style.display = 'none';
            return;
        }

        const emojis = ['👍','❤️','😂','😮','😢','🔥'];
        p.innerHTML = emojis.map(emo => `
            <span onclick="reaccionar(${mid}, '${emo}'); document.getElementById('chatReactionPicker').style.display='none';" 
                  style="cursor:pointer; padding:4px 6px; border-radius:12px; font-size:1.2rem; transition:transform 0.1s;"
                  onmouseover="this.style.transform='scale(1.3)';this.style.background='#f3f4f6'"
                  onmouseout="this.style.transform='scale(1)';this.style.background='transparent'">
                ${emo}
            </span>
        `).join('') + `
            <div style="width:1px; height:20px; background:#e5e7eb; margin:0 4px;"></div>
            <i class="fa-solid fa-plus" style="color:#9ca3af; font-size:0.8rem; cursor:pointer; padding:4px;" 
               onclick="toggleEmojiPicker(); document.getElementById('chatReactionPicker').style.display='none';"></i>
        `;

        p.dataset.mid = mid;
        p.style.display = 'flex';
        
        // Posicionamiento inteligente cerca del clic
        const rect = e.target.closest('button').getBoundingClientRect();
        p.style.left = (rect.left - 100) + 'px';
        p.style.top  = (rect.top - 50) + 'px';

        // Cerrar al hacer clic fuera
        const closePicker = (ev) => {
            if (!p.contains(ev.target)) {
                p.style.display = 'none';
                document.removeEventListener('click', closePicker);
            }
        };
        setTimeout(() => document.addEventListener('click', closePicker), 10);
    };

    window.reaccionar = function(mid, emoji) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('accion', 'reaccionar');
        fd.append('mensaje_id', mid);
        fd.append('emoji', emoji);
        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.ok) cargarMensajes(false, false); });
    };

    window.toggleEmojiPicker = function() {
        const p = document.getElementById('chatEmojiPicker');
        p.style.display = p.style.display === 'none' ? 'block' : 'none';
    };

    window.insertarEmoji = function(e) {
        const inp = document.getElementById('chatInput');
        inp.value += e;
        inp.focus();
        // No cerrar automáticamente para permitir insertar varios
    };

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // -? Enviar mensaje -------------------------------------------?
    window.enviarMensaje = function () {
        const input = document.getElementById('chatInput');
        const texto = input.value.trim();
        if (!texto) return;

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('accion',    'enviar');
        fd.append('mensaje',   texto);
        fd.append('cve_area',  ADMIN_AREA);
        if (canalActual !== null) fd.append('destinatario', canalActual);

        input.value        = '';
        input.style.height = '';

        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.ok) cargarMensajes(true, false); })
            .catch(() => {});
    };

    window.chatKeyDown = function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            enviarMensaje();
        }
    };

    // -? Cargar lista de admins y construir el sidebar de DMs ----?
    function cargarAdmins() {
        if (listaAdmins.length) return; // cache
        fetch(API + '?accion=admins')
            .then(r => r.json())
            .then(d => {
                listaAdmins = d.admins || [];
                const lista = document.getElementById('chatAdminList');
                const sel   = document.getElementById('chatTareaAsignado');
                lista.innerHTML = '';

                listaAdmins.forEach(a => {
                    const aid = parseInt(a.id);

                    // Botón DM en el sidebar
                    const btn = document.createElement('button');
                    btn.id    = `chatDMbtn_${aid}`;
                    btn.className = 'chat-dm-btn';
                    btn.title  = `Mensaje directo a ${a.nombre}`;
                    btn.style.cssText = [
                        'padding:7px 10px', 'background:rgba(255,255,255,0.06)',
                        'border:none', 'border-radius:10px', 'cursor:pointer',
                        'text-align:left', 'display:flex', 'align-items:center',
                        'gap:8px', 'color:rgba(255,255,255,0.9)',
                        'font-family:inherit', 'font-size:0.75rem', 'font-weight:500',
                        'width:100%',
                    ].join(';');

                    // Avatar con inicial + badge de rol
                    const av = crearAvatar(a.nombre, aid, 28);
                    const info = document.createElement('span');
                    info.style.cssText = 'min-width:0;flex:1;overflow:hidden;';
                    info.innerHTML = `
                        <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            ${escapeHtml(a.nombre.split(' ')[0])}
                        </span>
                        <span style="font-size:0.6rem;color:rgba(255,255,255,0.45);display:block;line-height:1.2;">
                            ${escapeHtml(a.rol || '')}
                        </span>`;

                    btn.appendChild(av);
                    btn.appendChild(info);
                    btn.addEventListener('click', () => chatSeleccionarCanal(a.id));

                    // Ocultar el propio usuario en la lista DM (comparar como string con prefijo)
                    if (String(a.id) !== String(ADMIN_ID)) lista.appendChild(btn);

                    // Llenar select de asignación de tareas
                    if (sel) {
                        const opt = document.createElement('option');
                        opt.value = aid;
                        opt.textContent = a.nombre;
                        sel.appendChild(opt);
                    }
                });

                // Marcar grupal como activo por defecto
                document.getElementById('chatBtnGrupal')?.classList.add('activo');
            })
            .catch(() => {});
    }

    // -? Modal: Nueva Tarea Kanban --------------------------------?
    document.getElementById('btnNuevaTarea').addEventListener('click', () => {
        document.getElementById('chatTareaTitulo').value = '';
        document.getElementById('chatTareaDesc').value   = '';
        document.getElementById('chatTareaAsignado').value = '';
        document.getElementById('chatTareaColor').value  = '#662331';
        document.getElementById('modalTareaOverlay').style.display = 'flex';
        setTimeout(() => document.getElementById('chatTareaTitulo').focus(), 100);
    });
    window.cerrarModalTarea = () => {
        document.getElementById('modalTareaOverlay').style.display = 'none';
    };
    window.confirmarTarea = function () {
        const titulo = document.getElementById('chatTareaTitulo').value.trim();
        if (!titulo) { COMECyTUI.alert('El título de la tarea es obligatorio.', 'Validación'); return; }
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('accion',      'crear_tarea');
        fd.append('titulo',      titulo);
        fd.append('descripcion', document.getElementById('chatTareaDesc').value.trim());
        fd.append('color',       document.getElementById('chatTareaColor').value);
        const asig = document.getElementById('chatTareaAsignado').value;
        if (asig) fd.append('asignado_a', asig);
        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { 
                if (d.ok) { 
                    cerrarModalTarea(); 
                    cargarMensajes(true, false); 
                    if(window.COMECyTNotif) window.COMECyTNotif.alerta('chat');
                } else COMECyTUI.alert('Error: ' + (d.error || 'Desconocido'), 'Error al crear'); 
            })
            .catch(() => COMECyTUI.toast('Error de conexión', 'error'));
    };

    // -? Modal: Nuevo Evento --------------------------------------?
    document.getElementById('btnNuevoEvento').addEventListener('click', () => {
        document.getElementById('chatEventoTitulo').value  = '';
        document.getElementById('chatEventoDesc').value    = '';
        document.getElementById('chatEventoColor').value   = '#B19A6D';
        const ahora = new Date(); ahora.setMinutes(0,0,0);
        const iso   = ahora.toISOString().slice(0,16);
        document.getElementById('chatEventoInicio').value  = iso;
        document.getElementById('chatEventoFin').value     = iso;
        document.getElementById('modalEventoOverlay').style.display = 'flex';
        setTimeout(() => document.getElementById('chatEventoTitulo').focus(), 100);
    });
    window.cerrarModalEvento = () => {
        document.getElementById('modalEventoOverlay').style.display = 'none';
    };
    window.confirmarEvento = function () {
        const titulo = document.getElementById('chatEventoTitulo').value.trim();
        const inicio = document.getElementById('chatEventoInicio').value;
        if (!titulo || !inicio) { COMECyTUI.alert('El título y la fecha de inicio son campos obligatorios.', 'Validación'); return; }
        const fd = new FormData();
        fd.append('csrf_token',   csrfToken);
        fd.append('accion',       'crear_evento');
        fd.append('titulo',       titulo);
        fd.append('descripcion',  document.getElementById('chatEventoDesc').value.trim());
        fd.append('fecha_inicio', inicio);
        fd.append('fecha_fin',    document.getElementById('chatEventoFin').value || inicio);
        fd.append('color',        document.getElementById('chatEventoColor').value);
        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { 
                if (d.ok) { 
                    cerrarModalEvento(); 
                    cargarMensajes(true, false); 
                    if(window.COMECyTNotif) window.COMECyTNotif.alerta('chat');
                } else COMECyTUI.alert('Error: ' + (d.error || 'Desconocido'), 'Error al agendar'); 
            })
            .catch(() => COMECyTUI.toast('Error de conexión', 'error'));
    };

    // -? Drag & Drop + Resize del panel v4.1 ----------------------?
    (function initInteractions() {
        const panel   = document.getElementById('chatPanel');
        const header  = document.getElementById('chatPanelHeader');
        const resizer = document.getElementById('chatResizeHandle');
        if (!panel || !header || !resizer) return;

        let dragActive = false, resizeActive = false;
        let startX, startY, startW, startH, origLeft, origTop;

        // DRAG
        header.addEventListener('mousedown', (e) => {
            if (e.target.closest('button')) return;
            dragActive = true;
            header.style.cursor = 'grabbing';
            const rect = panel.getBoundingClientRect();
            startX = e.clientX; startY = e.clientY;
            origLeft = rect.left; origTop = rect.top;
            panel.style.right = 'auto'; 
            panel.style.left = origLeft + 'px';
            panel.style.top = origTop + 'px';
            e.preventDefault();
        });

        // RESIZE (Bottom-Left)
        resizer.addEventListener('mousedown', (e) => {
            resizeActive = true;
            startX = e.clientX; startY = e.clientY;
            const rect = panel.getBoundingClientRect();
            startW = rect.width; 
            startH = rect.height;
            origLeft = rect.left;
            origTop  = rect.top;
            e.preventDefault();
            e.stopPropagation();
        });

        document.addEventListener('mousemove', (e) => {
            if (dragActive) {
                let newLeft = origLeft + (e.clientX - startX);
                let newTop  = origTop + (e.clientY - startY);
                panel.style.left = Math.max(0, Math.min(newLeft, window.innerWidth - panel.offsetWidth)) + 'px';
                panel.style.top  = Math.max(0, Math.min(newTop, window.innerHeight - panel.offsetHeight)) + 'px';
            }
            if (resizeActive) {
                const dx = startX - e.clientX; 
                const dy = e.clientY - startY;
                
                const newW = Math.max(320, Math.min(startW + dx, window.innerWidth * 0.9));
                const newH = Math.max(400, Math.min(startH + dy, window.innerHeight * 0.9));
                
                // Al crecer hacia la IZQUIERDA, el 'left' debe disminuir
                if (newW > 320) {
                    panel.style.left  = (origLeft - (newW - startW)) + 'px';
                    panel.style.width = newW + 'px';
                }
                panel.style.height = newH + 'px';
            }
        });

        document.addEventListener('mouseup', () => {
            dragActive = false; resizeActive = false;
            header.style.cursor = 'grab';
        });
    })();

    // -? Iniciar polling de fondo al cargar la página ---------------?
    // Detecta mensajes nuevos incluso antes de abrir el chat por primera vez
    iniciarBgPolling();

})();

// ====================================================================
// ASISTENTE IA COMECyT  Groq / LLaMA 3
// ====================================================================
(function () {
    const IA_API      = '<?= BASE_URL ?>admin/api/agente_ia.php';
    const CSRF_TOKEN  = '<?= $_SESSION["csrf_token"] ?? "" ?>';
    const PAGINA_CTX  = '<?= esc($activeMenu ?: "general") ?>';

    let iaOpen    = false;
    let iaBusy    = false;
    let iaHistory = [];  // [{ role: 'user'|'assistant', content: '...' }]

    // -? Toggle del panel --------------------------------------------?
    window.toggleAsistenteIA = function () {
        iaOpen = !iaOpen;
        const panel = document.getElementById('iaPanel');
        panel.style.display       = iaOpen ? 'flex' : 'none';
        panel.style.flexDirection = 'column';
        panel.setAttribute('aria-hidden', String(!iaOpen));

        if (iaOpen) {
            if (iaHistory.length === 0) iaMostrarBienvenida();
            setTimeout(() => document.getElementById('iaInput').focus(), 200);
        }
    };

    // -? Mensaje de bienvenida ----------------------------------------?
    function iaMostrarBienvenida() {
        iaAgregarBurbuja('assistant',
            '¡Hola! Soy el Asistente IA de COMECyT.\n\nPuedo ayudarte con dudas sobre el sistema: módulos, estatus de solicitudes, flujos de trabajo y más.\n\n¿En qué te puedo ayudar?'
        );
    }

    // -? Enviar mensaje -----------------------------------------------?
    window.iaEnviar = function () {
        if (iaBusy) return;
        const input  = document.getElementById('iaInput');
        const texto  = input.value.trim();
        if (!texto) return;

        input.value = '';
        input.style.height = '';

        iaAgregarBurbuja('user', texto);
        iaHistory.push({ role: 'user', content: texto });

        iaBusy = true;
        document.getElementById('iaTyping').style.display = 'block';
        iaMostrarScroll();

        const fd = new FormData();
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('mensaje',    texto);
        fd.append('pagina',     PAGINA_CTX);
        fd.append('historial',  JSON.stringify(iaHistory.slice(0, 1))); // historial previo

        fetch(IA_API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                document.getElementById('iaTyping').style.display = 'none';
                iaBusy = false;

                if (!data.ok) {
                    if (data.no_key) {
                        iaAgregarBurbuja('assistant',
                            '-sT️ **API Key no configurada**\n\n' +
                            'Para usar el Asistente IA, el administrador del servidor debe:\n' +
                            '1. Ir a https://console.groq.com y crear una cuenta gratuita\n' +
                            '2. Generar un API Key\n' +
                            '3. Editar el archivo `.env` del sistema y reemplazar `TU_API_KEY_AQUI` con el key obtenido'
                        );
                    } else {
                        iaAgregarBurbuja('assistant', '-s-️ ' + (data.error || 'Error desconocido. Intenta de nuevo.'));
                    }
                    return;
                }

                const respuesta = data.respuesta || '';
                iaAgregarBurbuja('assistant', respuesta);
                iaHistory.push({ role: 'assistant', content: respuesta });

                // Limitar historial local
                if (iaHistory.length > 30) iaHistory = iaHistory.slice(-30);
            })
            .catch(() => {
                document.getElementById('iaTyping').style.display = 'none';
                iaBusy = false;
                iaAgregarBurbuja('assistant', '-s-️ Error de conexión. Verifica que el servidor esté activo.');
            });
    };

    // -? Enter para enviar --------------------------------------------?
    window.iaKeyDown = function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            iaEnviar();
        }
    };

    // -? Renderizar burbuja -------------------------------------------?
    function iaAgregarBurbuja(role, texto) {
        const zona  = document.getElementById('iaMessages');
        const propio = role === 'user';

        const wrap = document.createElement('div');
        wrap.style.cssText = [
            'display:flex', 'flex-direction:column', 'max-width:86%',
            propio
                ? 'align-self:flex-end; align-items:flex-end'
                : 'align-self:flex-start; align-items:flex-start'
        ].join(';');

        // Avatar IA
        let inner = '';
        if (!propio) {
            inner += `<div style="width:22px;height:22px;border-radius:50%;
                         background:linear-gradient(135deg,#9b865f,#B19A6D);
                         display:flex;align-items:center;justify-content:center;
                         font-size:0.65rem;color:#fff;margin-bottom:3px;flex-shrink:0;">
                         <i class="fa-solid fa-robot"></i></div>`;
        }

        const bgOwn   = 'linear-gradient(135deg,#662331,#8b2f42)';
        const bgOther = '#f0ece8';
        const clrOther = '#111827';
        const radOwn   = '14px 14px 3px 14px';
        const radOther = '14px 14px 14px 3px';

        // Formato básico de markdown: **negrita**, saltos de línea, numeración
        const formatted = iaFormatText(texto);

        inner += `<div style="padding:9px 13px;
                     background:${propio ? bgOwn : bgOther};
                     border-radius:${propio ? radOwn : radOther};
                     color:${propio ? '#fff' : clrOther};
                     font-size:0.82rem; line-height:1.5;
                     word-break:break-word;">${formatted}</div>`;

        // Hora
        const hora = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        inner += `<span style="font-size:0.63rem;color:#9ca3af;padding:2px 2px 0;">${hora}</span>`;

        wrap.innerHTML = inner;
        zona.appendChild(wrap);
        iaMostrarScroll();
    }

    // -? Formato básico de texto --------------------------------------?
    function iaFormatText(texto) {
        let s = iaEscape(texto);
        // **negrita**
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Saltos de línea
        s = s.replace(/\n/g, '<br>');
        return s;
    }

    function iaEscape(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function iaMostrarScroll() {
        const zona = document.getElementById('iaMessages');
        if (zona) zona.scrollTop = zona.scrollHeight;
    }

    // -? Drag & Drop --------------------------------------------------?
    (function initIaDrag() {
        const panel  = document.getElementById('iaPanel');
        const handle = document.getElementById('iaPanelHeader');
        if (!panel || !handle) return;

        let dragActive = false;
        let startX, startY, origLeft, origTop;

        handle.addEventListener('mousedown', function (e) {
            if (e.target.closest('button')) return;
            dragActive = true;
            handle.style.cursor = 'grabbing';
            const rect = panel.getBoundingClientRect();
            startX = e.clientX; startY = e.clientY;
            origLeft = rect.left; origTop = rect.top;
            panel.style.right  = 'auto';
            panel.style.bottom = 'auto';
            panel.style.left   = origLeft + 'px';
            panel.style.top    = origTop  + 'px';
            e.preventDefault();
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragActive) return;
            let newLeft = origLeft + (e.clientX - startX);
            let newTop  = origTop  + (e.clientY - startY);
            const pw = panel.offsetWidth;
            const ph = panel.offsetHeight;
            newLeft = Math.max(0, Math.min(newLeft, window.innerWidth - pw));
            newTop  = Math.max(0, Math.min(newTop,  window.innerHeight - ph));
            panel.style.left = newLeft + 'px';
            panel.style.top  = newTop  + 'px';
        });

        document.addEventListener('mouseup', function () {
            if (!dragActive) return;
            dragActive = false;
            handle.style.cursor = 'grab';
        });
    })();

})();

    // -? Ocultar Loader al cargar la página ---------------------------?
    window.addEventListener('load', function () {
        const loader = document.getElementById('globalLoader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 300);
        }
    });

// -? Dark Mode -----------------------------------------------------?
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
                    style="position:relative; border:none; cursor:pointer;">
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

