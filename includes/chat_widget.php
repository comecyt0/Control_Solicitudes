<?php
/**
 * COMECyT — Universal Chat & AI Assistant Widget
 * Reusable component for Admins, Users, and Social Service
 */

// Personalización de Chat por Área (si no viene definida)
if (!isset($chat_area_label)) {
    $chat_area_label = 'Equipo TI';
    if (isset($_SESSION['user_area'])) {
        $chat_area_label = 'Equipo de ' . $_SESSION['user_area'];
    } elseif (isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] !== 'sistemas') {
        $chat_area_label = 'Administración';
    }
}

// Cargar preferencia de dark mode (si no viene definida)
$darkMode = $darkMode ?? (int) ($_SESSION['admin_dark_mode'] ?? $_SESSION['user_dark_mode'] ?? 0);
?>

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

        <!-- Tooltip de Emojis -->
        <div id="chatEmojiPicker" style="display:none; position:absolute; bottom:60px; right:12px; width:260px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); z-index:1000; padding:12px;">
            <div style="font-size:0.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                <span>Selector de Emojis</span>
                <i class="fa-solid fa-face-smile"></i>
            </div>
            <div id="chatEmojiGrid" style="display:grid; grid-template-columns:repeat(8,1fr); gap:4px; font-size:1.25rem; text-align:center; max-height:180px; overflow-y:auto;">
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
            </div>
        </div>

        <div id="chatReactionPicker" style="display:none; position:fixed; background:#fff; border:1px solid #e5e7eb; border-radius:24px; box-shadow:0 8px 24px rgba(0,0,0,0.12); z-index:10000; padding:4px 8px; display:flex; gap:4px; align-items:center;"></div>

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
     MODALS: Tarea / Evento
     ================================================================ -->
<div id="modalTareaOverlay" onclick="cerrarModalTarea()" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(102,35,49,0.35); backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:20px;">
    <div onclick="event.stopPropagation()" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px; width:100%; max-width:420px; box-shadow:0 24px 64px rgba(102,35,49,0.25); font-family:Inter,'Segoe UI',system-ui,sans-serif;">
        <h3 style="margin:0 0 18px; font-size:1rem; color:#662331; font-weight:700; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-list-check"></i> Nueva Tarea Kanban</h3>
        <div style="margin-bottom:12px;"><label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Título *</label><input type="text" id="chatTareaTitulo" placeholder="¿Qué hay que hacer?" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; color:#111827; font-size:0.85rem; outline:none; box-sizing:border-box;"></div>
        <div style="margin-bottom:12px;"><label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Descripción</label><textarea id="chatTareaDesc" rows="2" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; color:#111827; font-size:0.85rem; outline:none; resize:vertical; font-family:inherit; box-sizing:border-box;"></textarea></div>
        <div style="margin-bottom:12px;"><label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Asignar a</label><select id="chatTareaAsignado" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; color:#111827; font-size:0.85rem; outline:none; box-sizing:border-box;"><option value="">-- Sin asignar --</option></select></div>
        <div style="margin-bottom:18px; display:flex; align-items:center; gap:10px;"><label style="font-size:0.8rem; font-weight:600; color:#374151;">Color</label><input type="color" id="chatTareaColor" value="#662331" style="height:34px; width:56px; border-radius:6px; border:1px solid #d1d5db; cursor:pointer; padding:2px;"></div>
        <div style="display:flex; gap:10px;"><button onclick="confirmarTarea()" style="flex:1; padding:9px 16px; background:linear-gradient(135deg,#662331,#8b2f42); color:#fff; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer; font-family:inherit;">Crear Tarea</button><button onclick="cerrarModalTarea()" style="padding:9px 16px; background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:8px; font-size:0.85rem; cursor:pointer; font-family:inherit;">Cancelar</button></div>
    </div>
</div>

<div id="modalEventoOverlay" onclick="cerrarModalEvento()" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(102,35,49,0.35); backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:20px;">
    <div onclick="event.stopPropagation()" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px; width:100%; max-width:420px; box-shadow:0 24px 64px rgba(177,154,109,0.3); font-family:Inter,'Segoe UI',system-ui,sans-serif;">
        <h3 style="margin:0 0 18px; font-size:1rem; color:#7d6535; font-weight:700; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-calendar-plus"></i> Nuevo Evento</h3>
        <div style="margin-bottom:12px;"><label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Título *</label><input type="text" id="chatEventoTitulo" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; color:#111827; font-size:0.85rem; outline:none; box-sizing:border-box;"></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
            <div><label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Inicio *</label><input type="datetime-local" id="chatEventoInicio" style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; color:#111827; font-size:0.8rem; outline:none; box-sizing:border-box;"></div>
            <div><label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:4px;">Fin</label><input type="datetime-local" id="chatEventoFin" style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; color:#111827; font-size:0.8rem; outline:none; box-sizing:border-box;"></div>
        </div>
        <div style="display:flex; gap:10px; margin-top:18px;"><button onclick="confirmarEvento()" style="flex:1; padding:9px 16px; background:linear-gradient(135deg,#9b865f,#B19A6D); color:#fff; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer; font-family:inherit;">Agendar</button><button onclick="cerrarModalEvento()" style="padding:9px 16px; background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:8px; font-size:0.85rem; cursor:pointer; font-family:inherit;">Cancelar</button></div>
    </div>
