/**
 * COMECyT — Sistema de Notificaciones en Tiempo Real v3
 * ─────────────────────────────────────────────────────────────────────────────
 * Solo se activa si hay una sesión de administrador activa.
 * Inyecta sus propios estilos CSS directamente en el DOM para garantizar
 * funcionamiento en TODAS las páginas admin sin depender de archivos CSS externos.
 *
 * Flujo:
 *  1. ?init=1 → obtiene IDs máximos actuales (baseline silencioso)
 *  2. Polling cada 15s → detecta SOLO registros posteriores al baseline
 *  3. Si hay nuevos → toast visual animado + sonido
 */

(function () {
    'use strict';

    // ─── Configuración ────────────────────────────────────────────────────────
    const POLL_MS = 2000;    // Polling cada 2 segundos (antes 15s)
    const TOAST_MS = 6000;    // Auto-dismiss
    const MAX_TOASTS = 4;

    // Detectar BASE_URL: primero variable global, luego inferir desde el script src
    function detectarBase() {
        if (typeof BASE_URL_JS !== 'undefined' && BASE_URL_JS) return BASE_URL_JS;
        // Fallback: inferir desde la URL del propio script
        const scripts = document.querySelectorAll('script[src*="notificaciones.js"]');
        if (scripts.length) {
            const src = scripts[scripts.length - 1].src;
            return src.replace(/assets\/js\/notificaciones\.js.*$/, '');
        }
        // Último recurso: usar path relativo a /admin/
        const path = window.location.pathname;
        const idx = path.indexOf('/admin/');
        if (idx !== -1) return window.location.origin + path.substring(0, idx + 1);
        return window.location.origin + '/';
    }

    const BASE = detectarBase();
    const API = BASE + 'admin/api/notificaciones.php';

    // ─── Estado ───────────────────────────────────────────────────────────────
    let ultimoIdChat = parseInt(sessionStorage.getItem('nc_chat') || '0');
    let ultimoIdSolicitud = parseInt(sessionStorage.getItem('nc_sol') || '0');
    let baselineOk = sessionStorage.getItem('nc_init') === '1';
    let pollingTimer = null;
    let audioCtx = null;
    let audioOk = false;  // true tras primer clic (Safari fix)

    // ─── Inicio ───────────────────────────────────────────────────────────────
    function init() {
        inyectarCSS();
        crearContenedor();
        pedirPermisoNotif();
        configurarAudioUnlock();

        if (baselineOk && ultimoIdChat > 0 && ultimoIdSolicitud > 0) {
            iniciarPolling();
        } else {
            establecerBaseline();
        }
    }

    // ─── Baseline silencioso ──────────────────────────────────────────────────
    function establecerBaseline() {
        fetch(API + '?init=1', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                if (!d.ok) return;
                ultimoIdChat = d.chat.ultimo_id;
                ultimoIdSolicitud = d.solicitudes.ultimo_id;
                sessionStorage.setItem('nc_chat', ultimoIdChat);
                sessionStorage.setItem('nc_sol', ultimoIdSolicitud);
                sessionStorage.setItem('nc_init', '1');
                iniciarPolling();
            })
            .catch(() => setTimeout(iniciarPolling, 30000));
    }

    // ─── Polling ──────────────────────────────────────────────────────────────
    function iniciarPolling() {
        if (pollingTimer) return;
        pollingTimer = setInterval(hacerPolling, POLL_MS);
        setTimeout(hacerPolling, 3000); // primer check rápido
    }

    function hacerPolling() {
        const url = `${API}?ultimo_chat=${ultimoIdChat}&ultima_solicitud=${ultimoIdSolicitud}`;
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                if (!d.ok) return;
                if (d.chat && d.chat.count > 0) onNuevoChat(d.chat);
                if (d.solicitudes && d.solicitudes.count > 0) onNuevaSolicitud(d.solicitudes);
            })
            .catch(() => { });
    }

    // ─── Nuevo mensaje de chat ────────────────────────────────────────────────
    function onNuevoChat(data) {
        ultimoIdChat = data.ultimo_id;
        sessionStorage.setItem('nc_chat', ultimoIdChat);

        // No toastar si el panel del chat está abierto
        const panel = document.getElementById('chatPanel');
        if (panel && panel.style.display !== 'none') return;

        const msg = data.count === 1 ? data.preview : `${data.count} mensajes nuevos`;
        toast('chat', '💬 Nuevo mensaje', msg, () => {
            if (typeof window.toggleChat === 'function') window.toggleChat();
        });
        sonido('chat');
        badge(data.count);
    }

    // ─── Nueva solicitud ──────────────────────────────────────────────────────
    function onNuevaSolicitud(data) {
        ultimoIdSolicitud = data.ultimo_id;
        sessionStorage.setItem('nc_sol', ultimoIdSolicitud);

        const msg = data.count === 1 ? data.preview : `${data.count} solicitudes nuevas`;
        toast('solicitud', '📋 Nueva solicitud', msg, () => {
            window.location.href = BASE + 'admin/solicitudes.php?estatus=pendiente';
        });
        sonido('solicitud');

        if (window.Notification && Notification.permission === 'granted') {
            try { new Notification('COMECyT — Nueva solicitud', { body: data.preview, icon: BASE + 'assets/MARCA.png', tag: 'nc-sol' }); }
            catch (_) { }
        }
    }

    // ─── Badge del chat ───────────────────────────────────────────────────────
    function badge(n) {
        const b = document.getElementById('chatBadge');
        if (!b) return;
        const actual = parseInt(b.textContent) || 0;
        const nuevo = actual + n;
        b.textContent = nuevo > 99 ? '99+' : nuevo;
        b.style.display = 'flex';
    }

    // ─── Toast ────────────────────────────────────────────────────────────────
    function crearContenedor() {
        if (!document.getElementById('nc-container')) {
            const div = document.createElement('div');
            div.id = 'nc-container';
            document.body.appendChild(div);
        }
    }

    function toast(tipo, titulo, mensaje, accion) {
        const c = document.getElementById('nc-container');
        if (!c) return;

        // Limitar apilado
        const activos = c.querySelectorAll('.nc-toast');
        if (activos.length >= MAX_TOASTS) activos[0].remove();

        const el = document.createElement('div');
        el.className = `nc-toast nc-toast--${tipo}`;
        el.setAttribute('role', 'alert');

        const icono = tipo === 'chat' ? '💬' : '📋';
        el.innerHTML = `
            <div class="nc-icon">${icono}</div>
            <div class="nc-body" style="cursor:pointer;">
                <div class="nc-title">${xss(titulo)}</div>
                <div class="nc-msg">${xss(mensaje)}</div>
            </div>
            <button class="nc-close" title="Cerrar">✕</button>
            <div class="nc-bar"></div>
        `;

        el.querySelector('.nc-body').addEventListener('click', () => {
            if (accion) accion();
            cerrarToast(el);
        });
        el.querySelector('.nc-close').addEventListener('click', e => {
            e.stopPropagation();
            cerrarToast(el);
        });

        c.appendChild(el);

        // Animar barra de progreso
        const bar = el.querySelector('.nc-bar');
        requestAnimationFrame(() => requestAnimationFrame(() => {
            bar.style.width = '0%';
            bar.style.transition = `width ${TOAST_MS}ms linear`;
        }));

        el._t = setTimeout(() => cerrarToast(el), TOAST_MS);
    }

    function cerrarToast(el) {
        clearTimeout(el._t);
        el.style.animation = 'nc-salir 0.35s ease forwards';
        setTimeout(() => { if (el.parentNode) el.remove(); }, 380);
    }

    // ─── CSS inyectado ────────────────────────────────────────────────────────
    // Se inyecta aquí para garantizar funcionamiento en TODAS las páginas
    // sin depender de archivos CSS externos o su correcto cargado.
    function inyectarCSS() {
        if (document.getElementById('nc-styles')) return;
        const s = document.createElement('style');
        s.id = 'nc-styles';
        s.textContent = `
            /* ── Contenedor ────────────────────────────────────── */
            #nc-container {
                position: fixed;
                top: 72px;
                right: 20px;
                z-index: 999999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
                max-width: 340px;
                width: calc(100vw - 40px);
            }

            /* ── Toast base ─────────────────────────────────────── */
            .nc-toast {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                background: #1a1a2e;
                border: 1px solid rgba(255,255,255,0.12);
                border-radius: 14px;
                padding: 13px 12px 16px;
                box-shadow: 0 12px 40px rgba(0,0,0,0.5);
                position: relative;
                overflow: hidden;
                pointer-events: all;
                animation: nc-entrar 0.35s cubic-bezier(0.34,1.56,0.64,1) both;
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
            }

            /* ── Variante chat (azul) ───────────────────────────── */
            .nc-toast--chat   { border-left: 3px solid #6366f1; }
            .nc-toast--chat   .nc-icon { color: #818cf8; background: rgba(99,102,241,0.18); }
            .nc-toast--chat   .nc-bar  { background: linear-gradient(90deg,#6366f1,#818cf8); }

            /* ── Variante solicitud (guinda) ────────────────────── */
            .nc-toast--solicitud { border-left: 3px solid #662331; }
            .nc-toast--solicitud .nc-icon { color: #B19A6D; background: rgba(102,35,49,0.22); }
            .nc-toast--solicitud .nc-bar  { background: linear-gradient(90deg,#662331,#B19A6D); }

            /* ── Ícono ──────────────────────────────────────────── */
            .nc-icon {
                width: 34px; height: 34px; border-radius: 10px;
                display: flex; align-items: center; justify-content: center;
                font-size: 1.1rem; flex-shrink: 0;
            }

            /* ── Cuerpo ─────────────────────────────────────────── */
            .nc-body { flex: 1; min-width: 0; padding-top: 1px; }
            .nc-title {
                font-size: 0.78rem; font-weight: 700; color: #fff;
                margin: 0 0 3px; line-height: 1.2;
            }
            .nc-msg {
                font-size: 0.74rem; color: rgba(255,255,255,0.6);
                margin: 0; overflow: hidden; text-overflow: ellipsis;
                white-space: nowrap; max-width: 215px; line-height: 1.4;
            }

            /* ── Botón cerrar ───────────────────────────────────── */
            .nc-close {
                background: rgba(255,255,255,0.08); border: none;
                color: rgba(255,255,255,0.45); width: 22px; height: 22px;
                border-radius: 50%; cursor: pointer; font-size: 0.65rem;
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0; margin-top: 1px;
                transition: background .15s, color .15s;
                pointer-events: all;
            }
            .nc-close:hover { background: rgba(255,255,255,0.2); color: #fff; }

            /* ── Barra de progreso ──────────────────────────────── */
            .nc-bar {
                position: absolute; bottom: 0; left: 0;
                height: 3px; width: 100%;
                border-radius: 0 0 14px 14px;
            }

            /* ── Animaciones ────────────────────────────────────── */
            @keyframes nc-entrar {
                from { opacity:0; transform:translateX(110%) scale(0.9); }
                to   { opacity:1; transform:translateX(0)   scale(1);   }
            }
            @keyframes nc-salir {
                from { opacity:1; transform:translateX(0) scale(1);     }
                to   { opacity:0; transform:translateX(110%) scale(0.9); }
            }

            /* ── Responsive ─────────────────────────────────────── */
            @media (max-width: 480px) {
                #nc-container {
                    right: 8px; left: 8px; top: 68px;
                    max-width: 100%; width: auto;
                }
                .nc-msg { max-width: 100%; }
            }
        `;
        document.head.appendChild(s);
    }

    // ─── Elemento de audio para el MP3 del chat ─────────────────────────────
    // Se precarga al iniciar para evitar delay en la primera notificación
    const audioChat = new Audio(BASE + 'assets/TIENES UN MENSAJE!!!  letra.mp3');
    audioChat.preload = 'auto';
    audioChat.volume = 0.7;

    // ─── Web Audio (con Safari unlock) ────────────────────────────────────────
    function configurarAudioUnlock() {
        const evs = ['click', 'touchstart', 'keydown'];
        function unlock() {
            if (audioOk) return;
            // Desbloquear el elemento Audio de HTML (necesario en Safari/iOS)
            audioChat.play().then(() => { audioChat.pause(); audioChat.currentTime = 0; }).catch(() => { });
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const reanudar = () => { audioOk = true; };
                audioCtx.state === 'suspended' ? audioCtx.resume().then(reanudar) : reanudar();
            } catch (_) { audioOk = true; } // Permitir que el MP3 funcione aunque WebAudio falle
            evs.forEach(e => document.removeEventListener(e, unlock, true));
        }
        evs.forEach(e => document.addEventListener(e, unlock, { capture: true }));
    }

    function sonido(tipo) {
        if (tipo === 'chat') {
            // Usar el MP3 real para mensajes de chat
            try {
                audioChat.currentTime = 0;
                audioChat.play().catch(() => { });
            } catch (_) { }
            return;
        }
        // Solicitudes → tono sintético Web Audio (más urgente/grave)
        if (!audioOk || !audioCtx) return;
        try {
            if (audioCtx.state === 'suspended') audioCtx.resume();
            nota(440, 0.00, 0.14, 0.22);
            nota(550, 0.16, 0.12, 0.20);
            nota(660, 0.30, 0.10, 0.18);
        } catch (_) { }
    }

    function nota(freq, delay, dur, vol) {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        const t = audioCtx.currentTime + delay;
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, t);
        gain.gain.setValueAtTime(0, t);
        gain.gain.linearRampToValueAtTime(vol, t + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, t + dur);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(t);
        osc.stop(t + dur);
    }

    // ─── Permisos de notificación del sistema ────────────────────────────────
    function pedirPermisoNotif() {
        if ('Notification' in window && Notification.permission === 'default') {
            setTimeout(() => Notification.requestPermission(), 4000);
        }
    }

    // ─── Escape XSS ──────────────────────────────────────────────────────────
    function xss(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ─── Arranque ─────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // API pública
    window.COMECyTNotif = {
        test: (tipo) => tipo === 'chat'
            ? onNuevoChat({ count: 1, ultimo_id: ultimoIdChat, preview: 'Prueba de notificación de chat' })
            : onNuevaSolicitud({ count: 1, ultimo_id: ultimoIdSolicitud, preview: 'CMCT-2026-TEST · Prueba' }),
        polling: hacerPolling,
        reiniciar: () => {
            ['nc_chat', 'nc_sol', 'nc_init'].forEach(k => sessionStorage.removeItem(k));
            if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
            init();
        },
    };

})();
