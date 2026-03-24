/**
 * COMECyT — UI Frames Utility v1.0
 * ─────────────────────────────────────────────────────────────────────────────
 * Propósito: Reemplazar alert() y confirm() con marcos visuales integrados.
 * Diseño: Basado en el sistema de diseño institucional COMECyT.
 */

(function () {
    'use strict';

    const UI_STYLES = `
        .ui-frame-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            backdrop-filter: blur(5px); display: flex; align-items: center;
            justify-content: center; z-index: 1000000; animation: ui-fade-in 0.3s ease;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            padding: 20px;
        }
        .ui-frame {
            background: #ffffff; width: 100%; max-width: 420px;
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: ui-slide-up 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .ui-frame-header {
            padding: 20px 24px; background: #f8fafc;
            border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px;
        }
        .ui-frame-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .ui-frame-icon--alert { background: #fee2e2; color: #dc2626; }
        .ui-frame-icon--info  { background: #e0f2fe; color: #0284c7; }
        .ui-frame-icon--confirm { background: #fef9c3; color: #ca8a04; }

        .ui-frame-title { font-weight: 700; color: #1e293b; font-size: 1.1rem; }
        .ui-frame-body { padding: 24px; color: #475569; font-size: 0.95rem; line-height: 1.5; white-space: pre-wrap; }
        .ui-frame-footer { padding: 16px 24px 20px; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; border-top: 1px solid #f1f5f9; }
        
        .ui-btn {
            padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 0.9rem;
            cursor: pointer; transition: all 0.2s ease; border: none;
        }
        .ui-btn-primary { background: #662331; color: #fff; }
        .ui-btn-primary:hover { background: #501b26; transform: translateY(-1px); }
        .ui-btn-outline { background: #fff; color: #64748b; border: 1px solid #e2e8f0; }
        .ui-btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

        @keyframes ui-fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes ui-slide-up {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .ui-toast-container {
            position: fixed; top: 20px; right: 20px; z-index: 1000001;
            display: flex; flex-direction: column; gap: 10px; pointer-events: none;
        }
        .ui-prompt-input {
            width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0;
            border-radius: 10px; margin-top: 15px; font-size: 0.95rem;
            outline: none; transition: border-color 0.2s;
        }
        .ui-prompt-input:focus { border-color: #662331; box-shadow: 0 0 0 3px rgba(102, 35, 49, 0.1); }
        .ui-toast {
            background: #ffffff; min-width: 280px; max-width: 350px;
            padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 12px; pointer-events: auto;
            animation: ui-toast-in 0.3s ease forwards; border-left: 4px solid #cbd5e1;
            cursor: pointer; position: relative; overflow: hidden;
        }
        .ui-toast--success { border-left-color: #16a34a; }
        .ui-toast--error { border-left-color: #dc2626; }
        .ui-toast-content { font-size: 0.9rem; color: #334155; font-weight: 500; }
        
        @keyframes ui-toast-in {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes ui-toast-out {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
        }
    `;

    function inyectarEstilos() {
        if (document.getElementById('ui-frame-styles')) return;
        const s = document.createElement('style');
        s.id = 'ui-frame-styles';
        s.textContent = UI_STYLES;
        document.head.appendChild(s);
    }

    function createFrame(config) {
        inyectarEstilos();
        const backdrop = document.createElement('div');
        backdrop.className = 'ui-frame-backdrop';
        
        let iconHtml = '';
        if (config.tipo === 'alert') iconHtml = '<div class="ui-frame-icon ui-frame-icon--alert">⚠️</div>';
        if (config.tipo === 'info') iconHtml = '<div class="ui-frame-icon ui-frame-icon--info">ℹ️</div>';
        if (config.tipo === 'confirm') iconHtml = '<div class="ui-frame-icon ui-frame-icon--confirm">❓</div>';

        backdrop.innerHTML = `
            <div class="ui-frame">
                <div class="ui-frame-header">
                    ${iconHtml}
                    <div class="ui-frame-title">${config.titulo || 'Notificación'}</div>
                </div>
                <div class="ui-frame-body">
                    ${config.mensaje}
                    ${config.prompt ? `<input type="${config.inputType || 'text'}" class="ui-prompt-input" placeholder="${config.placeholder || ''}" value="${config.defaultValue || ''}">` : ''}
                </div>
                <div class="ui-frame-footer">
                    ${config.confirmar || config.prompt ? `<button class="ui-btn ui-btn-outline ui-cancel-btn">${config.txtCancel || 'Cancelar'}</button>` : ''}
                    <button class="ui-btn ui-btn-primary ui-ok-btn">${config.txtOk || 'Entendido'}</button>
                </div>
            </div>
        `;

        const close = () => {
            backdrop.style.opacity = '0';
            backdrop.querySelector('.ui-frame').style.transform = 'translateY(10px) scale(0.98)';
            setTimeout(() => backdrop.remove(), 250);
        };

        const okBtn = backdrop.querySelector('.ui-ok-btn');
        okBtn.onclick = () => {
            let val = true;
            if (config.prompt) {
                val = backdrop.querySelector('.ui-prompt-input').value;
            }
            close();
            if (config.onOk) config.onOk(val);
        };

        if (config.confirmar || config.prompt) {
            const cancelBtn = backdrop.querySelector('.ui-cancel-btn');
            cancelBtn.onclick = () => {
                close();
                if (config.onCancel) config.onCancel();
            };
        }

        if (config.prompt) {
            const input = backdrop.querySelector('.ui-prompt-input');
            setTimeout(() => input.focus(), 300);
            input.onkeydown = (e) => {
                if (e.key === 'Enter') okBtn.click();
                if (e.key === 'Escape') backdrop.querySelector('.ui-cancel-btn').click();
            };
        }

        document.body.appendChild(backdrop);
    }

    function createToast(mensaje, tipo = 'info', duration = 5000, config = {}) {
        inyectarEstilos();
        let container = document.querySelector('.ui-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'ui-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `ui-toast ui-toast--${tipo}`;
        
        let icon = 'ℹ️';
        if (tipo === 'success') icon = '✅';
        if (tipo === 'error')   icon = '❌';

        toast.innerHTML = `
            <div style="font-size: 1.2rem;">${icon}</div>
            <div class="ui-toast-content">${mensaje}</div>
            ${config.fixed ? '<div style="font-size: 0.7rem; color: #94a3b8; margin-left: auto;">✕</div>' : ''}
        `;

        const closeToast = () => {
            if (toast.dataset.closed) return;
            toast.dataset.closed = 'true';
            toast.style.animation = 'ui-toast-out 0.3s ease forwards';
            setTimeout(() => {
                toast.remove();
                if (config.onClose) config.onClose();
            }, 300);
        };

        toast.onclick = closeToast;
        if (!config.fixed) {
            setTimeout(closeToast, duration);
        }

        container.appendChild(toast);
    }

    window.COMECyTUI = {
        alert: (msg, titulo = 'Atención') => {
            createFrame({ tipo: 'alert', titulo, mensaje: msg });
        },
        info: (msg, titulo = 'Información') => {
            createFrame({ tipo: 'info', titulo, mensaje: msg });
        },
        toast: (msg, tipo = 'info', config = {}) => {
            let duration = typeof config === 'number' ? config : (config.duration || 5000);
            let cfg = typeof config === 'object' ? config : {};
            createToast(msg, tipo, duration, cfg);
        },
        confirm: (msg, onOk, onCancel, config = {}) => {
            createFrame({
                tipo: 'confirm',
                titulo: config.titulo || 'Confirmar acción',
                mensaje: msg,
                confirmar: true,
                onOk,
                onCancel,
                txtOk: config.txtOk,
                txtCancel: config.txtCancel
            });
        },
        prompt: (msg, onOk, config = {}) => {
            createFrame({
                tipo: 'info',
                titulo: config.titulo || 'Entrada de datos',
                mensaje: msg,
                prompt: true,
                onOk,
                onCancel: config.onCancel,
                placeholder: config.placeholder,
                defaultValue: config.defaultValue,
                txtOk: config.txtOk || 'Aceptar',
                txtCancel: config.txtCancel || 'Cancelar'
            });
        }
    };

})();
