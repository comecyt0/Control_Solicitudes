<?php
/**
 * COMECyT — Universal Chat & AI Assistant Widget (v5.2 Platinum)
 * Rediseño Profesional: Platinum Glassmorphism, Centrado de Modales y UX Mejorada
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
/* ── Platinum Design System ── */
:root {
    --chat-accent: #B19A6D;
    --chat-primary: #662331;
    --chat-primary-light: #8b2f42;
    --chat-bg-dark: #0f172a;
    --chat-text-main: #1e293b;
    --chat-text-muted: #64748b;
    --chat-glass: rgba(255, 255, 255, 0.92);
    --chat-glass-dark: rgba(255, 255, 255, 0.98);
    --chat-shadow-premium: 0 25px 50px -12px rgba(102, 35, 49, 0.25);
    --chat-radius-xl: 24px;
}

/* ── Panel Principal ── */
#chatPanel {
    display: none;
    position: fixed;
    top: 85px; 
    right: 25px;
    width: 680px;
    height: 600px;
    z-index: 9999;
    background: var(--chat-glass);
    backdrop-filter: blur(25px) saturate(180%);
    -webkit-backdrop-filter: blur(25px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: var(--chat-radius-xl);
    box-shadow: var(--chat-shadow-premium);
    flex-direction: row;
    overflow: hidden;
    font-family: 'Outfit', 'Inter', system-ui, sans-serif;
    animation: chatPlatinumIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes chatPlatinumIn { 
    from { transform: translateY(30px) scale(0.92); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

/* ── Sidebar Estilo Dark Platinum ── */
#chatSidebar {
    width: 220px;
    background: linear-gradient(165deg, #3d1520 0%, #662331 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.c-sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.c-sidebar-header h4 {
    margin: 0; font-size: 0.95rem; font-weight: 700;
    display: flex; align-items: center; gap: 10px;
    letter-spacing: -0.01em;
}

#chatAdminList {
    flex: 1;
    padding: 15px 10px;
    overflow-y: auto;
}

.c-contact-item {
    width: 100%; border: none; background: transparent;
    padding: 12px 14px; border-radius: 14px;
    color: rgba(255,255,255,0.7); display: flex; align-items: center; gap: 12px;
    cursor: pointer; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: left; margin-bottom: 2px;
}

.c-contact-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
.c-contact-item.active { 
    background: rgba(255,255,255,0.15); color: #fff; 
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1);
}

.c-avatar {
    width: 36px; height: 36px; border-radius: 12px;
    background: rgba(255,255,255,0.15); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

/* ── Main Area ── */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(255,255,255,0.45);
    min-width: 0;
}

.c-header {
    padding: 18px 24px;
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex; align-items: center; gap: 15px;
}

#chatMessages {
    flex: 1; padding: 25px; overflow-y: auto;
    display: flex; flex-direction: column; gap: 15px;
    background: radial-gradient(at 0% 0%, rgba(177, 154, 109, 0.05) 0%, transparent 50%),
                radial-gradient(at 100% 100%, rgba(102, 35, 49, 0.05) 0%, transparent 50%);
}

/* ── Burbujas Platinum ── */
.msg-bubble {
    max-width: 78%; padding: 12px 18px; border-radius: 20px;
    font-size: 0.92rem; line-height: 1.55; position: relative;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    transition: transform 0.2s ease;
}

.msg-bubble.me {
    align-self: flex-end;
    background: linear-gradient(135deg, var(--chat-primary) 0%, var(--chat-primary-light) 100%);
    color: #fff; border-bottom-right-radius: 4px;
}

.msg-bubble.other {
    align-self: flex-start;
    background: #fff; color: var(--chat-text-main);
    border-bottom-left-radius: 4px; border: 1px solid rgba(0,0,0,0.06);
}

.msg-meta {
    font-size: 0.7rem; color: var(--chat-text-muted);
    margin-bottom: 5px; display: block; font-weight: 600;
}

.msg-type-card {
    padding: 10px 14px; border-radius: 12px;
    background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; gap: 12px; margin-top: 8px;
}

/* ── Input Bar Float View ── */
.c-footer {
    padding: 15px 25px 25px;
    background: transparent;
}

.c-input-container {
    background: #fff;
    border-radius: 18px;
    padding: 8px 12px;
    display: flex; align-items: flex-end; gap: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.05);
}

#chatInput {
    flex: 1; border: none; background: transparent; padding: 10px 5px;
    font-size: 0.95rem; max-height: 120px; resize: none; outline: none;
    font-family: inherit; color: var(--chat-text-main);
}

.btn-send-platinum {
    width: 44px; height: 44px; border-radius: 14px;
    background: var(--chat-primary); color: #fff;
    border: none; cursor: pointer; transition: 0.3s;
    display: flex; align-items: center; justify-content: center;
}

