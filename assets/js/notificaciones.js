/**
 * COMECyT — Sistema de Notificaciones en Tiempo Real
 * ─────────────────────────────────────────────────────────────────────────────
 * Detecta nuevos mensajes de chat y nuevas solicitudes mediante polling.
 * Muestra toasts visuales en la esquina superior derecha y emite sonidos
 * sintéticos mediante Web Audio API (sin archivos de audio externos).
 *
 * Se inicializa automáticamente al cargar la página si el admin está autenticado.
 * El módulo es autocontenido y NO modifica el comportamiento del chat ni
 * de ningún otro módulo existente.
 */

(function () {
    'use strict';

    // ─── Configuración ────────────────────────────────────────────────────────
    const POLL_INTERVAL_MS = 15000;  // Cada 15 segundos
    const TOAST_DURATION   = 6000;   // 6 segundos antes de auto-dismiss
    const MAX_TOASTS       = 4;      // Máximo de toasts simultáneos

    // Detectar BASE_URL desde la variable inyectada por header_admin
    const BASE = (typeof BASE_URL_JS !== 'undefined') ? BASE_URL_JS : '/';
    const API  = BASE + 'admin/api/notificaciones.php';

    // ─── Estado persistente (sobrevive a cambios de página dentro de la misma sesión)
    let ultimoIdChat       = parseInt(sessionStorage.getItem('notif_chat_id')   || '0');
    let ultimoIdSolicitud  = parseInt(sessionStorage.getItem('notif_sol_id')    || '0');
    let inicializado       = false;
    let pollingTimer       = null;
    let audioCtx           = null;
    let notifPermission    = 'default'; // Para notificaciones del navegador (opcional)

    // ─── Inicialización ───────────────────────────────────────────────────────
    function init() {
        if (inicializado) return;
        inicializado = true;

        // Crear contenedor de toasts si no existe
        if (!document.getElementById('notifContainer')) {
            const c = document.createElement('div');
            c.id = 'notifContainer';
            document.body.appendChild(c);
        }

        // Solicitar permiso de notificaciones del navegador (opcional, no bloquea)
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(p => { notifPermission = p; });
        } else if ('Notification' in window) {
            notifPermission = Notification.permission;
        }

        // Iniciar el primer polling después de 5s (espera que la página cargue)
        setTimeout(hacerPolling, 5000);
        pollingTimer = setInterval(hacerPolling, POLL_INTERVAL_MS);
    }

    // ─── Polling principal ────────────────────────────────────────────────────
    function hacerPolling() {
        const url = `${API}?ultimo_chat=${ultimoIdChat}&ultima_solicitud=${ultimoIdSolicitud}`;
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;

                // Notificaciones de CHAT
                if (data.chat && data.chat.count > 0) {
                    procesarNuevoChat(data.chat);
                }

                // Notificaciones de SOLICITUDES
                if (data.solicitudes && data.solicitudes.count > 0) {
                    procesarNuevaSolicitud(data.solicitudes);
                }
            })
            .catch(() => {}); // Silencioso — no interrumpir el admin si falla
    }

    // ─── Procesar nuevos mensajes de chat ─────────────────────────────────────
    function procesarNuevoChat(chatData) {
        // Actualizar el cursor para no repetir
        if (chatData.ultimo_id > ultimoIdChat) {
            ultimoIdChat = chatData.ultimo_id;
            sessionStorage.setItem('notif_chat_id', ultimoIdChat);
        }

        // No mostrar si el panel de chat ya está abierto (el usuario lo está viendo)
        const panel = document.getElementById('chatPanel');
        const panelVisible = panel && panel.style.display !== 'none';
        if (panelVisible) return;

        const texto = chatData.count === 1
            ? `💬 ${chatData.preview}`
            : `💬 ${chatData.count} mensajes nuevos`;

        mostrarToast({
            tipo:    'chat',
            titulo:  'Nuevo mensaje',
            mensaje: texto,
            icono:   'fa-comments',
            accion:  () => { if (typeof window.toggleChat === 'function') window.toggleChat(); },
        });

        // Sonido tipo "ding" suave (tono más agudo y corto)
        reproducirSonido('chat');

        // Actualizar badge del botón de chat en el topbar
        try {
            const badge = document.getElementById('chatBadge');
            if (badge) {
                const actual = parseInt(badge.textContent) || 0;
                const nuevo  = actual + chatData.count;
                badge.textContent   = nuevo > 99 ? '99+' : nuevo;
                badge.style.display = 'flex';
            }
        } catch (_) {}
    }

    // ─── Procesar nuevas solicitudes ──────────────────────────────────────────
    function procesarNuevaSolicitud(solData) {
        if (solData.ultimo_id > ultimoIdSolicitud) {
            ultimoIdSolicitud = solData.ultimo_id;
            sessionStorage.setItem('notif_sol_id', ultimoIdSolicitud);
        }

        const texto = solData.count === 1
            ? `📋 ${solData.preview}`
            : `📋 ${solData.count} solicitudes nuevas`;

        mostrarToast({
            tipo:    'solicitud',
            titulo:  'Nueva solicitud',
            mensaje: texto,
            icono:   'fa-ticket',
            accion:  () => { window.location.href = BASE + 'admin/solicitudes.php?estatus=pendiente'; },
        });

        // Sonido tipo "ping" más bajo y largo para distinguir
        reproducirSonido('solicitud');

        // Notificación de sistema del navegador (si el admin tiene permisos)
        if (notifPermission === 'granted') {
            try {
                new Notification('Nueva solicitud — COMECyT', {
                    body: solData.preview,
                    icon: BASE + 'assets/MARCA.png',
                    tag:  'comecyt-solicitud',
                });
            } catch (_) {}
        }
    }

    // ─── Sistema de toasts ────────────────────────────────────────────────────
    function mostrarToast({ tipo, titulo, mensaje, icono, accion }) {
        const container = document.getElementById('notifContainer');
        if (!container) return;

        // Limitar cantidad de toasts simultáneos
        const existentes = container.querySelectorAll('.notif-toast');
        if (existentes.length >= MAX_TOASTS) {
            existentes[0].remove();
        }

        const toast = document.createElement('div');
        toast.className = `notif-toast notif-toast--${tipo}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');

        toast.innerHTML = `
            <div class="notif-toast__icon">
                <i class="fa-solid ${icono}"></i>
            </div>
            <div class="notif-toast__body">
                <p class="notif-toast__title">${escapeHtml(titulo)}</p>
                <p class="notif-toast__msg">${escapeHtml(mensaje)}</p>
            </div>
            <div class="notif-toast__progress"></div>
            <button class="notif-toast__close" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        // Acción al hacer clic en el cuerpo del toast
        toast.querySelector('.notif-toast__body').addEventListener('click', () => {
            if (typeof accion === 'function') accion();
            removerToast(toast);
        });
        toast.style.cursor = 'pointer';

        // Botón cerrar
        toast.querySelector('.notif-toast__close').addEventListener('click', (e) => {
            e.stopPropagation();
            removerToast(toast);
        });

        container.appendChild(toast);

        // Animar la barra de progreso y auto-dismiss
        const progreso = toast.querySelector('.notif-toast__progress');
        progreso.style.transitionDuration = `${TOAST_DURATION}ms`;
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                progreso.style.width = '0%';
            });
        });

        const timer = setTimeout(() => removerToast(toast), TOAST_DURATION);
        toast._dismissTimer = timer;
    }

    function removerToast(toast) {
        if (toast._dismissTimer) clearTimeout(toast._dismissTimer);
        toast.classList.add('notif-toast--saliendo');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
        // Fallback si animationend no dispara
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 500);
    }

    // ─── Web Audio API — Sonidos sintéticos ───────────────────────────────────
    function getAudioContext() {
        if (!audioCtx) {
            try {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            } catch (_) {}
        }
        return audioCtx;
    }

    function reproducirSonido(tipo) {
        const ctx = getAudioContext();
        if (!ctx) return;

        // Reanudar si el navegador suspendió el contexto de audio
        if (ctx.state === 'suspended') ctx.resume();

        try {
            if (tipo === 'chat') {
                // Ding suave: dos tonos cortos ascendentes
                _tocar(ctx, 880, 0.0,  0.12, 0.15, 'sine');
                _tocar(ctx, 1100, 0.14, 0.12, 0.15, 'sine');
            } else {
                // Ping de solicitud: tono más bajo, más prominente
                _tocar(ctx, 440, 0.0,  0.15, 0.25, 'sine');
                _tocar(ctx, 550, 0.18, 0.12, 0.2,  'sine');
                _tocar(ctx, 660, 0.32, 0.10, 0.18, 'sine');
            }
        } catch (_) {}
    }

    function _tocar(ctx, frecuencia, startDelay, duracion, volumen, tipo) {
        const osc   = ctx.createOscillator();
        const gain  = ctx.createGain();
        const now   = ctx.currentTime + startDelay;

        osc.type      = tipo;
        osc.frequency.setValueAtTime(frecuencia, now);

        gain.gain.setValueAtTime(0, now);
        gain.gain.linearRampToValueAtTime(volumen, now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, now + duracion);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + duracion);
    }

    // ─── Helper XSS ───────────────────────────────────────────────────────────
    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ─── Auto-inicio cuando el DOM está listo ─────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // API pública mínima (para reiniciar desde consola si es necesario)
    window.COMECyTNotif = { reiniciar: init, polling: hacerPolling };

})();
