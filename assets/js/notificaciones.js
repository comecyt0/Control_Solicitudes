/**
 * COMECyT — Sistema de Notificaciones en Tiempo Real v2
 * ─────────────────────────────────────────────────────────────────────────────
 * Flujo correcto:
 *  1. Al cargar: llama ?init=1 para obtener los IDs MÁXIMOS actuales de BD.
 *     Estos IDs son el baseline — no generan notificaciones.
 *  2. Cada 15s: llama con esos IDs como referencia.
 *     Solo si hay registros POSTERIORES → muestra toast + sonido.
 *
 * Esto resuelve:
 *  · Falsos positivos de solicitudes históricas en la primera carga
 *  · Chat que no detecta mensajes nuevos correctamente
 */

(function () {
    'use strict';

    const POLL_MS    = 15000;   // Polling cada 15 segundos
    const TOAST_DURACIÓN = 6000; // Auto-dismiss en 6 segundos
    const MAX_TOASTS = 4;

    const BASE = (typeof BASE_URL_JS !== 'undefined') ? BASE_URL_JS : '/';
    const API  = BASE + 'admin/api/notificaciones.php';

    // ─── Estado ───────────────────────────────────────────────────────────────
    // Usamos sessionStorage para que persista entre recargas del mismo tab
    // pero NO entre sesiones nuevas (se resetea al cerrar el tab)
    let ultimoIdChat      = parseInt(sessionStorage.getItem('nc_chat') || '0');
    let ultimoIdSolicitud = parseInt(sessionStorage.getItem('nc_sol')  || '0');
    let baselineEstablecido = sessionStorage.getItem('nc_init') === '1';
    let pollingTimer     = null;
    let audioCtx         = null;
    let audioDesbloqueado = false;  // Safari: true solo tras primer clic

    // ─── Inicialización ───────────────────────────────────────────────────────
    function init() {
        crearContenedor();
        solicitarPermisosNotif();
        configurarAudioUnlock(); // Safari: desbloquear audio en primer clic

        if (baselineEstablecido && ultimoIdChat > 0 && ultimoIdSolicitud > 0) {
            // Ya tenemos baselines de esta sesión → empezar polling directamente
            iniciarPolling();
        } else {
            // Primera vez en esta sesión → obtener baselines silenciosamente
            establecerBaseline();
        }
    }

    // ─── Establece el baseline sin disparar notificaciones ────────────────────
    function establecerBaseline() {
        fetch(API + '?init=1', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;

                // Guardar los IDs máximos actuales como punto de partida
                ultimoIdChat      = data.chat.ultimo_id;
                ultimoIdSolicitud = data.solicitudes.ultimo_id;

                sessionStorage.setItem('nc_chat', ultimoIdChat);
                sessionStorage.setItem('nc_sol',  ultimoIdSolicitud);
                sessionStorage.setItem('nc_init', '1');

                // AHORA empezar el polling real
                iniciarPolling();
            })
            .catch(() => {
                // Si falla el init, intentar polling de todas formas en 30s
                setTimeout(iniciarPolling, 30000);
            });
    }

    // ─── Polling principal ────────────────────────────────────────────────────
    function iniciarPolling() {
        if (pollingTimer) return; // No duplicar
        pollingTimer = setInterval(hacerPolling, POLL_MS);
        // También ejecutar de inmediato (para detectar mensajes llegados durante el init)
        setTimeout(hacerPolling, 3000);
    }

    function hacerPolling() {
        const url = `${API}?ultimo_chat=${ultimoIdChat}&ultima_solicitud=${ultimoIdSolicitud}`;
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;
                if (data.chat        && data.chat.count        > 0) procesarChat(data.chat);
                if (data.solicitudes && data.solicitudes.count > 0) procesarSolicitud(data.solicitudes);
            })
            .catch(() => {});
    }

    // ─── Procesar nuevos mensajes de chat ─────────────────────────────────────
    function procesarChat(chatData) {
        // Actualizar cursor para no repetir
        ultimoIdChat = chatData.ultimo_id;
        sessionStorage.setItem('nc_chat', ultimoIdChat);

        // No notificar si el panel de chat ya está abierto y visible
        const panel = document.getElementById('chatPanel');
        if (panel && panel.style.display !== 'none') {
            // Aun así actualizamos el cursor, pero no toast ni sonido
            return;
        }

        const texto = chatData.count === 1
            ? chatData.preview
            : `${chatData.count} mensajes nuevos`;

        mostrarToast({
            tipo:    'chat',
            titulo:  '💬 Nuevo mensaje',
            mensaje: texto,
            accion:  () => { if (typeof window.toggleChat === 'function') window.toggleChat(); },
        });

        reproducirSonido('chat');

        // Actualizar el badge del botón de chat
        actualizarBadgeChat(chatData.count);
    }

    // ─── Procesar nuevas solicitudes ──────────────────────────────────────────
    function procesarSolicitud(solData) {
        ultimoIdSolicitud = solData.ultimo_id;
        sessionStorage.setItem('nc_sol', ultimoIdSolicitud);

        const texto = solData.count === 1
            ? solData.preview
            : `${solData.count} solicitudes nuevas`;

        mostrarToast({
            tipo:    'solicitud',
            titulo:  '📋 Nueva solicitud',
            mensaje: texto,
            accion:  () => { window.location.href = BASE + 'admin/solicitudes.php?estatus=pendiente'; },
        });

        reproducirSonido('solicitud');

        // Notificación nativa del navegador
        if (window.Notification && Notification.permission === 'granted') {
            try {
                new Notification('COMECyT — Nueva solicitud', {
                    body: solData.preview,
                    icon: BASE + 'assets/MARCA.png',
                    tag:  'comecyt-sol',
                });
            } catch (_) {}
        }
    }

    // ─── Badge del chat ───────────────────────────────────────────────────────
    function actualizarBadgeChat(cantidad) {
        try {
            const badge = document.getElementById('chatBadge');
            if (!badge) return;
            const actual = parseInt(badge.textContent) || 0;
            const nuevo  = actual + cantidad;
            badge.textContent   = nuevo > 99 ? '99+' : nuevo;
            badge.style.display = 'flex';
        } catch (_) {}
    }

    // ─── Sistema de toasts ────────────────────────────────────────────────────
    function crearContenedor() {
        if (document.getElementById('notifContainer')) return;
        const c = document.createElement('div');
        c.id = 'notifContainer';
        document.body.appendChild(c);
    }

    function mostrarToast({ tipo, titulo, mensaje, accion }) {
        const container = document.getElementById('notifContainer');
        if (!container) return;

        // Limitar apilado
        const activos = container.querySelectorAll('.notif-toast');
        if (activos.length >= MAX_TOASTS) activos[0].remove();

        const icono = tipo === 'chat' ? 'fa-comments' : 'fa-ticket';

        const toast = document.createElement('div');
        toast.className = `notif-toast notif-toast--${tipo}`;
        toast.setAttribute('role', 'alert');

        toast.innerHTML = `
            <div class="notif-toast__icon"><i class="fa-solid ${icono}"></i></div>
            <div class="notif-toast__body">
                <p class="notif-toast__title">${esc(titulo)}</p>
                <p class="notif-toast__msg">${esc(mensaje)}</p>
            </div>
            <div class="notif-toast__progress"></div>
            <button class="notif-toast__close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        `;

        toast.querySelector('.notif-toast__body').addEventListener('click', () => {
            if (typeof accion === 'function') accion();
            quitar(toast);
        });

        toast.querySelector('.notif-toast__close').addEventListener('click', e => {
            e.stopPropagation();
            quitar(toast);
        });

        container.appendChild(toast);

        // Barra de progreso animada
        const bar = toast.querySelector('.notif-toast__progress');
        bar.style.transitionDuration = `${TOAST_DURACIÓN}ms`;
        requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = '0%'; }));

        toast._timer = setTimeout(() => quitar(toast), TOAST_DURACIÓN);
    }

    function quitar(toast) {
        if (toast._timer) clearTimeout(toast._timer);
        toast.classList.add('notif-toast--saliendo');
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 420);
    }

    // ─── Web Audio API ────────────────────────────────────────────────────────
    // Safari exige que el AudioContext se cree y reanude directamente
    // desde un handler de clic/tap. "audio unlock" es el patrón estándar.

    function configurarAudioUnlock() {
        const eventos = ['click', 'touchstart', 'keydown'];
        function desbloquear() {
            if (audioDesbloqueado) return;

            try {
                // Crear el contexto DENTRO del handler de evento → Safari lo acepta
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                // Reanudar si está suspendido (obligatorio en Safari)
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume().then(() => {
                        audioDesbloqueado = true;
                    });
                } else {
                    audioDesbloqueado = true;
                }
            } catch (_) {
                audioDesbloqueado = false;
            }

            // Solo necesitamos hacerlo una vez
            eventos.forEach(ev => document.removeEventListener(ev, desbloquear, true));
        }

        eventos.forEach(ev => document.addEventListener(ev, desbloquear, { once: false, capture: true }));
    }

    function getCtx() {
        // Si no está desbloqueado (sin interacción del usuario), no intentar crear
        if (!audioDesbloqueado) return null;
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function reproducirSonido(tipo) {
        const ctx = getCtx();
        if (!ctx) return;  // Silencioso si Safari no ha sido desbloqueado aún
        try {
            if (tipo === 'chat') {
                tono(ctx, 880,  0.00, 0.10, 0.12);
                tono(ctx, 1100, 0.12, 0.10, 0.12);
            } else {
                tono(ctx, 440,  0.00, 0.14, 0.22);
                tono(ctx, 550,  0.16, 0.12, 0.20);
                tono(ctx, 660,  0.30, 0.10, 0.18);
            }
        } catch (_) {}
    }

    function tono(ctx, freq, delay, dur, vol) {
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        const t    = ctx.currentTime + delay;
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, t);
        gain.gain.setValueAtTime(0, t);
        gain.gain.linearRampToValueAtTime(vol, t + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, t + dur);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(t);
        osc.stop(t + dur);
    }

    // ─── Permisos de notificación ─────────────────────────────────────────────
    function solicitarPermisosNotif() {
        if ('Notification' in window && Notification.permission === 'default') {
            // No bloqueante — pide en segundo plano
            setTimeout(() => Notification.requestPermission(), 3000);
        }
    }

    // ─── Escape XSS ───────────────────────────────────────────────────────────
    function esc(s) {
        return String(s || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─── Limpiar sesión al cerrar (para que al reiniciar se re-inicialice) ────
    window.addEventListener('beforeunload', () => {
        // No limpiar sessionStorage aquí — queremos que persista entre recargas
        // pero NO entre sesiones nuevas (el navegador lo gestiona)
    });

    // ─── Inicio automático al cargar el DOM ──────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // API pública mínima
    window.COMECyTNotif = {
        polling:    hacerPolling,
        reiniciar:  () => {
            sessionStorage.removeItem('nc_chat');
            sessionStorage.removeItem('nc_sol');
            sessionStorage.removeItem('nc_init');
            if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
            init();
        },
    };

})();