.btn-send-platinum:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 20px rgba(102, 35, 49, 0.3);
    background: var(--chat-primary-light);
}

/* ── Modales Platinum ── */
.platinum-overlay {
    position: fixed; inset: 0; z-index: 10000;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px; opacity: 0; pointer-events: none;
    transition: opacity 0.3s ease;
}

.platinum-overlay.open { opacity: 1; pointer-events: all; }

.platinum-modal {
    background: #fff; width: 100%; max-width: 440px;
    border-radius: 28px; padding: 35px;
    box-shadow: 0 40px 100px rgba(0,0,0,0.3);
    transform: scale(0.9) translateY(20px);
    transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.platinum-overlay.open .platinum-modal { transform: scale(1) translateY(0); }

.premium-input {
    width: 100%; padding: 12px 16px; border-radius: 14px;
    border: 1px solid #e2e8f0; background: #f8fafc;
    font-size: 0.95rem; margin-top: 6px; outline: none;
    transition: 0.2s;
}

.premium-input:focus { border-color: var(--chat-primary); background: #fff; box-shadow: 0 0 0 4px rgba(102,35,49,0.05); }

.btn-premium-full {
    width: 100%; padding: 15px; border: none; border-radius: 16px;
    background: var(--chat-primary); color: #fff; font-weight: 700;
    font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px;
}

.btn-premium-full:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 35, 49, 0.3); }

/* ── Responsive ── */
@media (max-width: 768px) {
    #chatPanel { top:0; right:0; left:0; bottom:0; width:100% !important; height:100% !important; border-radius:0; padding-top: 60px; }
    #chatSidebar { width: 100%; position: absolute; inset: 0; z-index: 100; transform: translateX(0); }
    #chatSidebar.hidden { transform: translateX(-100%); }
    .mobile-back { display: flex !important; }
}
</style>

<div id="chatPanel">
    <!-- Sidebar -->
    <div id="chatSidebar">
        <div class="c-sidebar-header">
            <h4><i class="fa-solid fa-comments"></i> <?= $chat_area_label ?></h4>
            <span style="opacity:0.5; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 5px; display: block;">Platinum Edition v5.2</span>
        </div>
        <div id="chatAdminList">
            <button class="c-contact-item active" id="chatGeneralBtn" onclick="chatSeleccionarCanal(null)">
                <div class="c-avatar" style="background:var(--chat-accent)"><i class="fa-solid fa-users"></i></div>
                <div style="flex:1;">
                    <div style="font-weight:700; font-size:0.85rem;">General</div>
                    <div style="font-size:0.65rem; opacity:0.6;">Equipo Completo</div>
                </div>
            </button>
            <div style="margin-top: 25px; padding: 0 10px;">
                <p style="font-size: 0.6rem; opacity: 0.4; text-transform: uppercase; font-weight: 800; letter-spacing: 0.1em; padding-left: 14px; margin-bottom: 12px;">Mensajes Directos</p>
                <div id="platDmsList" style="display: flex; flex-direction: column; gap: 4px;"></div>
            </div>
        </div>
        
        <!-- Acciones Rápidas con Nuevo Diseño -->
        <div style="padding: 15px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.05);">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <button onclick="abrirModPlatinum('tarea')" style="background:rgba(255,255,255,0.1); border:none; border-radius:12px; color:#fff; padding:10px; cursor:pointer; font-size:0.7rem; transition:0.2s;">
                    <i class="fa-solid fa-list-check" style="font-size:1rem; margin-bottom:4px;"></i><br>Tarea
                </button>
                <button onclick="abrirModPlatinum('evento')" style="background:rgba(255,255,255,0.1); border:none; border-radius:12px; color:#fff; padding:10px; cursor:pointer; font-size:0.7rem; transition:0.2s;">
                    <i class="fa-solid fa-calendar-plus" style="font-size:1rem; margin-bottom:4px;"></i><br>Evento
                </button>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="chat-main">
        <div class="c-header">
            <button class="mobile-back" style="display:none; background:none; border:none; font-size:1.2rem; color:var(--chat-primary); cursor:pointer;" onclick="volverALista()"><i class="fa-solid fa-chevron-left"></i></button>
            <div id="platActiveAvatar" class="c-avatar" style="background:var(--chat-primary)"><i class="fa-solid fa-users"></i></div>
            <div style="flex:1; min-width:0;">
                <strong id="platActiveName" style="font-size: 1rem; color: var(--chat-text-main); display: block; overflow: hidden; text-overflow: ellipsis;">General de Área</strong>
                <p id="platActiveStatus" style="font-size: 0.75rem; color: var(--chat-text-muted); margin: 0;">Activo ahora</p>
            </div>
            <button onclick="toggleChat()" style="background:none; border:none; color:#cbd5e1; cursor:pointer; font-size:1.4rem; padding: 5px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div id="chatMessages"></div>

        <div class="c-footer">
            <div class="c-input-container">
                <textarea id="chatInput" placeholder="Escribe tu mensaje..." onkeydown="chatKeyDown(event)" rows="1"></textarea>
                <button class="btn-send-platinum" onclick="enviarMensaje()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Modales Centrados y Platinum -->