</div>

<!-- ================================================================
     PANEL ASISTENTE IA
     ================================================================ -->
<div id="iaPanel" style="display:none; position:fixed; right:350px; top:60px; width:340px; height:500px; background:#fff; z-index:9998; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); flex-direction:column; font-family:Inter,sans-serif;">
    <div id="iaPanelHeader" style="padding:12px 15px; background:linear-gradient(135deg,#9b865f,#B19A6D); border-radius:12px 12px 0 0; display:flex; align-items:center; gap:10px; cursor:grab;">
        <div style="width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; color:#fff;"><i class="fa-solid fa-robot"></i></div>
        <div style="flex:1; display:flex; flex-direction:column;"><span style="font-size:0.9rem; font-weight:700; color:#fff;">Asistente IA</span></div>
        <button onclick="toggleAsistenteIA()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:26px; height:26px; border-radius:50%; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="iaMessages" style="flex:1; padding:15px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; background:#f9fafb;"></div>
    <div id="iaTyping" style="display:none; padding:8px 15px; font-size:0.75rem; color:#6b7280; background:#f9fafb; font-style:italic;"><i class="fa-solid fa-circle-notch fa-spin"></i> Escribiendo...</div>
    <div style="padding:10px 15px; border-top:1px solid #e5e7eb; background:#fff; display:flex; gap:8px;">
        <textarea id="iaInput" placeholder="Pregunta algo..." rows="1" style="flex:1; border:1px solid #d1d5db; border-radius:12px; padding:8px 12px; outline:none; font-size:0.85rem; resize:none;" onkeydown="iaKeyDown(event)"></textarea>
        <button onclick="iaEnviar()" style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#9b865f,#B19A6D); color:#fff; border:none; cursor:pointer; align-self:flex-end;"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
</div>

