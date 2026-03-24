<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administración — Gestión de Anuncios
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// Listar todos los anuncios
$stmt = $pdo->query("SELECT * FROM anuncios ORDER BY fecha_creacion DESC");
$anuncios = $stmt->fetchAll();

$pageTitle  = 'Gestión de Anuncios';
$activeMenu = 'anuncios';
$helpPage   = 'anuncios';

require_once __DIR__ . '/../includes/header_admin.php';
?>

<div class="card mb-16">
    <div class="card-header d-flex justify-between align-center">
        <h2 class="card-title">
            <i class="fa-solid fa-bullhorn text-primary"></i>
            Mantenedor de Anuncios
        </h2>
        <button type="button" class="btn btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo Anuncio
        </button>
    </div>

    <?php if (empty($anuncios)): ?>
    <div class="empty-state">
        <i class="fa-regular fa-bell-slash"></i>
        <h3>Sin anuncios registrados</h3>
        <p>Los anuncios que crees aparecerán en la pantalla principal de la intranet.</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Estatus</th>
                    <th>Fecha</th>
                    <th>Autor</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anuncios as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><strong><?= esc($a['titulo']) ?></strong></td>
                    <td>
                        <?php
                            $tInfo = ['info' => 'Informativo', 'success' => 'Éxito', 'warning' => 'Advertencia', 'urgent' => 'Urgente'];
                            $bClass = ['info' => 'primary', 'success' => 'success', 'warning' => 'warning', 'urgent' => 'error'];
                            $tipo = $a['tipo'] ?? 'info';
                        ?>
                        <span class="badge badge-<?= $bClass[$tipo] ?>"><?= $tInfo[$tipo] ?></span>
                    </td>
                    <td>
                        <?php if ($a['activo']): ?>
                            <span class="badge badge-success"><i class="fa-solid fa-check"></i> Activo</span>
                        <?php else: ?>
                            <span class="badge" style="background: #94a3b8; color: #fff;"><i class="fa-solid fa-eye-slash"></i> Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted fs-sm"><?= formatearFecha($a['fecha_creacion']) ?></td>
                    <td class="text-muted fs-sm"><?= esc($a['creado_por']) ?></td>
                    <td style="text-align: right;">
                        <button type="button" class="btn btn-outline btn-icon" title="Editar" 
                                onclick="abrirModalEditar(<?= $a['id'] ?>, '<?= esc(js_escape($a['titulo'])) ?>', '<?= esc(js_escape($a['contenido'])) ?>', '<?= $a['tipo'] ?>', <?= $a['activo'] ? 'true' : 'false' ?>)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="btn btn-outline btn-icon btn-danger" title="Eliminar" onclick="eliminarAnuncio(<?= $a['id'] ?>)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Crear/Editar Anuncio -->
<div class="modal-backdrop" id="modalAnuncio">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal_titulo">Nuevo Anuncio</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalAnuncio')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formAnuncio" onsubmit="guardarAnuncio(event)">
            <input type="hidden" id="a_id" name="id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Título del Anuncio <span class="required">*</span></label>
                    <input type="text" id="a_titulo" class="form-control" required placeholder="Ej. Ventana de mantenimiento este fin de semana">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tipo de Mensaje / Relevancia</label>
                    <select id="a_tipo" class="form-control">
                        <option value="info">Informativo (Azul)</option>
                        <option value="success">Éxito / Logro (Verde)</option>
                        <option value="warning">Advertencia (Amarillo)</option>
                        <option value="urgent">Urgente / Crítico (Rojo)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Imagen / Banner del Anuncio (Opcional)</label>
                    <input type="file" id="a_banner" accept="image/*" class="form-control" style="padding: 6px;">
                    <small class="text-muted">Formatos soportados: JPG, PNG, WEBP. Se recomienda una proporción ancha (ej. 800x200).</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Contenido <span class="required">*</span></label>
                    <textarea id="a_contenido" class="form-control" rows="5" required placeholder="Cuerpo del anuncio..."></textarea>
                </div>

                <div class="form-group" style="margin-top: 1rem; padding: 12px; background: #e0f2fe; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="a_activo" checked style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="a_activo" style="margin: 0; cursor: pointer; font-weight: 600; color: #0369a1; font-size: 0.9rem;">
                        <i class="fa-solid fa-eye"></i> Publicar y Mostrar en la Intranet
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalAnuncio')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardar">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Anuncio
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCrear() {
    document.getElementById('modal_titulo').innerHTML = '<i class="fa-solid fa-bullhorn text-primary"></i> Crear Anuncio';
    document.getElementById('a_id').value = '';
    document.getElementById('a_titulo').value = '';
    document.getElementById('a_contenido').value = '';
    document.getElementById('a_tipo').value = 'info';
    document.getElementById('a_activo').checked = true;
    document.getElementById('a_banner').value = '';
    abrirModal('modalAnuncio');
}

function abrirModalEditar(id, titulo, contenido, tipo, activo) {
    document.getElementById('modal_titulo').innerHTML = '<i class="fa-solid fa-pen text-primary"></i> Editar Anuncio';
    document.getElementById('a_id').value = id;
    document.getElementById('a_titulo').value = titulo;
    document.getElementById('a_contenido').value = contenido;
    document.getElementById('a_tipo').value = tipo;
    document.getElementById('a_activo').checked = activo;
    document.getElementById('a_banner').value = '';
    abrirModal('modalAnuncio');
}

function guardarAnuncio(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';

    const fd = new FormData();
    fd.append('accion', document.getElementById('a_id').value ? 'editar' : 'crear');
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    if (document.getElementById('a_id').value) fd.append('id', document.getElementById('a_id').value);
    fd.append('titulo', document.getElementById('a_titulo').value);
    fd.append('contenido', document.getElementById('a_contenido').value);
    fd.append('tipo', document.getElementById('a_tipo').value);
    fd.append('activo', document.getElementById('a_activo').checked ? 1 : 0);
    
    const bannerFile = document.getElementById('a_banner').files[0];
    if (bannerFile) {
        fd.append('banner', bannerFile);
    }

    fetch('api/anuncios.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                cerrarModal('modalAnuncio');
                COMECyTUI.toast('Anuncio guardado correctamente.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                COMECyTUI.alert('Error: ' + d.error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Anuncio';
            }
        })
        .catch(err => {
            console.error(err);
            COMECyTUI.alert('Hubo un error de conexión.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Anuncio';
        });
}

function eliminarAnuncio(id) {
    COMECyTUI.confirm("¿Estás seguro de eliminar este anuncio de forma permanente?", "Eliminar Anuncio", () => {
        const fd = new FormData();
        fd.append('accion', 'eliminar');
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        fd.append('id', id);

        fetch('api/anuncios.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.ok) {
                    COMECyTUI.toast('Anuncio eliminado.', 'info');
                    setTimeout(() => location.reload(), 800);
                } else {
                    COMECyTUI.alert('Error: ' + d.error);
                }
            });
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
