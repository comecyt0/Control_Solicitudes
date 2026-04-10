/**
 * COMECyT Control de Solicitudes
 * JavaScript del lado del cliente
 *
 * Responsabilidad: Manejar unicamente la capa de presentacion (UI):
 *   - Sidebar movil
 *   - Modales
 *   - Confirmaciones antes de acciones criticas
 *   - Notificaciones toast efimeras
 *
 * NO contiene logica de negocio ni llamadas a API.
 * Todos los datos son obtenidos y procesados por PHP (server-side).
 */

'use strict';

/* ==============================================================
   Sidebar movil
   ============================================================== */
(function initSidebar() {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!toggle || !sidebar) return;

    function abrirSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        toggle.setAttribute('aria-expanded', 'true');
    }

    function cerrarSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', () => {
        sidebar.classList.contains('open') ? cerrarSidebar() : abrirSidebar();
    });

    overlay.addEventListener('click', cerrarSidebar);

    // Cerrar con Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            cerrarSidebar();
        }
    });
}());

/* ==============================================================
   Modales
   ============================================================== */
/**
 * Abrir un modal por ID.
 * @param {string} modalId
 */
function abrirModal(modalId) {
    const backdrop = document.getElementById(modalId);
    if (!backdrop) return;
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Foco en el primer campo interactivo
    const primer = backdrop.querySelector('input, select, textarea, button');
    if (primer) setTimeout(() => primer.focus(), 100);
}

/**
 * Cerrar un modal por ID.
 * @param {string} modalId
 */
function cerrarModal(modalId) {
    const backdrop = document.getElementById(modalId);
    if (!backdrop) return;
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
}

// Cerrar al hacer clic fuera del panel del modal
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Cerrar con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach((m) => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

/* ==============================================================
   Toast (notificaciones efimeras)
   ============================================================== */
/**
 * Mostrar una notificacion toast.
 * Reemplazado por COMECyTUI.toast para consistencia global.
 */
function mostrarToast(mensaje, tipo = 'success', duracion = 3500) {
    if (window.COMECyTUI) {
        window.COMECyTUI.toast(mensaje, tipo, { duration: duracion });
    } else {
        // Fallback básico si no carga la librería
        console.log(`[Toast ${tipo}] ${mensaje}`);
    }
}

/* ==============================================================
   Confirmaciones Globales Personalizadas (Custom Modal UI)
   ============================================================== */
/**
 * Reemplazado por COMECyTUI.confirm para consistencia global.
 */
function mostrarConfirmacionPersonalizada(titulo, mensaje, onConfirmCallback, isDanger = true) {
    if (window.COMECyTUI) {
        window.COMECyTUI.confirm(mensaje, onConfirmCallback, null, { titulo: titulo });
    } else {
        if (confirm(mensaje)) onConfirmCallback();
    }
}

/* ==============================================================
   Interceptar data-confirm
   ============================================================== */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;

    // Si ya fue confirmado desde el modal, permitimos el evento nativo
    if (btn.dataset.confirmed === "true") {
        btn.dataset.confirmed = "false";
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    const msj = btn.getAttribute('data-confirm');
    
    // Usar el framework institucional COMECyTUI
    if (window.COMECyTUI) {
        window.COMECyTUI.confirm(msj, () => {
            btn.dataset.confirmed = "true";
            btn.click();
        });
    } else {
        // Fallback nativo si la librería no está
        if (confirm(msj)) {
            btn.dataset.confirmed = "true";
            btn.click();
        }
    }
});

/* ==============================================================
   Auto-cierre de alertas con [data-auto-close]
   ============================================================== */
document.querySelectorAll('[data-auto-close]').forEach((el) => {
    const ms = parseInt(el.getAttribute('data-auto-close'), 10) || 4000;
    setTimeout(() => {
        el.style.transition = 'opacity 300ms ease';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    }, ms);
});

/* ==============================================================
   Confirmación Doble Asíncrona (Prevención de bloqueo en Chrome)
   ============================================================== */
/**
 * Ejecuta una doble confirmacion asincrona encadenando modales UI.
 */
window.confirmarAccionDoble = function (boton, msj1, msj2) {
    mostrarConfirmacionPersonalizada("Confirmación", msj1, () => {
        setTimeout(() => {
            mostrarConfirmacionPersonalizada("⚠️ Acción Irreversible", msj2, () => {
                boton.closest('form').submit();
            }, true);
        }, 150);
    }, false);
};
