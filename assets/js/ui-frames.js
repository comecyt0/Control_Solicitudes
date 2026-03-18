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
                <div class="ui-frame-body">${config.mensaje}</div>
                <div class="ui-frame-footer">
                    ${config.confirmar ? `<button class="ui-btn ui-btn-outline ui-cancel-btn">${config.txtCancel || 'Cancelar'}</button>` : ''}
                    <button class="ui-btn ui-btn-primary ui-ok-btn">${config.txtOk || 'Entendido'}</button>
                </div>
            </div>
        `;

        const close = () => {
            backdrop.style.opacity = '0';
            backdrop.querySelector('.ui-frame').style.transform = 'translateY(10px) scale(0.98)';
            setTimeout(() => backdrop.remove(), 250);
        };

        backdrop.querySelector('.ui-ok-btn').onclick = () => {
            close();
            if (config.onOk) config.onOk();
        };

        if (config.confirmar) {
            backdrop.querySelector('.ui-cancel-btn').onclick = () => {
                close();
                if (config.onCancel) config.onCancel();
            };
        }

        document.body.appendChild(backdrop);
    }

    window.COMECyTUI = {
        alert: (msg, titulo = 'Atención') => {
            createFrame({ tipo: 'alert', titulo, mensaje: msg });
        },
        info: (msg, titulo = 'Información') => {
            createFrame({ tipo: 'info', titulo, mensaje: msg });
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
        }
    };

})();
