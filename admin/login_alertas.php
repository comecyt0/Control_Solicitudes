<?php
/**
 * Gestión de Alertas de Login — Rediseño Premium
 * COMECyT — Control de Solicitudes
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

inicializarSesion();
verificarSesionAdmin();

$pageTitle = "Centro de Mensajes Institucionales";
$activeMenu = "login_alertas";

require_once __DIR__ . '/../includes/header_admin.php';
?>

<div class="premium-dashboard-container">
    <!-- Header Refinado -->
    <div class="content-header-premium">
        <div class="header-main-info">
            <h1 class="gradient-text"><i class="fa-solid fa-bullhorn animate-bounce-slow"></i> Alertas de Sistema</h1>
            <p class="subtitle">Gestiona la comunicación visual previa al acceso administrativo.</p>
        </div>
        <div class="header-actions">
            <button class="btn-premium btn-icon-text" onclick="abrirModalCrear()">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Nueva Alerta</span>
            </button>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="stats-mini-grid">
        <div class="stat-mini-card">
            <div class="icon-box blue"><i class="fa-solid fa-layer-group"></i></div>
            <div class="info">
                <span class="label">Total Alertas</span>
                <h3 id="statTotal">0</h3>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="icon-box green"><i class="fa-solid fa-check-circle"></i></div>
            <div class="info">
                <span class="label">Activas Hoy</span>
                <h3 id="statActivas">0</h3>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="icon-box amber"><i class="fa-solid fa-clock"></i></div>
            <div class="info">
                <span class="label">Última Actividad</span>
                <h3 id="statUltima">--</h3>
            </div>
        </div>
    </div>

    <!-- Grid de Alertas (Vista Visual) -->
    <div class="admin-card-glass">
        <div class="card-header">
            <h3><i class="fa-solid fa-list-ul"></i> Registro del Portal</h3>
            <div class="card-options">
                <button class="btn-icon" title="Refrescar" onclick="cargarAlertas()"><i class="fa-solid fa-rotate"></i></button>
            </div>
        </div>
        <div class="table-responsive-premium">
            <table class="table-modern" id="tablaAlertas">
                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th width="150">Vista Previa</th>
                        <th>Detalles del Aviso</th>
                        <th width="150">Visualización</th>
                        <th width="150">Fecha</th>
                        <th width="120" style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaAlertas">
                    <!-- Se llena vía AJAX -->
                </tbody>
            </table>
            <div id="emptyState" class="empty-state-luxury" style="display:none;">
                <div class="luxury-icon"><i class="fa-solid fa-images"></i></div>
                <h4>Sin alertas configuradas</h4>
                <p>Comienza creando una nueva alerta para informar a los usuarios.</p>
                <button class="btn-outline-premium" onclick="abrirModalCrear()">Crear Primera Alerta</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Rediseño Glassmorphism -->
<div id="modalAlerta" class="modal-glass-overlay" style="display:none;">
    <div class="modal-glass-content">
        <div class="modal-glass-header">
            <div class="header-title">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <div>
                    <h3>Configurar Aviso</h3>
                    <span>Personaliza el mensaje del portal</span>
                </div>
            </div>
            <button class="modal-glass-close" onclick="cerrarModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-glass-body">
            <form id="formAlerta" onsubmit="guardarAlerta(event)" enctype="multipart/form-data" class="premium-form">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="premium-label"><i class="fa-solid fa-heading"></i> Título de Referencia</label>
                    <input type="text" id="titulo" name="titulo" required class="premium-input" placeholder="Ej: Mantenimiento Preventivo">
                </div>
                
                <div class="form-group">
                    <label class="premium-label"><i class="fa-solid fa-image"></i> Multimedia de Alerta</label>
                    <div class="dropzone-premium" id="dropzone" onclick="document.getElementById('imagen').click()">
                        <div class="dropzone-info" id="dropzoneInfo">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p><strong>Haz clic para subir</strong> o arrastra una imagen</p>
                            <span>JPG, PNG o WEBP (Máx 2MB)</span>
                        </div>
                        <div class="preview-zone" id="previewZone" style="display:none;">
                            <img id="imagePreview" src="" alt="Preview">
                            <div class="preview-overlay">
                                <i class="fa-solid fa-arrows-rotate"></i> Cambiar Imagen
                            </div>
                        </div>
                        <input type="file" id="imagen" name="imagen" accept="image/*" style="display:none;" onchange="handleImageSelect(this)">
                    </div>
                </div>

                <div class="modal-actions-premium">
                    <button type="button" class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn-save-premium" id="btnGuardar">
                        <i class="fa-solid fa-paper-plane"></i> Publicar Ahora
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Dashboard Luxury Layout */
.premium-dashboard-container {
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.content-header-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 35px;
}

