<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Pública — Calendario Institucional — v1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionUsuario();

$pageTitle  = 'Calendario Institucional';
$activeMenu = 'calendario';

// FullCalendar v6 CDN
$extraHead = '
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<style>
    :root {
        --fc-button-bg-color: #662331;
        --fc-button-border-color: #662331;
        --fc-button-hover-bg-color: #8b2f42;
        --fc-button-hover-border-color: #8b2f42;
        --fc-event-bg-color: #B19A6D;
        --fc-event-border-color: #B19A6D;
    }
    .calendar-container {
        background: #fff;
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        margin-bottom: 24px;
    }
    #calendar {
        min-height: 600px;
    }
    .fc-header-toolbar {
        margin-bottom: 2rem !important;
    }
    .fc-toolbar-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #662331 !important;
    }
    .fc-event {
        cursor: pointer;
        padding: 2px 4px;
        font-size: 0.85rem;
        border-radius: 4px;
    }
    /* Estilos Modal Solicitud */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.4); 
        backdrop-filter: blur(4px); display: none; align-items: center; 
        justify-content: center; z-index: 9999;
    }
    .modal-card {
        background: #fff; width: 100%; max-width: 450px; 
        padding: 28px; border-radius: 14px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    }
    .modal-card h3 { margin: 0 0 20px; color: #662331; display: flex; align-items: center; gap: 8px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #374151; }
    .form-control { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; }
    .btn-group { display: flex; gap: 12px; margin-top: 24px; }
    .btn-submit { flex: 1; background: #662331; color: #fff; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .btn-cancel { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 12px 20px; border-radius: 8px; cursor: pointer; }
</style>
';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div class="calendar-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin:0; font-size: 1.4rem; color: #334155;">Agenda Institucional</h2>
            <p style="margin:4px 0 0; font-size: 0.85rem; color: #64748b;">Visualice los próximos eventos y solicite el uso de espacios.</p>
        </div>
        <button class="btn btn-primary" onclick="abrirModalSolicitud()">
            <i class="fa-solid fa-calendar-plus"></i> Solicitar Espacio
        </button>
    </div>
    <div id="calendar"></div>
</div>

<!-- Modal Solicitud -->
<div id="modalSolicitud" class="modal-overlay" onclick="cerrarModalSolicitud()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <h3><i class="fa-solid fa-calendar-plus"></i> Nueva Solicitud de Espacio</h3>
        <form id="formSolicitud">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="accion" value="solicitar">
            
            <div class="form-group">
                <label>Título del Evento / Concepto</label>
                <input type="text" name="titulo" class="form-control" required placeholder="Ej: Presentación de Proyectos TI">
            </div>
            
            <div class="form-group">
                <label>Descripción / Detalles</label>
                <textarea name="descripcion" class="form-control" rows="3" placeholder="Indique el propósito, número de personas, requerimientos adicionales..."></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Fecha y Hora Inicio</label>
                    <input type="datetime-local" name="fecha_inicio" class="form-control" required id="fecha_inicio">
                </div>
                <div class="form-group">
                    <label>Fecha y Hora Fin</label>
                    <input type="datetime-local" name="fecha_fin" class="form-control" required id="fecha_fin">
                </div>
            </div>
            
            <div class="form-group">
                <label>Color para el Calendario (Tentativo)</label>
                <div style="display: flex; gap: 8px;">
                    <input type="color" name="color" value="#B19A6D" style="width: 50px; height: 38px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                    <span style="font-size: 0.8rem; color: #64748b; align-self: center;">Pulse para cambiar</span>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn-cancel" onclick="cerrarModalSolicitud()">Cancelar</button>
                <button type="submit" class="btn-submit">Enviar Solicitud</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día'
        },
        events: '<?= BASE_URL ?>public/api/calendario.php?accion=listar',
        eventClick: function(info) {
            alert('Evento: ' + info.event.title + '\n\n' + (info.event.extendedProps.description || 'Sin descripción adicional.'));
        }
    });
    calendar.render();

    // Manejar envío de solicitud
    document.getElementById('formSolicitud').onsubmit = function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        
        fetch('<?= BASE_URL ?>public/api/calendario.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    alert('¡Éxito! Su solicitud ha sido enviada y está pendiente de aprobación por la administración.');
                    cerrarModalSolicitud();
                    this.reset();
                } else {
                    alert('Error: ' + d.error);
                }
            })
            .catch(() => alert('Error de conexión al servidor'));
    };

    // Polling para respuestas (cada 15 segundos)
    setInterval(verificarRespuestas, 15000);
    verificarRespuestas(); // Primera carga
});

function abrirModalSolicitud() {
    document.getElementById('modalSolicitud').style.display = 'flex';
    // Defaults para hoy
    const ahora = new Date();
    ahora.setMinutes(0,0,0);
    const iso = ahora.toISOString().slice(0,16);
    document.getElementById('fecha_inicio').value = iso;
    document.getElementById('fecha_fin').value = iso;
}

function cerrarModalSolicitud() {
    document.getElementById('modalSolicitud').style.display = 'none';
}

function verificarRespuestas() {
    const fd = new FormData();
    fd.append('accion', 'verificar_respuestas');
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

    fetch('<?= BASE_URL ?>public/api/calendario.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok && d.respuestas.length > 0) {
                d.respuestas.forEach(r => mostrarRespuesta(r));
            }
        });
}

function mostrarRespuesta(res) {
    const tipo = res.estatus === 'aceptado' ? 'success' : 'error';
    const titulo = res.estatus === 'aceptado' ? 'Solicitud Aprobada' : 'Solicitud Rechazada';
    const msg = res.estatus === 'aceptado' 
        ? `Tu espacio "${res.titulo}" ha sido agendado.` 
        : `Tu espacio "${res.titulo}" no fue aprobado.\nMotivo: ${res.motivo_rechazo || 'No especificado'}`;

    // Alerta sonora (rehusando lógica de notificaciones si es posible o una nueva)
    reproducirSonido(res.estatus === 'aceptado');

    if (confirm(`${titulo}\n\n${msg}\n\n¿Marcar como leído?`)) {
        marcarLeido(res.id);
    }
}

function marcarLeido(id) {
    const fd = new FormData();
    fd.append('accion', 'marcar_leido');
    fd.append('id', id);
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    fetch('<?= BASE_URL ?>public/api/calendario.php', { method: 'POST', body: fd });
}

function reproducirSonido(exito) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        
        if (exito) {
            osc.frequency.setValueAtTime(523.25, audioCtx.currentTime); // C5
            osc.frequency.exponentialRampToValueAtTime(1046.50, audioCtx.currentTime + 0.3); // C6
        } else {
            osc.frequency.setValueAtTime(220, audioCtx.currentTime); // A3
            osc.frequency.exponentialRampToValueAtTime(110, audioCtx.currentTime + 0.5); // A2
        }
        
        gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
        
        osc.start();
        osc.stop(audioCtx.currentTime + 0.5);
    } catch(e) {}
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
