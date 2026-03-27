<?php
/**
 * Gestión de Alertas de Login
 * COMECyT — Control de Solicitudes
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/auth.php';

inicializarSesion();
verificarSesionAdmin();

$pageTitle = "Gestión de Alertas de Login";
$activeMenu = "login_alertas";

require_once __DIR__ . '/includes/header_admin.php';
?>

<div class="content-header">
    <div class="header-info">
        <h1><i class="fa-solid fa-triangle-exclamation"></i> <?= $pageTitle ?></h1>
        <p>Administra las imágenes que se muestran en la pantalla de login.</p>
    </div>
    <div class="header-actions">
        <button class="btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nueva Alerta
        </button>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table-premium" id="tablaAlertas">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Imagen</th>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Creado En</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="listaAlertas">
                <!-- Se llena vía AJAX -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Crear Alerta -->
<div id="modalAlerta" class="modal-premium-overlay" style="display:none;">
    <div class="modal-premium">
        <div class="modal-premium-header">
            <h3><i class="fa-solid fa-plus-circle"></i> Nueva Alerta de Login</h3>
            <button class="modal-close" onclick="cerrarModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-premium-body">
            <form id="formAlerta" onsubmit="guardarAlerta(event)" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="field">
                    <label for="titulo">Título de la Alerta *</label>
                    <input type="text" id="titulo" name="titulo" required placeholder="Ej: Aviso de Mantenimiento">
                </div>
                <div class="field">
                    <label for="imagen">Imagen (Recomendado 1200x800px) *</label>
                    <div class="upload-zone" onclick="document.getElementById('imagen').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span id="fileNameDisplay">Seleccionar imagen o soltar aquí</span>
                        <input type="file" id="imagen" name="imagen" accept="image/*" style="display:none;" onchange="updateFileName(this)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-primary" id="btnGuardar">
                        <i class="fa-solid fa-save"></i> Guardar Alerta
                    </button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.preview-img-row {
    width: 60px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}
.upload-zone {
    border: 2px dashed #d1d5db;
    padding: 30px;
    text-align: center;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    background: #f9fafb;
    color: #6b7280;
}
.upload-zone:hover {
    border-color: var(--color-primary);
    background: #fff;
    color: var(--color-primary);
}
.modal-premium-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-premium {
    background: #fff;
    width: 100%;
    max-width: 500px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: modalSlideUp 0.3s ease-out;
}
@keyframes modalSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-premium-header {
    background: var(--color-primary);
    color: #fff;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-premium-header h3 { margin: 0; font-size: 1.1rem; }
.modal-close { background: none; border: none; color: #fff; cursor: pointer; font-size: 1.2rem; }
.modal-premium-body { padding: 25px; }
.modal-footer { margin-top: 20px; display: flex; gap: 10px; }
</style>

<script>
const API = window.BASE_URL + 'admin/api/login_alertas.php';
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

document.addEventListener('DOMContentLoaded', cargarAlertas);

async function cargarAlertas() {
    try {
        const r = await fetch(API + '?accion=listar');
        const data = await r.json();
        if(!data.ok) return;

        const tbody = document.getElementById('listaAlertas');
        tbody.innerHTML = '';
        data.alertas.forEach(a => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${a.id}</td>
                <td><img src="${window.BASE_URL}${a.imagen_path}" class="preview-img-row"></td>
                <td><strong>${a.titulo}</strong></td>
                <td>
                    <label class="switch-premium">
                        <input type="checkbox" ${a.activo ? 'checked' : ''} onchange="toggleAlerta(${a.id}, this.checked)">
                        <span class="slider-premium"></span>
                    </label>
                </td>
                <td>${new Date(a.creado_en).toLocaleDateString()}</td>
                <td>
                    <button class="btn-sm btn-danger" onclick="eliminarAlerta(${a.id})" title="Eliminar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
    }
}

function abrirModalCrear() {
    document.getElementById('modalAlerta').style.display = 'flex';
    document.getElementById('formAlerta').reset();
    document.getElementById('fileNameDisplay').textContent = 'Seleccionar imagen o soltar aquí';
}

function cerrarModal() {
    document.getElementById('modalAlerta').style.display = 'none';
}

function updateFileName(input) {
    const display = document.getElementById('fileNameDisplay');
    display.textContent = input.files.length > 0 ? input.files[0].name : 'Seleccionar imagen o soltar aquí';
}

async function guardarAlerta(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';

    const formData = new FormData(e.target);
    formData.append('accion', 'crear');
    formData.append('csrf_token', CSRF_TOKEN);

    try {
        const r = await fetch(API, { method: 'POST', body: formData });
        const data = await r.json();
        if(data.ok) {
            COMECyTUI.toast('¡Éxito!', data.msg, 'success');
            cerrarModal();
            cargarAlertas();
        } else {
            COMECyTUI.toast('Error', data.msg, 'error');
        }
    } catch (err) {
        COMECyTUI.toast('Error', 'No se pudo conectar con el servidor', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-save"></i> Guardar Alerta';
    }
}

async function toggleAlerta(id, activo) {
    const formData = new FormData();
    formData.append('accion', 'toggle');
    formData.append('id', id);
    formData.append('activo', activo);
    formData.append('csrf_token', CSRF_TOKEN);

    try {
        await fetch(API, { method: 'POST', body: formData });
        COMECyTUI.toast('Actualizado', 'Estado de alerta modificado', 'info');
    } catch(e) {}
}

async function eliminarAlerta(id) {
    COMECyTUI.confirm('¿Estás seguro de eliminar esta alerta? Esta acción no se puede deshacer.', async (confirmado) => {
        if (!confirmado) return;

        const formData = new FormData();
        formData.append('accion', 'eliminar');
        formData.append('id', id);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const r = await fetch(API, { method: 'POST', body: formData });
            const data = await r.json();
            if(data.ok) {
                COMECyTUI.toast('Eliminado', data.msg, 'success');
                cargarAlertas();
            }
        } catch(e) {}
    }, null, { titulo: "Eliminar Alerta" });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