.gradient-text {
    background: linear-gradient(135deg, var(--color-primary), #B19A6D);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0;
}

.subtitle {
    color: #64748b;
    margin: 5px 0 0 0;
    font-size: 1rem;
}

.btn-premium {
    background: linear-gradient(135deg, var(--color-primary), #8a1d31);
    color: #fff;
    border: none;
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 10px 20px rgba(102, 35, 49, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-premium:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(102, 35, 49, 0.3);
}

/* Stats Grid */
.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-mini-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    border: 1px solid #f1f5f9;
}

.icon-box {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}

.icon-box.blue { background: #eff6ff; color: #3b82f6; }
.icon-box.green { background: #f0fdf4; color: #22c55e; }
.icon-box.amber { background: #fffbeb; color: #f59e0b; }

.stat-mini-card .label { font-size: 0.85rem; color: #64748b; color: #64748b; }
.stat-mini-card h3 { margin: 2px 0 0 0; font-size: 1.5rem; font-weight: 700; color: #1e293b; }

/* Main Card Glass */
.admin-card-glass {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card-header {
    padding: 25px 30px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 { margin: 0; font-size: 1.1rem; color: #1e293b; }

.table-modern {
    width: 100%;
    border-collapse: collapse;
}

.table-modern th {
    background: #f8fafc;
    padding: 15px 30px;
    text-align: left;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    border-bottom: 1px solid #f1f5f9;
}

.table-modern td {
    padding: 20px 30px;
    border-bottom: 1px solid #f8fafc;
    color: #334155;
    vertical-align: middle;
}

.preview-container {
    width: 100px;
    height: 65px;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.preview-tile {
    width: 100%; height: 100%; object-fit: cover;
}

.alert-info-cell h4 { margin: 0 0 4px 0; font-size: 1rem; color: #1e293b; }
.alert-info-cell span { font-size: 0.85rem; color: #64748b; }

/* Luxury Switch */
.luxury-switch {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    gap: 10px;
}

.switch-track {
    width: 44px; height: 24px;
    background: #e2e8f0;
    border-radius: 20px;
    position: relative;
    transition: all 0.3s;
}

.switch-thumb {
    width: 18px; height: 18px;
    background: #fff;
    border-radius: 50%;
    position: absolute;
    top: 3px; left: 3px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

input:checked + .switch-track { background: #22c55e; }
input:checked + .switch-track .switch-thumb { left: 23px; }

/* Modal Glassmorphism */
.modal-glass-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index: 10000;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}

.modal-glass-content {
    background: rgba(255, 255, 255, 0.95);
    width: 100%; max-width: 550px;
    border-radius: 30px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    animation: modalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes modalPop {
    from { transform: scale(0.9) translateY(30px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}

.modal-glass-header {
    padding: 30px;
    background: #fff;
    border-bottom: 1px solid #f1f5f9;
    display: flex; justify-content: space-between; align-items: flex-start;
}

.header-title { display: flex; gap: 15px; }
.header-title i { font-size: 1.8rem; color: var(--color-primary); background: #fff5f7; width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center; }
.header-title h3 { margin: 0; font-size: 1.25rem; }
.header-title span { font-size: 0.9rem; color: #64748b; }

.modal-glass-body { padding: 30px; }

.dropzone-premium {
    border: 2px dashed #cbd5e1;
    border-radius: 20px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: #f8fafc;
    position: relative;
    overflow: hidden;
}

.dropzone-premium:hover { border-color: var(--color-primary); background: #fff; }

.dropzone-info i { font-size: 2.5rem; color: #94a3b8; margin-bottom: 15px; }

.preview-zone { width: 100%; position: relative; border-radius: 15px; overflow: hidden; }
.preview-zone img { width: 100%; height: 200px; object-fit: cover; }
.preview-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); color: #fff; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; font-weight: 600; gap: 10px; }
.preview-zone:hover .preview-overlay { opacity: 1; }

.modal-actions-premium {
    display: flex; gap: 15px; margin-top: 30px;
}

.btn-save-premium {
    flex: 1; height: 54px;
    background: var(--color-primary); color: #fff;
    border: none; border-radius: 15px;
    font-size: 1rem; font-weight: 700; cursor: pointer;
    transition: 0.3s;
}

.btn-save-premium:hover { background: #8a1d31; box-shadow: 0 10px 20px rgba(102, 35, 49, 0.2); }

.btn-cancel {
    padding: 0 25px; height: 54px;
    background: #f1f5f9; color: #475569;
    border: none; border-radius: 15px;
    font-weight: 600; cursor: pointer;
}

/* Animations */
.animate-bounce-slow {
    animation: bounce-slow 3s infinite;
}
@keyframes bounce-slow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.empty-state-luxury {
    padding: 80px 40px; text-align: center;
}
.luxury-icon { font-size: 4rem; color: #e2e8f0; margin-bottom: 20px; }
.empty-state-luxury h4 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.empty-state-luxury p { color: #64748b; margin: 10px 0 25px 0; }
.btn-outline-premium {
    border: 2px solid #e2e8f0; background: transparent; padding: 10px 25px; border-radius: 12px; font-weight: 600; color: #475569; cursor: pointer; transition: 0.3s;
}
.btn-outline-premium:hover { border-color: var(--color-primary); color: var(--color-primary); }
</style>

<script>
const API = window.BASE_URL + 'admin/api/login_alertas.php';
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

document.addEventListener('DOMContentLoaded', cargarAlertas);

async function cargarAlertas() {
    try {
        const r = await fetch(API + '?accion=listar');
        const data = await r.json();
        
        const tbody = document.getElementById('listaAlertas');
        const emptyState = document.getElementById('emptyState');
        tbody.innerHTML = '';

        if(!data.ok || data.alertas.length === 0) {
            emptyState.style.display = 'block';
            actualizarStats([]);
            return;
        }

        emptyState.style.display = 'none';
        data.alertas.forEach(a => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="badge-id">#${a.id}</span></td>
                <td>
                    <div class="preview-container">
                        <img src="${window.BASE_URL}${a.imagen_path}" class="preview-tile" onerror="this.src='https://placehold.co/200x120?text=Error'">
                    </div>
                </td>
                <td>
                    <div class="alert-info-cell">
                        <h4>${a.titulo}</h4>
                        <span><i class="fa-regular fa-user"></i> Creado por Admin</span>
                    </div>
                </td>
                <td>
                    <label class="luxury-switch">
                        <input type="checkbox" style="display:none" ${a.activo ? 'checked' : ''} onchange="toggleAlerta(${a.id}, this.checked)">
                        <div class="switch-track"><div class="switch-thumb"></div></div>
                    </label>
                </td>
                <td>
                    <div class="date-cell">
                        <strong>${new Date(a.creado_en).toLocaleDateString()}</strong>
                        <div style="font-size:0.75rem; color:#94a3b8">${new Date(a.creado_en).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                    </div>
                </td>
                <td style="text-align: right;">
                    <button class="btn-icon btn-red" onclick="eliminarAlerta(${a.id})" title="Eliminar Alerta">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        actualizarStats(data.alertas);
    } catch (e) {
        console.error(e);
        COMECyTUI.toast('Error', 'No se pudieron cargar las alertas', 'error');
    }
}

function actualizarStats(alertas) {
    document.getElementById('statTotal').textContent = alertas.length;
    document.getElementById('statActivas').textContent = alertas.filter(a => a.activo).length;
    if(alertas.length > 0) {
        const ultima = new Date(alertas[0].creado_en);
        document.getElementById('statUltima').textContent = ultima.toLocaleDateString();
    }
}

function abrirModalCrear() {
    document.getElementById('modalAlerta').style.display = 'flex';
    document.getElementById('formAlerta').reset();
    document.getElementById('dropzoneInfo').style.display = 'block';
    document.getElementById('previewZone').style.display = 'none';
}

function cerrarModal() {
    document.getElementById('modalAlerta').style.display = 'none';
}

function handleImageSelect(input) {
    if (input.files && input.files[0]) {
        // Validar tamaño (2MB)
        if(input.files[0].size > 2 * 1024 * 1024) {
            COMECyTUI.toast('Archivo muy grande', 'La imagen no debe superar los 2MB', 'error');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('dropzoneInfo').style.display = 'none';
            document.getElementById('previewZone').style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

async function guardarAlerta(e) {
    e.preventDefault();
    
    // Validación básica cliente
    const titulo = document.getElementById('titulo').value.trim();
    const imagen = document.getElementById('imagen').files.length;
    
    if(!titulo || imagen === 0) {
        COMECyTUI.toast('Campos requeridos', 'Debes ingresar un título y seleccionar una imagen', 'warning');
        return;
    }

    const btn = document.getElementById('btnGuardar');
    const originalContent = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Publicando...';

    const formData = new FormData(e.target);
    formData.append('accion', 'crear');
    formData.append('csrf_token', CSRF_TOKEN);

    try {
        const r = await fetch(API, { 
            method: 'POST', 
            body: formData 
        });
        
        const textResponse = await r.text();
        let data;
        try {
            data = JSON.parse(textResponse);
        } catch(jsonErr) {
            console.error("Respuesta no JSON:", textResponse);
            throw new Error("Respuesta del servidor no válida");
        }

        if(data.ok) {
            COMECyTUI.toast('¡Publicada!', data.msg, 'success');
            // Cierre inmediato y recarga diferida
            cerrarModal();
            setTimeout(cargarAlertas, 300);
        } else {
            COMECyTUI.toast('Atención', data.msg, 'error');
        }
    } catch (err) {
        console.error(err);
        COMECyTUI.toast('Fallo Crítico', err.message || 'No se pudo procesar la solicitud', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        // Fallback de seguridad para cerrar modal si se quedó trabado
        setTimeout(() => {
            if(document.getElementById('modalAlerta').style.display === 'flex') {
                cerrarModal();
            }
        }, 1500);
    }
}

async function toggleAlerta(id, activo) {
    try {
        const formData = new FormData();
        formData.append('accion', 'toggle');
        formData.append('id', id);
        formData.append('activo', activo);
        formData.append('csrf_token', CSRF_TOKEN);

        const r = await fetch(API, { method: 'POST', body: formData });
        const data = await r.json();
        
        if(data.ok) {
            COMECyTUI.toast('Actualizado', 'Estado de la alerta sincronizado', 'success');
            cargarAlertas(); // Recargar para actualizar stats
        }
    } catch(e) {
        COMECyTUI.toast('Error', 'Fallo al comunicar con el servidor', 'error');
    }
}

async function eliminarAlerta(id) {
    COMECyTUI.confirm('¿Deseas retirar esta alerta del portal permanentemente?', async (confirmado) => {
        if (!confirmado) return;

        const formData = new FormData();
        formData.append('accion', 'eliminar');
        formData.append('id', id);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const r = await fetch(API, { method: 'POST', body: formData });
            const data = await r.json();
            if(data.ok) {
                COMECyTUI.toast('Eliminado', 'La alerta ha sido removida', 'success');
                cargarAlertas();
            }
        } catch(e) {
            COMECyTUI.toast('Error', 'No se pudo eliminar el registro', 'error');
        }
    }, null, { titulo: "Eliminar Alerta" });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