<div id="platModalOverlay" class="platinum-overlay" onclick="cerrarModPlatinum()">
    <div class="platinum-modal" onclick="event.stopPropagation()">
        
        <div id="platModalTarea" style="display:none;">
            <div style="text-align:center; margin-bottom:25px;">
                <div style="width:50px; height:50px; background:rgba(102,35,49,0.1); color:var(--chat-primary); border-radius:16px; display:inline-flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:15px;">
                    <i class="fa-solid fa-thumbtack"></i>
                </div>
                <h3 style="margin:0; color:var(--chat-primary); font-size:1.4rem;">Nueva Tarea Kanban</h3>
                <p style="color:var(--chat-text-muted); font-size:0.85rem; margin-top:5px;">Añade una actividad para el equipo</p>
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="font-size:0.8rem; font-weight:700; color:var(--chat-text-main);">Título de la Tarea</label>
                <input type="text" id="ptTitle" placeholder="Ej: Mantenimiento Preventivo" class="premium-input">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="font-size:0.8rem; font-weight:700; color:var(--chat-text-main);">Descripción (Opcional)</label>
                <textarea id="ptDesc" placeholder="Detalles adicionales..." class="premium-input" style="height:80px; resize:none;"></textarea>
            </div>
            
            <div style="margin-bottom:25px;">
                <label style="font-size:0.8rem; font-weight:700; color:var(--chat-text-main);">Responsable</label>
                <select id="ptAsignado" class="premium-input"></select>
            </div>
            
            <button onclick="guardarTareaPlat()" class="btn-premium-full">Crear Tarea</button>
        </div>

        <div id="platModalEvento" style="display:none;">
            <div style="text-align:center; margin-bottom:25px;">
                <div style="width:50px; height:50px; background:rgba(177,154,109,0.1); color:var(--chat-accent); border-radius:16px; display:inline-flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:15px;">
                    <i class="fa-solid fa-calendar-star"></i>
                </div>
                <h3 style="margin:0; color:var(--chat-accent); font-size:1.4rem;">Agendar Evento</h3>
                <p style="color:var(--chat-text-muted); font-size:0.85rem; margin-top:5px;">Registra una reunión o fecha clave</p>
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="font-size:0.8rem; font-weight:700; color:var(--chat-text-main);">Título del Evento</label>
                <input type="text" id="peTitle" placeholder="Ej: Reunión Mensual" class="premium-input">
            </div>
            
            <div style="margin-bottom:25px;">
                <label style="font-size:0.8rem; font-weight:700; color:var(--chat-text-main);">Fecha y Hora</label>
                <input type="datetime-local" id="peStart" class="premium-input">
            </div>
            
            <button onclick="guardarEventoPlat()" class="btn-premium-full" style="background:var(--chat-accent);">Agendar Evento</button>
        </div>

        <button onclick="cerrarModPlatinum()" style="background:none; border:none; color:var(--chat-text-muted); width:100%; margin-top:15px; cursor:pointer; font-weight:600; font-size:0.85rem;">Cerrar sin guardar</button>
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
            cargarAdminsPlatinum();
            cargarMensajesPlatinum(true);
            iniciarPollingPlat();
            badgeCnt = 0;
            const b = document.getElementById('chatBadge'); if(b) b.style.display = 'none';
        } else {
            detenerPollingPlat();
        }
    };

    window.chatSeleccionarCanal = function(id, nombre = 'General de Área') {
        canalActual = id;
        ultimoId = 0;
        document.getElementById('chatMessages').innerHTML = '';
        document.getElementById('platActiveName').textContent = nombre;
        document.getElementById('platActiveStatus').textContent = id ? 'Mensaje Directo' : 'Equipo Institucional';
        
        const avatar = document.getElementById('platActiveAvatar');
        avatar.innerHTML = id ? `<div class="c-avatar">${nombre.charAt(0).toUpperCase()}</div>` : `<i class="fa-solid fa-users"></i>`;

        document.querySelectorAll('.c-contact-item').forEach(el => el.classList.remove('active'));
        if (!id) document.getElementById('chatGeneralBtn').classList.add('active');

        cargarMensajesPlatinum(true);
        if (window.innerWidth < 768) document.getElementById('chatSidebar').classList.add('hidden');
    };

    function iniciarPollingPlat() { detenerPollingPlat(); polling = setInterval(() => cargarMensajesPlatinum(false), 5000); }
    function detenerPollingPlat() { if (polling) clearInterval(polling); }

    window.volverALista = () => document.getElementById('chatSidebar').classList.remove('hidden');

    function cargarAdminsPlatinum() {
        fetch(`${API}?accion=admins`).then(r => r.json()).then(res => {
            if (!res.ok) return;
            const list = document.getElementById('platDmsList');
            const select = document.getElementById('ptAsignado');
            list.innerHTML = '';
            select.innerHTML = '<option value="">-- Cualquiera del equipo --</option>';

            res.admins.forEach(adm => {
                if (adm.id === ADMIN_ID) return;
                const btn = document.createElement('button');
                btn.className = `c-contact-item ${canalActual === adm.id ? 'active' : ''}`;
                btn.innerHTML = `<div class="c-avatar">${adm.inicial}</div><div style="flex:1;"><div style="font-weight:700; font-size:0.85rem;">${adm.nombre}</div><div style="font-size:0.65rem; opacity:0.6;">${adm.rol || 'Staff'}</div></div>`;
                btn.onclick = () => chatSeleccionarCanal(adm.id, adm.nombre);
                list.appendChild(btn);

                const opt = document.createElement('option');
                opt.value = adm.id.replace('A', '');
                opt.textContent = adm.nombre;
                select.appendChild(opt);
            });
        });
    }

    function cargarMensajesPlatinum(scroll = false) {
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
                    content = `<div class="msg-meta" style="${isMe ? 'color:rgba(255,255,255,0.7)' : ''}">${m.admin_nombre} • ${m.hora}</div>
                               <div class="msg-type-card" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);">
                               <i class="fa-solid fa-thumbtack"></i> <div><strong>${m.ref_titulo}</strong><br><small>Nueva Tarea Asignada</small></div></div>`;
                } else if (m.tipo === 'evento') {
                    content = `<div class="msg-meta" style="${isMe ? 'color:rgba(255,255,255,0.7)' : ''}">${m.admin_nombre} • ${m.hora}</div>
                               <div class="msg-type-card" style="background:rgba(177,154,109,0.15); border-color:rgba(177,154,109,0.3);">
                               <i class="fa-solid fa-calendar-check" style="color:var(--chat-accent)"></i> 
                               <div><strong>${m.ref_titulo}</strong><br><small>Evento en Calendario</small></div></div>`;
                } else {
                    content = `<span class="msg-meta" style="${isMe ? 'color:rgba(255,255,255,0.7)' : ''}">${m.admin_nombre} • ${m.hora}</span><div style="word-break:break-word;">${m.mensaje}</div>`;
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
            if (res.ok) { input.value = ''; cargarMensajesPlatinum(true); }
        });
    };

    window.chatKeyDown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensaje(); } };

    // Modales Platinum Centrados
    window.abrirModPlatinum = (tipo) => {
        document.getElementById('platModalOverlay').classList.add('open');
        document.getElementById('platModalTarea').style.display = tipo === 'tarea' ? 'block' : 'none';
        document.getElementById('platModalEvento').style.display = tipo === 'evento' ? 'block' : 'none';
        cargarAdminsPlatinum();
    };
    window.cerrarModPlatinum = () => document.getElementById('platModalOverlay').classList.remove('open');

    window.guardarTareaPlat = () => {
        const title = document.getElementById('ptTitle').value.trim();
        const desc = document.getElementById('ptDesc').value.trim();
        const asig = document.getElementById('ptAsignado').value;
        if (!title) return;

        const fd = new FormData();
        fd.append('accion', 'crear_tarea');
        fd.append('titulo', title);
        fd.append('descripcion', desc);
        fd.append('asignado_a', asig);
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.ok) { 
                cerrarModPlatinum(); 
                document.getElementById('ptTitle').value = '';
                document.getElementById('ptDesc').value = '';
                cargarMensajesPlatinum(true); 
            }
        });
    };

    window.guardarEventoPlat = () => {
        const title = document.getElementById('peTitle').value.trim();
        const start = document.getElementById('peStart').value;
        if (!title || !start) return;

        const fd = new FormData();
        fd.append('accion', 'crear_evento');
        fd.append('titulo', title);
        fd.append('fecha_inicio', start);
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.ok) { 
                cerrarModPlatinum(); 
                document.getElementById('peTitle').value = '';
                cargarMensajesPlatinum(true); 
            }
        });
    };

})();
</script>
