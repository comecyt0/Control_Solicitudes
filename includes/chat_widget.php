<?php
/**
 * COMECyT — Universal Chat & AI Assistant Widget (v5.1 Premium)
 * Rediseño Profesional: Glassmorphism, Responsividad y Gestión de Tareas/Eventos
 */

// Personalización de Chat por Área
if (!isset($chat_area_label)) {
    $chat_area_label = 'Equipo TI';
    if (isset($_SESSION['user_area'])) {
        $chat_area_label = 'Equipo de ' . $_SESSION['user_area'];
    } elseif (isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] !== 'sistemas') {
        $chat_area_label = 'Administración';
    }
}
$darkMode = $darkMode ?? (int) ($_SESSION['admin_dark_mode'] ?? $_SESSION['user_dark_mode'] ?? 0);
?>

<style>
/* ── Variables de Diseño Premium ── */
:root {
    --chat-primary: #662331;
    --chat-accent: #B19A6D;
    --chat-bg-dark: rgba(15, 23, 42, 0.98);
    --chat-glass: rgba(255, 255, 255, 0.9);
    --chat-border: rgba(255, 255, 255, 0.15);
    --chat-shadow: 0 20px 60px rgba(102, 35, 49, 0.18);
    --chat-radius: 20px;
}

#chatPanel {
    display: none;
    position: fixed;
    top: 75px; 
    right: 25px;
    width: 620px;
    height: 560px;
    z-index: 9999;
    background: var(--chat-glass);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--chat-border);
    border-radius: var(--chat-radius);
    box-shadow: var(--chat-shadow);
    flex-direction: row;
    overflow: hidden;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    animation: chatPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes chatPop { 
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

/* ── Sidebar: Glass Dark ── */
#chatSidebar {
    width: 200px;
    background: linear-gradient(180deg, #3d1520 0%, #662331 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,0.08);
}

.chat-sidebar-header { padding: 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.chat-sidebar-header h4 { margin: 0; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.chat-sidebar-header span { font-size: 0.65rem; opacity: 0.5; display: block; margin-top: 3px; }

#chatAdminList { flex: 1; padding: 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
.chat-contact-item {
    width: 100%; border: none; background: transparent; padding: 10px; border-radius: 12px;
    color: rgba(255,255,255,0.65); display: flex; align-items: center; gap: 10px; cursor: pointer;
    transition: 0.2s; text-align: left;
}
.chat-contact-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
.chat-contact-item.active { background: rgba(255,255,255,0.2); color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

.chat-avatar {
    width: 30px; height: 30px; border-radius: 10px; background: rgba(255,255,255,0.15);
    color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; flex-shrink: 0;
}

/* ── Main Chat Area ── */
.chat-main { flex: 1; display: flex; flex-direction: column; background: rgba(255,255,255,0.4); min-width: 0; }

.chat-header {
    padding: 14px 20px; background: rgba(255,255,255,0.85); border-bottom: 1px solid rgba(0,0,0,0.04);
    display: flex; align-items: center; gap: 12px;
}
.chat-header .chat-avatar { background: var(--chat-primary); }

#chatMessages {
    flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;
    background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0) 0%, rgba(255,255,255,0.5) 100%);
}

/* ── Burbujas Profesionales ── */
.msg-bubble {
    max-width: 82%; padding: 10px 14px; border-radius: 16px; font-size: 0.88rem; line-height: 1.45;
    position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.msg-bubble.me { align-self: flex-end; background: var(--chat-primary); color: #fff; border-bottom-right-radius: 3px; }
.msg-bubble.other { align-self: flex-start; background: #fff; color: #1e293b; border-bottom-left-radius: 3px; border: 1px solid rgba(0,0,0,0.05); }

.msg-meta { font-size: 0.65rem; opacity: 0.55; margin-bottom: 3px; display: block; font-weight: 500; }

.msg-type-card {
    padding: 8px 12px; border-radius: 10px; background: rgba(255,255,255,0.15); display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.1); margin-top: 5px; cursor: default;
}

/* ── Input y Barra inferior ── */
.chat-footer { padding: 12px 18px; background: #fff; border-top: 1px solid rgba(0,0,0,0.04); display: flex; align-items: flex-end; gap: 10px; }
.chat-input-wrapper { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 4px 12px; }
#chatInput { width: 100%; border: none; background: transparent; padding: 8px 0; font-size: 0.88rem; max-height: 80px; resize: none; outline: none; font-family: inherit; }

.btn-send { width: 38px; height: 38px; border-radius: 50%; background: var(--chat-primary); color: #fff; border: none; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
.btn-send:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,35,49,0.3); }

/* ── Responsive Móvil ── */
@media (max-width: 768px) {
    #chatPanel { top:0; right:0; left:0; bottom:0; width:100% !important; height:100% !important; border-radius:0; }
    #chatSidebar { width: 100%; position: absolute; inset: 0; z-index: 100; transform: translateX(0); transition: 0.3s ease; }
    #chatSidebar.hidden { transform: translateX(-100%); }
    .chat-main { width: 100%; }
    .mobile-back { display: flex !important; }
}
</style>

<div id="chatPanel">
    <!-- Sidebar -->
    <div id="chatSidebar">
        <div class="chat-sidebar-header">
            <h4><i class="fa-solid fa-comments"></i> <?= $chat_area_label ?></h4>
            <span>Chat Institucional v5.1</span>
        </div>
        <div id="chatAdminList">
            <button class="chat-contact-item active" onclick="chatSeleccionarCanal(null)">
                <div class="chat-avatar" style="background:var(--chat-accent)"><i class="fa-solid fa-users"></i></div>
                <span>General de Área</span>
            </button>
            <div style="margin-top: 15px; padding: 0 10px;">
                <p style="font-size: 0.6rem; opacity: 0.4; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">Directos</p>
                <div id="dmsList" style="margin-top: 8px; display: flex; flex-direction: column; gap: 2px;"></div>
            </div>
        </div>
        <div style="padding: 12px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; gap: 6px;">
            <button onclick="abrirModalWidget('tarea')" style="flex:1; background:rgba(255,255,255,0.1); border:none; border-radius:10px; color:#fff; padding:6px; cursor:pointer; font-size:0.65rem;">
                <i class="fa-solid fa-list-check"></i><br>Tarea
            </button>
            <button onclick="abrirModalWidget('evento')" style="flex:1; background:rgba(255,255,255,0.1); border:none; border-radius:10px; color:#fff; padding:6px; cursor:pointer; font-size:0.65rem;">
                <i class="fa-solid fa-calendar-alt"></i><br>Evento
            </button>
        </div>
    </div>

    <!-- Main -->
    <div class="chat-main">
        <div class="chat-header">
            <button class="mobile-back" style="display:none; background:none; border:none; font-size:1.1rem; color:var(--chat-primary); cursor:pointer;" onclick="volverALista()"><i class="fa-solid fa-chevron-left"></i></button>
            <div id="activeAvatar" class="chat-avatar"><i class="fa-solid fa-users"></i></div>
            <div style="flex:1; min-width:0;">
                <strong id="activeName" style="font-size: 0.9rem; color: #1e293b; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">General</strong>
                <p id="activeRole" style="font-size: 0.7rem; color: #94a3b8; margin: 0;">Equipos TI / Difusión</p>
            </div>
            <button onclick="toggleChat()" style="background:none; border:none; color:#cbd5e1; cursor:pointer; font-size:1.1rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div id="chatMessages"></div>

        <div class="chat-footer">
            <div class="chat-input-wrapper">
                <textarea id="chatInput" placeholder="Enviar mensaje..." onkeydown="chatKeyDown(event)" rows="1"></textarea>
            </div>
            <button class="btn-send" onclick="enviarMensaje()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<!-- Modales Modulares -->
<div id="chatModalOverlay" class="modal-blur" style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.5); backdrop-filter:blur(6px); align-items:center; justify-content:center;" onclick="cerrarModalesChat()">
    <div class="premium-card" style="width:100%; max-width:420px; background:#fff; border-radius:24px; padding:30px; box-shadow:0 30px 60px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
        
        <div id="modalTarea" style="display:none;">
            <h3 style="margin:0 0 20px; color:var(--chat-primary); font-size:1.2rem; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-thumbtack"></i> Nueva Tarea Kanban</h3>
            <div class="field" style="margin-bottom:15px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:6px;">Título de la tarea</label>
                <input type="text" id="tTitle" placeholder="Ej: Revisar servidor central" style="width:100%; padding:10px; border-radius:10px; border:1px solid #e2e8f0; outline:none;">
            </div>
            <div class="field" style="margin-bottom:15px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:6px;">Detalles</label>
                <textarea id="tDesc" placeholder="Describe brevemente lo que hay que hacer..." style="width:100%; padding:10px; border-radius:10px; border:1px solid #e2e8f0; outline:none; height:80px; resize:none;"></textarea>
            </div>
            <div class="field" style="margin-bottom:20px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:6px;">Responsable</label>
                <select id="tAssign" style="width:100%; padding:10px; border-radius:10px; border:1px solid #e2e8f0; outline:none;"></select>
            </div>
            <button onclick="guardarTareaChat()" style="width:100%; padding:14px; background:var(--chat-primary); color:#fff; border:none; border-radius:15px; font-weight:700; cursor:pointer;">Registrar Tarea</button>
        </div>

        <div id="modalEvento" style="display:none;">
            <h3 style="margin:0 0 20px; color:var(--chat-accent); font-size:1.2rem; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-calendar-day"></i> Agendar Evento</h3>
            <div class="field" style="margin-bottom:15px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:6px;">Título del Evento</label>
                <input type="text" id="eTitle" placeholder="Ej: Reunión Institucional" style="width:100%; padding:10px; border-radius:10px; border:1px solid #e2e8f0; outline:none;">
            </div>
            <div class="field" style="margin-bottom:20px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:6px;">Fecha y Hora</label>
                <input type="datetime-local" id="eStart" style="width:100%; padding:10px; border-radius:10px; border:1px solid #e2e8f0; outline:none;">
            </div>
            <button onclick="guardarEventoChat()" style="width:100%; padding:14px; background:var(--chat-accent); color:#fff; border:none; border-radius:15px; font-weight:700; cursor:pointer;">Agendar ahora</button>
        </div>

    </div>
</div>

<script>
(function() {
    const API = '<?= BASE_URL ?>admin/api/chat.php';
    const ADMIN_ID = '<?= $_SESSION['admin_id'] ? "A".$_SESSION['admin_id'] : "P".($_SESSION['user_id'] ?? 0) ?>';
    let chatOpen = false, canalActual = null, ultimoId = 0, polling = null, badgeCnt = 0;

    window.toggleChat = function() {
        chatOpen = !chatOpen;
        const panel = document.getElementById('chatPanel');
        panel.style.display = chatOpen ? 'flex' : 'none';
        
        if (chatOpen) {
            cargarAdmins();
            cargarMensajes(true);
            iniciarPolling();
            badgeCnt = 0;
            const b = document.getElementById('chatBadge'); if(b) b.style.display = 'none';
        } else {
            detenerPolling();
        }
    };

    window.chatSeleccionarCanal = function(id, nombre = 'General') {
        canalActual = id;
        ultimoId = 0;
        document.getElementById('chatMessages').innerHTML = '';
        document.getElementById('activeName').textContent = nombre;
        document.getElementById('activeRole').textContent = id ? 'Mensaje Directo' : 'Canal del Equipo';
        
        const avatar = document.getElementById('activeAvatar');
        avatar.innerHTML = id ? `<div class="chat-avatar">${nombre.charAt(0).toUpperCase()}</div>` : `<i class="fa-solid fa-users"></i>`;

        document.querySelectorAll('.chat-contact-item').forEach(el => el.classList.remove('active'));
        if (!id) document.querySelector('#chatAdminList > button').classList.add('active');

        cargarMensajes(true);
        if (window.innerWidth < 768) document.getElementById('chatSidebar').classList.add('hidden');
    };

    function iniciarPolling() { detenerPolling(); polling = setInterval(() => cargarMensajes(false), 5000); }
    function detenerPolling() { if (polling) clearInterval(polling); }

    window.volverALista = () => document.getElementById('chatSidebar').classList.remove('hidden');

    function cargarAdmins() {
        fetch(`${API}?accion=admins`).then(r => r.json()).then(res => {
            if (!res.ok) return;
            const list = document.getElementById('dmsList');
            const select = document.getElementById('tAssign');
            list.innerHTML = '';
            select.innerHTML = '<option value="">-- Cualquiera --</option>';

            res.admins.forEach(adm => {
                if (adm.id === ADMIN_ID) return;
                const btn = document.createElement('button');
                btn.className = `chat-contact-item ${canalActual === adm.id ? 'active' : ''}`;
                btn.innerHTML = `<div class="chat-avatar">${adm.inicial}</div><span>${adm.nombre}</span>`;
                btn.onclick = () => chatSeleccionarCanal(adm.id, adm.nombre);
                list.appendChild(btn);

                const opt = document.createElement('option');
                opt.value = adm.id.replace('A', '');
                opt.textContent = adm.nombre;
                select.appendChild(opt);
            });
        });
    }

    function cargarMensajes(scroll = false) {
        let url = `${API}?accion=listar&desde=${ultimoId}`;
        if (canalActual) url += `&destinatario=${canalActual}`;

        fetch(url).then(r => r.json()).then(res => {
            if (!res.ok || !res.mensajes.length) return;
            const zona = document.getElementById('chatMessages');
            res.mensajes.forEach(m => {
                const isMe = String(m.admin_id) === String(ADMIN_ID);
                const bubble = document.createElement('div');
                bubble.className = `msg-bubble ${isMe ? 'me' : 'other'}`;
                
                let content = ``;
                if (m.tipo === 'tarea') {
                    content = `<div class="msg-meta">${m.admin_nombre} • ${m.hora}</div>
                               <div class="msg-type-card"><i class="fa-solid fa-thumbtack"></i> <div><strong>${m.ref_titulo}</strong><br><small>Tarea Kanban registrada</small></div></div>`;
                } else if (m.tipo === 'evento') {
                    content = `<div class="msg-meta">${m.admin_nombre} • ${m.hora}</div>
                               <div class="msg-type-card" style="background:rgba(177,154,109,0.2); border-color:#B19A6D44;">
                               <i class="fa-solid fa-calendar-star" style="color:#B19A6D"></i> 
                               <div><strong>${m.ref_titulo}</strong><br><small>Evento agendado</small></div></div>`;
                } else {
                    content = `<span class="msg-meta">${m.admin_nombre} • ${m.hora}</span><div>${m.mensaje}</div>`;
                }
                
                bubble.innerHTML = content;
                zona.appendChild(bubble);
                ultimoId = Math.max(ultimoId, parseInt(m.id));
            });
            if (scroll) zona.scrollTop = zona.scrollHeight;

            if (!chatOpen) {
                badgeCnt += res.mensajes.length;
                const b = document.getElementById('chatBadge');
                if (b) { b.textContent = badgeCnt > 99 ? '99+' : badgeCnt; b.style.display = 'flex'; }
                if (window.COMECyTNotificationBell) window.COMECyTNotificationBell.notify();
            }
        });
    }

    window.enviarMensaje = function() {
        const input = document.getElementById('chatInput');
        const txt = input.value.trim();
        if (!txt) return;

        const fd = new FormData();
        fd.append('accion', 'enviar');
        fd.append('mensaje', txt);
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        if (canalActual) fd.append('destinatario', canalActual);

        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.ok) { input.value = ''; cargarMensajes(true); }
        });
    };

    window.chatKeyDown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensaje(); } };

    // Modales
    window.abrirModalWidget = (tipo) => {
        document.getElementById('chatModalOverlay').style.display = 'flex';
        document.getElementById('modalTarea').style.display = tipo === 'tarea' ? 'block' : 'none';
        document.getElementById('modalEvento').style.display = tipo === 'evento' ? 'block' : 'none';
        cargarAdmins();
    };
    window.cerrarModalesChat = () => document.getElementById('chatModalOverlay').style.display = 'none';

    window.guardarTareaChat = () => {
        const title = document.getElementById('tTitle').value.trim();
        const desc = document.getElementById('tDesc').value.trim();
        const asig = document.getElementById('tAssign').value;
        if (!title) return;

        const fd = new FormData();
        fd.append('accion', 'crear_tarea');
        fd.append('titulo', title);
        fd.append('descripcion', desc);
        fd.append('asignado_a', asig);
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.ok) { 
                cerrarModalesChat(); 
                document.getElementById('tTitle').value = '';
                cargarMensajes(true); 
            }
        });
    };

    window.guardarEventoChat = () => {
        const title = document.getElementById('eTitle').value.trim();
        const start = document.getElementById('eStart').value;
        if (!title || !start) return;

        const fd = new FormData();
        fd.append('accion', 'crear_evento');
        fd.append('titulo', title);
        fd.append('fecha_inicio', start);
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.ok) { 
                cerrarModalesChat(); 
                document.getElementById('eTitle').value = '';
                cargarMensajes(true); 
            }
        });
    };

})();
</script>
