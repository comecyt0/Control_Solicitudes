/**
 * COMECyT Universal Notification Bell (v16.0)
 * 
 * Gestiona el polling, el badge de la campana y las alertas sonoras
 * integrando todos los módulos administrativos.
 */

class NotificationBell {
    constructor() {
        this.counts = { chat: 0, solicitudes: 0, personal: 0, equipos: 0, calendario: 0 };
        this.total = 0;
        this.lastTotal = parseInt(sessionStorage.getItem('notif_last_total') || '0');
        this.interval = null;
        this.audioEnabled = false;
        
        this.init();
    }

    init() {
        console.log('[Bell] Initializing...');
        const ready = () => {
            this.render();
            this.startPolling();
            this.setupAudioUnlock();
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ready);
        } else {
            ready();
        }
    }

    render() {
        // Soporte para múltiples layouts del sistema
        const header = document.querySelector('.topbar-actions') || 
                       document.querySelector('.header-actions') || 
                       document.querySelector('.top-bar-actions') ||
                       document.querySelector('.navbar-nav'); // Fallback para admin legacy
        
        if (!header) {
            console.warn('[Bell] No injection point found');
            return;
        }

        // Si ya existe, no duplicar
        if (document.getElementById('universal-bell-container')) return;

        const container = document.createElement('div');
        container.id = 'universal-bell-container';
        container.className = 'bell-container';
        container.innerHTML = `
            <button class="bell-button" id="bell-trigger" type="button">
                <i class="fa-solid fa-bell"></i>
                <span class="bell-badge" id="bell-badge" style="display:none;">0</span>
            </button>
            <div class="bell-dropdown" id="bell-dropdown">
                <div class="bell-header">Notificaciones Pendientes</div>
                <div class="bell-items" id="bell-items">
                    <div class="bell-empty">No hay pendientes</div>
                </div>
                <div class="bell-footer">
                    <a href="${window.BASE_URL || '/'}public/router.php" id="view-all-notifications">Ver todo el panel</a>
                </div>
            </div>
        `;

        header.prepend(container);

        // Events
        const trigger = document.getElementById('bell-trigger');
        const dropdown = document.getElementById('bell-dropdown');

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });

        document.addEventListener('click', () => dropdown.classList.remove('active'));
        console.log('[Bell] Rendered successfully');
    }

    startPolling() {
        this.fetch();
        this.interval = setInterval(() => this.fetch(), 3000); // 3s polling (v16.2)
    }

    async fetch() {
        try {
            const baseUrl = window.BASE_URL || '/';
            const isPublic = window.location.pathname.includes('/public/') || !window.location.pathname.includes('/admin/');
            const apiPath = isPublic ? 'public/api/notificaciones.php' : 'admin/api/notificaciones.php';
            
            const resp = await fetch(`${baseUrl}${apiPath}`);
            const data = await resp.json();

            if (data.ok) {
                this.update(data, isPublic);
            }
        } catch (err) {
            // Silencioso en producción si no hay datos
        }
    }

    update(data, isPublic) {
        this.counts = data.pendientes;
        this.total = data.total;

        const badge = document.getElementById('bell-badge');
        if (badge) {
            badge.innerText = this.total > 99 ? '99+' : this.total;
            badge.style.display = this.total > 0 ? 'flex' : 'none';
        }

        this.renderItems(isPublic);

        if (this.total > this.lastTotal) {
            this.notify();
        }

        this.lastTotal = this.total;
        sessionStorage.setItem('notif_last_total', this.total);
    }

    renderItems(isPublic) {
        const list = document.getElementById('bell-items');
        if (!list) return;

        let html = '';
        const baseUrl = window.BASE_URL || '/';
        const adminLabels = {
            chat: { label: 'Mensajes nuevos', icon: 'fa-comments', color: '#3B82F6', url: 'admin/dashboard.php?openChat=1' },
            solicitudes: { label: 'Solicitudes nuevas', icon: 'fa-file-invoice', color: '#10B981', url: 'admin/solicitudes.php' },
            personal: { label: 'Cambios de personal', icon: 'fa-user-clock', color: '#F59E0B', url: 'admin/personal_actualizacion.php' },
            equipos: { label: 'Equipos pendientes', icon: 'fa-laptop-medical', color: '#6366F1', url: 'admin/equipos.php' },
            calendario: { label: 'Agenda / Espacios', icon: 'fa-calendar-day', color: '#EC4899', url: 'admin/calendario.php' }
        };

        const publicLabels = {
            chat: { label: 'Mensajes de seguimiento', icon: 'fa-comment-dots', color: '#3B82F6', url: 'public/historial.php' },
            calendario: { label: 'Respuestas de agenda', icon: 'fa-calendar-check', color: '#F59E0B', url: 'public/calendario.php' },
            solicitudes: { label: 'Actualizaciones de estatus', icon: 'fa-sync', color: '#10B981', url: 'public/historial.php' }
        };

        const configSet = isPublic ? publicLabels : adminLabels;

        let hasItems = false;
        for (const [key, val] of Object.entries(this.counts)) {
            if (val > 0 && configSet[key]) {
                hasItems = true;
                const config = configSet[key];
                html += `
                    <a href="${baseUrl}${config.url}" class="bell-item" ${config.url.includes('openChat=1') ? 'onclick="if(window.toggleChat){window.toggleChat();return false;}"' : ''}>
                        <div class="bell-item-icon" style="background: ${config.color}20; color: ${config.color}">
                            <i class="fa-solid ${config.icon}"></i>
                        </div>
                        <div class="bell-item-content">
                            <div class="bell-item-title">${config.label}</div>
                            <div class="bell-item-count">${val} pendientes</div>
                        </div>
                    </a>
                `;
            }
        }

        list.innerHTML = hasItems ? html : '<div class="bell-empty">No hay acciones pendientes</div>';
    }

    notify() {
        console.log('[Bell] New notification detected');
        if (typeof window.sonido === 'function') {
            window.sonido('chat'); 
        } else {
            this.playAlert();
        }
    }

    playAlert() {
        if (!this.audioEnabled) {
            console.log('[Bell] Audio not unlocked yet');
            return;
        }
        const baseUrl = window.BASE_URL || '/';
        const audio = new Audio(`${baseUrl}assets/notification.mp3`);
        audio.play().catch(e => console.log('Audio playback failed', e));
    }

    setupAudioUnlock() {
        const unlock = () => {
            const baseUrl = window.BASE_URL || '/';
            const audio = new Audio(`${baseUrl}assets/notification.mp3`);
            audio.volume = 0;
            audio.play().then(() => {
                console.log('[Bell] Audio context unlocked');
                this.audioEnabled = true;
                document.removeEventListener('click', unlock);
            }).catch(() => {});
        };
        document.addEventListener('click', unlock);
    }
}

// Iniciar instancia global
if (!window.COMECyTNotificationBell) {
    window.COMECyTNotificationBell = new NotificationBell();
}
