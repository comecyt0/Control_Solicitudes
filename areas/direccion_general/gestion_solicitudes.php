<?php
/**
 * COMECyT Control de Solicitudes
 * Dirección General — Gestión Editorial de Solicitudes Institucionales
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pageTitle  = 'Gestión de Solicitudes Institucionales';
$activeMenu = 'solicitudes'; // Podríamos crear uno nuevo si hace falta

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-bell"></i> Solicitudes de Espacio Pendientes</h2>
        <p style="color: #64748b; font-size: 0.9rem;">Revise, edite y oficialice las solicitudes ciudadanas para el calendario institucional.</p>
    </div>
    
    <div id="listaSolicitudesPendientes" style="margin-top: 20px;">
        <div style="text-align: center; padding: 50px;">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: var(--color-primary);"></i>
            <p style="margin-top: 10px; color: #64748b;">Cargando solicitudes...</p>
        </div>
    </div>
</div>

<!-- Modal: Revisar y Editar Solicitud -->
<div class="modal-backdrop" id="modalRevisionDG">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Control Editorial de Solicitud</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalRevisionDG')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="formEditorialDG" onsubmit="guardarYProcesar(event)">
            <input type="hidden" id="ed_id" name="id">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label class="form-label">Título del Evento (Editable)</label>
                    <input type="text" id="ed_titulo" name="titulo" class="form-control" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Descripción / Detalles</label>
                    <textarea id="ed_descripcion" name="descripcion" class="form-control" rows="4"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;" class="mb-16">
                    <div class="form-group">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="datetime-local" id="ed_fecha_inicio" name="fecha_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha Fin</label>
                        <input type="datetime-local" id="ed_fecha_fin" name="fecha_fin" class="form-control" required>
                    </div>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Color Distintivo</label>
                    <input type="color" id="ed_color" name="color" class="form-control" style="height: 45px; padding: 5px;">
                </div>
                
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                
                <div class="form-group mb-16">
                    <label class="form-label">Motivo de Rechazo (Sólo si aplica)</label>
                    <textarea id="ed_motivo" name="motivo" class="form-control" placeholder="Indique por qué se rechaza la solicitud..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalRevisionDG')">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="procesarSolicitud('rechazado')"><i class="fa-solid fa-ban"></i> Rechazar</button>
                <button type="button" class="btn btn-primary" onclick="procesarSolicitud('aceptado')"><i class="fa-solid fa-check"></i> Aprobar y Publicar</button>
            </div>
        </form>
    </div>
</div>

<script>
let solicitudesCache = [];

function cargarSolicitudes() {
    const zona = document.getElementById('listaSolicitudesPendientes');
    fetch('api/solicitudes_dg.php?accion=listar_pendientes')
        .then(r => r.json())
        .then(d => {
            if (!d.ok) throw new Error(d.error);
            solicitudesCache = d.solicitudes;
            
            if (solicitudesCache.length === 0) {
                zona.innerHTML = `
                    <div style="text-align: center; padding: 60px; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1;">
                        <i class="fa-solid fa-calendar-check fa-3x" style="color: #94a3b8; margin-bottom: 20px;"></i>
                        <p style="color: #64748b; font-size: 1.1rem;">No hay solicitudes de espacio pendientes por revisar.</p>
                    </div>
                `;
                return;
            }

            let html = '<div style="display: flex; flex-direction: column; gap: 12px;">';
            solicitudesCache.forEach(s => {
                html += `
                    <div style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 700; color: var(--color-primary); font-size: 1.1rem; margin-bottom: 5px;">${escapeHtml(s.titulo)}</div>
                            <div style="display: flex; gap: 15px; font-size: 0.85rem; color: #64748b;">
                                <span><i class="fa-solid fa-user"></i> ${escapeHtml(s.solicitante_nombre)}</span>
                                <span><i class="fa-solid fa-calendar"></i> ${formatFechaDisplay(s.fecha_inicio)}</span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn btn-primary btn-sm" onclick="abrirRevision(${s.id})">
                                <i class="fa-solid fa-pen-to-square"></i> Revisar y Procesar
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            zona.innerHTML = html;
        })
        .catch(err => {
            zona.innerHTML = `<p style="color: var(--color-danger); text-align: center; padding: 20px;">Error: ${err.message}</p>`;
        });
}

function abrirRevision(id) {
    const s = solicitudesCache.find(x => parseInt(x.id) === id);
    if (!s) return;

    document.getElementById('ed_id').value = s.id;
    document.getElementById('ed_titulo').value = s.titulo;
    document.getElementById('ed_descripcion').value = s.descripcion || '';
    document.getElementById('ed_fecha_inicio').value = s.fecha_inicio.substring(0, 16);
    document.getElementById('ed_fecha_fin').value = s.fecha_fin.substring(0, 16);
    document.getElementById('ed_color').value = s.color || '#662331';
    document.getElementById('ed_motivo').value = '';

    abrirModal('modalRevisionDG');
}

function procesarSolicitud(estatus) {
    const id = document.getElementById('ed_id').value;
    const motivo = document.getElementById('ed_motivo').value.trim();

    if (estatus === 'rechazado' && !motivo) {
        COMECyTUI.alert('Debe indicar un motivo para el rechazo.', 'Atención');
        return;
    }

    // Paso 1: Primero actualizamos los datos editados (si se aprueba)
    if (estatus === 'aceptado') {
        const formData = new FormData(document.getElementById('formEditorialDG'));
        formData.append('accion', 'editar');
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

        fetch('api/solicitudes_dg.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    finalizarGestion(id, estatus, motivo);
                } else {
                    COMECyTUI.alert('Error al actualizar datos: ' + d.error);
                }
            });
    } else {
        finalizarGestion(id, estatus, motivo);
    }
}

function finalizarGestion(id, estatus, motivo) {
    const fd = new FormData();
    fd.append('accion', 'gestionar');
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    fd.append('id', id);
    fd.append('estatus', estatus);
    fd.append('motivo', motivo);

    fetch('api/solicitudes_dg.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                cerrarModal('modalRevisionDG');
                cargarSolicitudes();
                COMECyTUI.info(d.mensaje, 'Gobernanza');
            } else {
                COMECyTUI.alert('Error: ' + d.error);
            }
        });
}

function formatFechaDisplay(s) {
    const d = new Date(s);
    return d.toLocaleString('es-MX', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function escapeHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', cargarSolicitudes);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
