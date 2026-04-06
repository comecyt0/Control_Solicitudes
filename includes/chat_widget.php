<?php
/**
 * COMECyT — Universal Chat & AI Assistant Widget (v5.3 Platinum)
 * Rediseño Profesional: Platinum Glassmorphism, FIX DE CENTRADO (Teleport) y UX Elite
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
/* ── Platinum V3 Design System ── */
:root {
    --chat-accent: #B19A6D;
    --chat-primary: #662331;
    --chat-primary-hover: #7d2a3c;
    --chat-glass: rgba(255, 255, 255, 0.94);
    --chat-shadow: 0 30px 60px -12px rgba(102, 35, 49, 0.3);
    --chat-radius: 24px;
}

#chatPanel {
    display: none;
    position: fixed;
    top: 85px; 
    right: 25px;
    width: 700px;
    height: 620px;
    z-index: 9999;
    background: var(--chat-glass);
    backdrop-filter: blur(30px) saturate(180%);
    -webkit-backdrop-filter: blur(30px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: var(--chat-radius);
    box-shadow: var(--chat-shadow);
    flex-direction: row;
    overflow: hidden;
    font-family: 'Outfit', 'Inter', sans-serif;
    animation: chatEliteIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes chatEliteIn { 
    from { transform: translateY(40px) scale(0.9); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

/* ── Sidebar: Profundidad y Contraste ── */
#chatSidebar {
    width: 230px;
    background: linear-gradient(170deg, #2d0f18 0%, #662331 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,0.05);
}

.plat-side-header {
    padding: 26px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

#chatAdminList {
    flex: 1;
    padding: 15px 10px;
    overflow-y: auto;
}

.plat-contact {
    width: 100%; border: none; background: transparent;
    padding: 12px 14px; border-radius: 16px;
    color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 12px;
    cursor: pointer; transition: all 0.3s;
    text-align: left; margin-bottom: 4px;
}

.plat-contact:hover { background: rgba(255,255,255,0.08); color: #fff; }
.plat-contact.active { 
    background: rgba(255,255,255,0.15); color: #fff; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.plat-avatar {
    width: 38px; height: 38px; border-radius: 13px;
    background: rgba(255,255,255,0.12); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.9rem; flex-shrink: 0;
}

/* ── Main Chat Area ── */
.chat-main { flex: 1; display: flex; flex-direction: column; background: rgba(255,255,255,0.3); min-width: 0; }

.plat-header {
    padding: 20px 25px;
    background: rgba(255,255,255,0.95);
    border-bottom: 1px solid rgba(0,0,0,0.04);
    display: flex; align-items: center; gap: 15px;
}

#chatMessages {
    flex: 1; padding: 25px; overflow-y: auto;
    display: flex; flex-direction: column; gap: 15px;
    background-image: radial-gradient(#66233108 1px, transparent 1px);
    background-size: 20px 20px;
}

/* ── Elite Bubbles ── */
.elite-msg {
    max-width: 80%; padding: 14px 18px; border-radius: 22px;
    font-size: 0.94rem; line-height: 1.5; position: relative;
    box-shadow: 0 5px 15px rgba(0,0,0,0.02);
}

.elite-msg.me {
    align-self: flex-end;
    background: linear-gradient(135deg, var(--chat-primary) 0%, #8b2f42 100%);
    color: #fff; border-bottom-right-radius: 5px;
}

.elite-msg.other {
    align-self: flex-start;
    background: #fff; color: #1e293b;
    border: 1px solid rgba(0,0,0,0.06); border-bottom-left-radius: 5px;
}

.elite-meta { font-size: 0.7rem; color: #94a3b8; margin-bottom: 5px; display: block; font-weight: 600; }

/* ── Platinum Input Bar ── */
.plat-footer { padding: 15px 25px 25px; }
.plat-input-box {
    background: #fff; border-radius: 20px; padding: 10px 15px;
    display: flex; align-items: flex-end; gap: 12px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.06);
    border: 1px solid rgba(0,0,0,0.03);
}

#chatInput {
    flex: 1; border: none; background: transparent; padding: 10px 0;
    font-size: 1rem; max-height: 150px; resize: none; outline: none;
    font-family: inherit; color: #1e293b;
}

.btn-send-elite {
    width: 48px; height: 48px; border-radius: 15px;
    background: var(--chat-primary); color: #fff;
    border: none; cursor: pointer; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
}

.btn-send-elite:hover { transform: translateY(-4px) scale(1.05); box-shadow: 0 10px 25px rgba(102, 35, 49, 0.35); }

/* ── FIXED MODAL SYSTEM (Teleport Fix) ── */
.v-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    z-index: 100000; background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    visibility: hidden; opacity: 0; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.v-modal-overlay.active { visibility: visible; opacity: 1; }

.v-modal-card {
    background: #fff; width: 100%; max-width: 460px;
    border-radius: 32px; padding: 40px;
    box-shadow: 0 50px 100px -20px rgba(0,0,0,0.4);
    transform: translateY(30px) scale(0.95);
    transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.v-modal-overlay.active .v-modal-card { transform: translateY(0) scale(1); }

.v-input {
    width: 100%; padding: 14px 18px; border-radius: 16px;
    border: 1px solid #e2e8f0; background: #f8fafc;
    font-size: 1rem; margin-top: 8px; outline: none; transition: 0.3s;
}
.v-input:focus { border-color: var(--chat-primary); background: #fff; box-shadow: 0 0 0 5px rgba(102,35,49,0.06); }

.v-btn-primary {
    width: 100%; padding: 18px; border: none; border-radius: 18px;
    background: var(--chat-primary); color: #fff; font-weight: 700;
    font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 15px;
}
.v-btn-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(102, 35, 49, 0.4); }

/* Responsive */
@media (max-width: 768px) {
    #chatPanel { top:0; right:0; left:0; bottom:0; width:100% !important; height:100% !important; border-radius:0; }
    #chatSidebar { width: 100%; position: absolute; inset: 0; z-index: 100; transform: translateX(0); }
    #chatSidebar.hidden { transform: translateX(-100%); }
}
</style>

<div id="chatPanel">
    <!-- Sidebar -->
    <div id="chatSidebar">
        <div class="plat-side-header">
            <h4 style="margin:0; font-size:1rem;"><i class="fa-solid fa-comments" style="color:var(--chat-accent)"></i> <?= $chat_area_label ?></h4>
            <span style="font-size:0.6rem; text-transform:uppercase; color:var(--chat-accent); opacity:0.8; letter-spacing:0.1em; font-weight:800; margin-top:5px; display:block;">Platinum Edition v5.3</span>
        </div>
        <div id="chatAdminList">
            <button class="plat-contact active" id="btnGen" onclick="selChat(null)">
                <div class="plat-avatar" style="background:#B19A6D"><i class="fa-solid fa-users"></i></div>
                <div style="flex:1;">
                    <div style="font-weight:700; font-size:0.9rem;">Área General</div>
                    <div style="font-size:0.65rem; opacity:0.6;">Chat Colaborativo</div>
                </div>
            </button>
            <div style="margin-top: 30px; padding: 0 10px;">
                <p style="font-size: 0.65rem; opacity: 0.4; text-transform: uppercase; font-weight: 800; letter-spacing: 0.1em; padding-left: 14px; margin-bottom: 15px;">Mensajes Directos</p>
                <div id="platDms"></div>
            </div>
        </div>
        <!-- Acciones Inferiores -->
        <div style="padding: 15px; background: rgba(0,0,0,0.1); border-top: 1px solid rgba(255,255,255,0.05);">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button onclick="opModPlat('tarea')" style="background:rgba(255,255,255,0.08); border:none; border-radius:14px; color:#fff; padding:12px 5px; cursor:pointer; font-size:0.75rem;"><i class="fa-solid fa-list-check" style="font-size:1rem; margin-bottom:5px;"></i><br>Tarea</button>
                <button onclick="opModPlat('evento')" style="background:rgba(255,255,255,0.08); border:none; border-radius:14px; color:#fff; padding:12px 5px; cursor:pointer; font-size:0.75rem;"><i class="fa-solid fa-calendar-star" style="font-size:1rem; margin-bottom:5px;"></i><br>Evento</button>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="chat-main">
        <div class="plat-header">
            <button class="mobile-back" style="display:none; background:none; border:none; color:var(--chat-primary); font-size:1.3rem; margin-right:15px;" onclick="vAList()"><i class="fa-solid fa-chevron-left"></i></button>
            <div id="hdrAvatar" class="plat-avatar" style="background:var(--chat-primary);"><i class="fa-solid fa-users"></i></div>
            <div style="flex:1; min-width:0;">
                <strong id="hdrName" style="font-size: 1.1rem; color: #1e293b; display: block;">General de Área</strong>
                <p id="hdrStatus" style="font-size: 0.8rem; color: #94a3b8; margin: 0;">Activo ahora</p>
            </div>
            <button onclick="toggleChat()" style="background:none; border:none; color:#cbd5e1; cursor:pointer; font-size:1.5rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="chatMessages"></div>
        <div class="plat-footer">
            <div class="plat-input-box">
                <textarea id="chatInput" placeholder="Enviar mensaje..." onkeydown="chatKeyDown(event)" rows="1"></textarea>
                <button class="btn-send-elite" onclick="enviarMensaje()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Modales (Aislados para Teleport) -->
<div id="platModalRoot" class="v-modal-overlay" onclick="clModPlat()">
    <div class="v-modal-card" onclick="event.stopPropagation()">
        <!-- Tarea -->
        <div id="vModTarea" style="display:none;">
            <div style="text-align:center; margin-bottom:30px;">
                <div style="width:60px; height:60px; background:#66233115; color:#662331; border-radius:20px; display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:15px;"><i class="fa-solid fa-layer-group"></i></div>
                <h3 style="margin:0; font-size:1.5rem; color:#1e293b;">Crear Tarea Kanban</h3>
                <p style="color:#94a3b8; font-size:0.9rem; margin-top:5px;">Gestiona el flujo de trabajo del equipo</p>
            </div>
            <input type="text" id="vTTitle" placeholder="Nombre de la tarea" class="v-input">
            <textarea id="vTDesc" placeholder="Descripción breve..." class="v-input" style="height:100px; resize:none;"></textarea>
            <select id="vTAssign" class="v-input"></select>
            <button onclick="saveTPlat()" class="v-btn-primary">Registrar en Tablero</button>
        </div>
        <!-- Evento -->
        <div id="vModEvento" style="display:none;">
            <div style="text-align:center; margin-bottom:30px;">
                <div style="width:60px; height:60px; background:#B19A6D15; color:#B19A6D; border-radius:20px; display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:15px;"><i class="fa-solid fa-calendar-check"></i></div>
                <h3 style="margin:0; font-size:1.5rem; color:#1e293b;">Agendar Evento</h3>
                <p style="color:#94a3b8; font-size:0.9rem; margin-top:5px;">Añade una fecha clave al calendario</p>
            </div>
            <input type="text" id="vETitle" placeholder="Título del evento" class="v-input">
            <input type="datetime-local" id="vEStart" class="v-input">
            <button onclick="saveEPlat()" class="v-btn-primary" style="background:var(--chat-accent);">Guardar en Calendario</button>
        </div>
        <button onclick="clModPlat()" style="width:100%; border:none; background:none; margin-top:20px; color:#94a3b8; font-weight:600; cursor:pointer;">Cancelar registro</button>
    </div>
</div>

<script>
(function() {
    const API = '<?= BASE_URL ?>admin/api/chat.php';
    const ADMIN_ID = '<?= $_SESSION['admin_id'] ? "A".$_SESSION['admin_id'] : "P".($_SESSION['user_id'] ?? 0) ?>';
    let chatOpen = false, canalActual = null, ultimoId = 0, polling = null, badgeCnt = 0;

    // Teleport Modals to Body to fix alignment issues
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('platModalRoot');
        if (modal) document.body.appendChild(modal);
    });

    window.toggleChat = function() {
        chatOpen = !chatOpen;
        document.getElementById('chatPanel').style.display = chatOpen ? 'flex' : 'none';
        if (chatOpen) { loadAdmins(); loadMsgs(true); startPoll(); badgeCnt = 0; const b = document.getElementById('chatBadge'); if(b) b.style.display = 'none'; }
        else stopPoll(); // FIX: antes era detenerPoll()
    };

    function marcarLeidoServidor(id) {
        if (!id) return;
        const fd = new FormData();
        fd.append('accion', 'marcar_leido');
        fd.append('ultimo_id', id);
        // Fallback seguro por si no esta la variable de sesion definida
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? "" ?>');
        fetch(API, { method: 'POST', body: fd }).then(() => {
            if (window.COMECyTNotificationBell) window.COMECyTNotificationBell.fetch();
        }).catch(e => console.error(e));
    }

    window.selChat = function(id, nombre = 'General de Área') {
        canalActual = id; ultimoId = 0;
        document.getElementById('chatMessages').innerHTML = '';
        document.getElementById('hdrName').textContent = nombre;
        document.getElementById('hdrStatus').textContent = id ? 'Mensaje Directo' : 'Equipo Institucional';
        const hdrAv = document.getElementById('hdrAvatar');
        hdrAv.innerHTML = id ? `<div class="plat-avatar">${nombre.charAt(0).toUpperCase()}</div>` : `<i class="fa-solid fa-users"></i>`;
        document.querySelectorAll('.plat-contact').forEach(el => el.classList.remove('active'));
        if (!id) document.getElementById('btnGen').classList.add('active');
        loadMsgs(true);
        if (window.innerWidth < 768) document.getElementById('chatSidebar').classList.add('hidden');
    };

    function startPoll() { stopPoll(); polling = setInterval(() => loadMsgs(false), 5000); }
    function stopPoll() { if (polling) clearInterval(polling); }
    window.vAList = () => document.getElementById('chatSidebar').classList.remove('hidden');

    function loadAdmins() {
        fetch(`${API}?accion=admins`).then(r => r.json()).then(res => {
            if (!res.ok) return;
            const list = document.getElementById('platDms');
            const select = document.getElementById('vTAssign');
            list.innerHTML = '';
            select.innerHTML = '<option value="">-- Cualquiera del equipo --</option>';
            res.admins.forEach(adm => {
                if (adm.id === ADMIN_ID) return;
                const btn = document.createElement('button');
                btn.className = `plat-contact ${canalActual === adm.id ? 'active' : ''}`;
                btn.innerHTML = `<div class="plat-avatar">${adm.inicial}</div><div style="flex:1;"><div style="font-weight:700; font-size:0.9rem;">${adm.nombre}</div><div style="font-size:0.65rem; opacity:0.5;">${adm.rol || 'Miembro'}</div></div>`;
                btn.onclick = () => selChat(adm.id, adm.nombre);
                list.appendChild(btn);
                const opt = document.createElement('option'); opt.value = adm.id.replace('A',''); opt.textContent = adm.nombre;
                select.appendChild(opt);
            });
        });
    }

    function loadMsgs(scroll = false) {
        let url = `${API}?accion=listar&desde=${ultimoId}`;
        if (canalActual) url += `&destinatario=${canalActual}`;
        fetch(url).then(r => r.json()).then(res => {
            if (!res.ok || !res.mensajes.length) return;
            const zona = document.getElementById('chatMessages');
            res.mensajes.forEach(m => {
                const isMe = String(m.admin_id) === String(ADMIN_ID);
                const bub = document.createElement('div');
                bub.className = `elite-msg ${isMe ? 'me' : 'other'}`;
                let content = ``;
                if (m.tipo === 'tarea') {
                    content = `<span class="elite-meta" style="${isMe?'color:#ffffffaa':''}">${m.admin_nombre} • ${m.hora}</span><div style="display:flex; gap:12px; align-items:center; background:#00000010; padding:10px; border-radius:12px;"><i class="fa-solid fa-list-check" style="font-size:1.2rem;"></i><div><strong>${m.ref_titulo}</strong><br><small>Tarea Kanban registrada</small></div></div>`;
                } else if (m.tipo === 'evento') {
                    content = `<span class="elite-meta" style="${isMe?'color:#ffffffaa':''}">${m.admin_nombre} • ${m.hora}</span><div style="display:flex; gap:12px; align-items:center; background:#ffffff15; padding:10px; border-radius:12px; border:1px solid #B19A6D33;"><i class="fa-solid fa-calendar-star" style="color:var(--chat-accent); font-size:1.2rem;"></i><div><strong>${m.ref_titulo}</strong><br><small>Evento agendado</small></div></div>`;
                } else {
                    content = `<span class="elite-meta" style="${isMe?'color:#ffffffaa':''}">${m.admin_nombre} • ${m.hora}</span><div style="word-break:break-word;">${m.mensaje}</div>`;
                }
                bub.innerHTML = content;
                zona.appendChild(bub);
                ultimoId = Math.max(ultimoId, parseInt(m.id));
            });
            if (scroll) zona.scrollTop = zona.scrollHeight;
            if (!chatOpen) {
                badgeCnt += res.mensajes.length;
                const b = document.getElementById('chatBadge'); if (b) { b.textContent = badgeCnt > 99 ? '99+' : badgeCnt; b.style.display = 'flex'; }
                if (window.COMECyTNotificationBell) window.COMECyTNotificationBell.notify();
            } else {
                // Reportar últimos mensajes cargados al server como leídos
                marcarLeidoServidor(ultimoId);
            }
        });
    }

    window.enviarMensaje = function() {
        const inp = document.getElementById('chatInput'); const txt = inp.value.trim(); if (!txt) return;
        const fd = new FormData(); fd.append('accion', 'enviar'); fd.append('mensaje', txt); fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        if (canalActual) fd.append('destinatario', canalActual);
        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => { if (res.ok) { inp.value = ''; loadMsgs(true); } });
    };

    window.chatKeyDown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensaje(); } };

    // Modales Platinum Elite
    window.opModPlat = (tipo) => {
        document.getElementById('platModalRoot').classList.add('active');
        document.getElementById('vModTarea').style.display = tipo === 'tarea' ? 'block' : 'none';
        document.getElementById('vModEvento').style.display = tipo === 'evento' ? 'block' : 'none';
        loadAdmins();
    };
    window.clModPlat = () => document.getElementById('platModalRoot').classList.remove('active');

    window.saveTPlat = () => {
        const tit = document.getElementById('vTTitle').value.trim(); const dsc = document.getElementById('vTDesc').value.trim(); const asg = document.getElementById('vTAssign').value;
        if (!tit) return;
        const fd = new FormData(); fd.append('accion', 'crear_tarea'); fd.append('titulo', tit); fd.append('descripcion', dsc); fd.append('asignado_a', asg); fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => { if (res.ok) { clModPlat(); document.getElementById('vTTitle').value=''; loadMsgs(true); } });
    };

    window.saveEPlat = () => {
        const tit = document.getElementById('vETitle').value.trim(); const st = document.getElementById('vEStart').value;
        if (!tit || !st) return;
        const fd = new FormData(); fd.append('accion', 'crear_evento'); fd.append('titulo', tit); fd.append('fecha_inicio', st); fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => { if (res.ok) { clModPlat(); document.getElementById('vETitle').value=''; loadMsgs(true); } });
    };

})();
</script>