<script>
(function () {
    const ADMIN_ID   = '<?= $_SESSION['admin_id'] ? "A".$_SESSION['admin_id'] : "P".($_SESSION['user_id'] ?? 0) ?>';
    const API        = '<?= BASE_URL ?>admin/api/chat.php';
    const POLL_MS    = 6000;
    const BG_POLL_MS = 15000;

    let chatOpen = false, canalActual = null, ultimoId = 0, noLeidosCnt = 0;
    let pollingTimer = null, bgPollingTimer = null, listaAdmins = [];
    let csrfToken = '<?= $_SESSION["csrf_token"] ?? "" ?>';
    const ADMIN_AREA = <?= (int)($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0) ?>;

    const AVATAR_COLORS = ['#662331','#7c3aed','#059669','#b45309','#0369a1','#be185d','#15803d','#c2410c','#1d4ed8','#6b21a8'];

    // Inyectar estilos
    const s = document.createElement('style');
    s.textContent = `@keyframes chatBadgePulse { 0%,100% { transform:scale(1); } 50% { transform:scale(1.25); } } #chatBadge { animation:chatBadgePulse 1.8s ease-in-out infinite; } .chat-dm-btn { transition:background .15s; } .chat-dm-btn:hover { background:rgba(255,255,255,0.13) !important; } .chat-dm-btn.activo { background:rgba(255,255,255,0.22) !important; } #chatBtnGrupal.activo { background:rgba(255,255,255,0.3) !important; } .chat-reaccion-chip { background:rgba(255,255,255,0.8); border:1px solid #e5e7eb; border-radius:10px; padding:1px 6px; font-size:0.75rem; display:flex; align-items:center; gap:3px; cursor:pointer; } @media (max-width: 650px) { #chatPanel { width: calc(100% - 20px) !important; height: calc(100% - 100px) !important; right: 10px !important; left: 10px !important; top: 80px !important; } }`;
    document.head.appendChild(s);

    function mostrarBadge(n) { const b = document.getElementById('chatBadge'); if (b) { b.textContent = n > 99 ? '99+' : n; b.style.display = 'flex'; } }
    function ocultarBadge() { const b = document.getElementById('chatBadge'); if (b) b.style.display = 'none'; }

    window.toggleChat = function () {
        chatOpen = !chatOpen;
        const panel = document.getElementById('chatPanel');
        panel.style.display = chatOpen ? 'flex' : 'none';
        if (chatOpen) { cargarAdmins(); cargarMensajes(true, true); iniciarPolling(); ocultarBadge(); noLeidosCnt = 0; }
        else { detenerPolling(); iniciarBgPolling(); }
    };

    window.chatSeleccionarCanal = function (id) { canalActual = id; ultimoId = 0; cargarMensajes(true, false); };

    function iniciarPolling() { detenerPolling(); pollingTimer = setInterval(() => cargarMensajes(false, false), POLL_MS); }
    function detenerPolling() { if (pollingTimer) clearInterval(pollingTimer); }
    function iniciarBgPolling() { detenerBgPolling(); bgPollingTimer = setInterval(verificarNoLeidos, BG_POLL_MS); }
    function detenerBgPolling() { if (bgPollingTimer) clearInterval(bgPollingTimer); }

    function verificarNoLeidos() {
        fetch(API + '?accion=listar&desde=' + ultimoId).then(r => r.json()).then(data => {
            if (!data.ok || !data.mensajes.length) return;
            noLeidosCnt += data.mensajes.length;
            mostrarBadge(noLeidosCnt);
            
            // Trigger universal notification sound if available
            if (window.COMECyTNotificationBell && typeof window.COMECyTNotificationBell.notify === 'function') {
                window.COMECyTNotificationBell.notify();
            } else if (typeof window.sonido === 'function') {
                window.sonido('chat');
            }
        });
    }

    function cargarMensajes(scroll, first) {
        let url = API + '?accion=listar&desde=' + ultimoId;
        if (canalActual) url += '&destinatario=' + canalActual;
        fetch(url).then(r => r.json()).then(data => {
            if (!data.ok || !data.mensajes.length) return;
            const zona = document.getElementById('chatMessages');
            data.mensajes.forEach(m => {
                const bubble = document.createElement('div');
                bubble.style.cssText = 'padding:6px 10px; margin:4px 0; border-radius:10px; max-width:85%;' + (String(m.admin_id) === String(ADMIN_ID) ? 'align-self:flex-end; background:#662331; color:#fff;' : 'align-self:flex-start; background:#f0f0f0; color:#111827;');
                bubble.innerHTML = `<div style="font-size:0.65rem; opacity:0.7;">${m.admin_nombre}</div><div>${m.mensaje}</div>`;
                zona.appendChild(bubble);
                ultimoId = Math.max(ultimoId, parseInt(m.id));
            });
            if (scroll) zona.scrollTop = zona.scrollHeight;
        });
    }

    window.enviarMensaje = function () {
        const inp = document.getElementById('chatInput');
        const txt = inp.value.trim();
        if (!txt) return;
        const fd = new FormData();
        fd.append('accion', 'enviar'); fd.append('mensaje', txt); fd.append('csrf_token', csrfToken);
        if (canalActual) fd.append('destinatario', canalActual);
        fetch(API, { method: 'POST', body: fd }).then(() => { inp.value = ''; cargarMensajes(true, false); });
    };

    window.chatKeyDown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensaje(); } };

    function cargarAdmins() {
        fetch(API + '?accion=admins').then(r => r.json()).then(d => {
            const list = document.getElementById('chatAdminList');
            list.innerHTML = '';
            d.admins.forEach(a => {
                if (String(a.id) === String(ADMIN_ID)) return;
                const b = document.createElement('button');
                b.className = 'chat-dm-btn';
                b.style.cssText = 'width:100%; text-align:left; background:none; border:none; color:#fff; padding:8px; cursor:pointer;';
                b.textContent = a.nombre;
                b.onclick = () => chatSeleccionarCanal(a.id);
                list.appendChild(b);
            });
        });
    }

    iniciarBgPolling();
})();

// AI Assistant
(function() {
    const API = '<?= BASE_URL ?>admin/api/agente_ia.php';
    window.toggleAsistenteIA = () => {
        const p = document.getElementById('iaPanel');
        p.style.display = (p.style.display === 'none' ? 'flex' : 'none');
    };
    window.iaEnviar = () => {
        const inp = document.getElementById('iaInput');
        const txt = inp.value.trim();
        if (!txt) return;
        const zona = document.getElementById('iaMessages');
        const b = document.createElement('div');
        b.style.cssText = 'align-self:flex-end; background:#662331; color:#fff; padding:8px; border-radius:10px; margin:4px;';
        b.textContent = txt;
        zona.appendChild(b);
        inp.value = '';
        const fd = new FormData(); fd.append('mensaje', txt);
        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            const r = document.createElement('div');
            r.style.cssText = 'align-self:flex-start; background:#f0f0f0; padding:8px; border-radius:10px; margin:4px;';
            r.textContent = d.respuesta || 'Error';
            zona.appendChild(r);
            zona.scrollTop = zona.scrollHeight;
        });
    };
    window.iaKeyDown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); iaEnviar(); } };
})();

document.addEventListener('DOMContentLoaded', () => {
    if (new URLSearchParams(window.location.search).get('openChat') === '1') {
        setTimeout(() => { if (typeof toggleChat === 'function') toggleChat(); }, 500);
    }
});
</script>
