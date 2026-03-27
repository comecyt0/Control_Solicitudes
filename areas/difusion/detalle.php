<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Detalle de Solicitud
 *
 * Muestra toda la informacion de una solicitud individual junto
 * con su historial de cambios de estatus en formato timeline.
 * Permite cambiar el estatus directamente desde esta vista.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/api/notificacion_email.php';

verificarSesionAdmin();

$pdo = getConnection();

$id = (int) getParam('id');
if ($id <= 0) {
    redirigir('admin/solicitudes.php');
}

// -------------------------------------------------------
// Procesar cambio de estatus (POST -> Redirect -> GET)
// -------------------------------------------------------
$mensajeFlash = '';
$tipoFlash    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && postParam('_accion') === 'cambiar_estatus') {
    validarCsrfPost();
    $estatusNuevo = postParam('estatus_nuevo');
    $comentario   = postParam('comentario');
    $estatusValidos = ['pendiente', 'en_proceso', 'completada', 'cancelada'];

    if (in_array($estatusNuevo, $estatusValidos, true)) {
        $stmt = $pdo->prepare("SELECT estatus FROM solicitudes WHERE id = ?");
        $stmt->execute([$id]);
        $actual = $stmt->fetchColumn();

        if ($actual !== false) {
            $nombreAdmin = getNombreAdmin();

            if ($estatusNuevo === 'completada' && $actual !== 'completada') {
                $upd = $pdo->prepare("UPDATE solicitudes SET estatus = ?, resuelto_por = ? WHERE id = ?");
                $upd->execute([$estatusNuevo, $nombreAdmin, $id]);
            } else {
                $upd = $pdo->prepare("UPDATE solicitudes SET estatus = ? WHERE id = ?");
                $upd->execute([$estatusNuevo, $id]);
            }

            $ins = $pdo->prepare(
                "INSERT INTO historial_solicitudes (solicitud_id, estatus_anterior, estatus_nuevo, comentario, usuario_nombre)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ins->execute([$id, $actual, $estatusNuevo, $comentario ?: null, $nombreAdmin]);

            // Recargar solicitud para el email
            $stmtSolEmail = $pdo->prepare('SELECT * FROM solicitudes WHERE id = ?');
            $stmtSolEmail->execute([$id]);
            $solEmail = $stmtSolEmail->fetch();
            if ($solEmail) {
                enviarNotificacionEstatus($solEmail, $estatusNuevo, $comentario, $nombreAdmin);
            }

            header('Location: ' . BASE_URL . 'admin/detalle.php?id=' . $id . '&flash=ok');
            exit;
        }
    }

    $mensajeFlash = 'No se pudo actualizar el estatus.';
    $tipoFlash    = 'error';
}

if (getParam('flash') === 'ok') {
    $mensajeFlash = 'Estatus actualizado correctamente.';
    $tipoFlash    = 'success';
}

// -------------------------------------------------------
// Cargar solicitud
// -------------------------------------------------------
// Cargar solicitud con información del equipo vinculado (si aplica)
$stmt = $pdo->prepare("
    SELECT s.*, 
           b.marca, b.modelo, b.num_serie, b.num_inventario, b.estatus_alta 
    FROM solicitudes s 
    LEFT JOIN sb_bienes b ON s.equipo_id = b.cve_bienes 
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sol = $stmt->fetch();

// Cargar plantillas de respuesta
$plantillas = [];
try {
    $stmtPl = $pdo->query('SELECT id, titulo, contenido FROM plantillas_respuesta ORDER BY admin_id ASC, titulo ASC');
    $plantillas = $stmtPl->fetchAll();
} catch (Throwable $e) {
    // Tabla no existe aún
}

if (!$sol) {
    redirigir('admin/solicitudes.php');
}

// Cargar historial cronologico
$stmtH = $pdo->prepare(
    "SELECT * FROM historial_solicitudes
     WHERE solicitud_id = ?
     ORDER BY fecha_cambio ASC"
);
$stmtH->execute([$id]);
$historial = $stmtH->fetchAll();

// Transiciones validas
$transiciones = [
    'pendiente'  => ['en_proceso', 'cancelada'],
    'en_proceso' => ['completada', 'cancelada'],
    'completada' => [],
    'cancelada'  => [],
];
$opcionesEstatus = $transiciones[$sol['estatus']] ?? [];

// -------------------------------------------------------
// Vista
// -------------------------------------------------------
$pageTitle  = 'Detalle — ' . $sol['folio'];
$activeMenu = 'solicitudes';
$helpPage   = 'detalle';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<a href="<?= BASE_URL ?>admin/solicitudes.php" class="back-link">
    <i class="fa-solid fa-arrow-left"></i>
    Volver al listado
</a>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= esc($tipoFlash) ?>" data-auto-close="4000">
    <i class="fa-solid <?= $tipoFlash === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<!-- Encabezado de la solicitud -->
<div class="card mb-16" style="margin-bottom: 20px;">
    <div class="d-flex align-center justify-between gap-16" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <div class="folio-link" style="font-size: 1.2rem; margin-bottom: 8px;">
                <?= esc($sol['folio']) ?>
            </div>
            <div class="d-flex align-center gap-8" style="flex-wrap: wrap; gap: 8px;">
                <span class="badge <?= getBadgeClase('tipo', $sol['tipo']) ?>">
                    <i class="<?= getIconoTipo($sol['tipo']) ?>"></i>
                    <?= esc(getEtiqueta('tipo', $sol['tipo'])) ?>
                </span>
                <span class="badge <?= getBadgeClase('estatus', $sol['estatus']) ?>">
                    <i class="<?= getIconoEstatus($sol['estatus']) ?>"></i>
                    <?= esc(getEtiqueta('estatus', $sol['estatus'])) ?>
                </span>
                <span class="badge <?= getBadgeClase('prioridad', $sol['prioridad']) ?>">
                    <?= esc(getEtiqueta('prioridad', $sol['prioridad'])) ?>
                </span>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>admin/api/exportar_pdf.php?id=<?= $sol['id'] ?>"
               class="btn btn-outline btn-sm" title="Exportar / Imprimir" id="btn-export-pdf">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </a>
            <?php if (!empty($opcionesEstatus)): ?>
            <button type="button" class="btn btn-primary" onclick="abrirModal('modalEstatus')">
                <i class="fa-solid fa-pen-to-square"></i>
                Cambiar Estatus
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Layout dos columnas -->
<div class="detail-layout">

    <!-- Columna izquierda: Datos -->
    <div>
        <div class="card mb-16" style="margin-bottom: 20px;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fa-solid fa-circle-info"></i>
                    Informacion General
                </h2>
            </div>
            <div class="detail-grid">
                <div class="detail-field">
                    <div class="detail-field-label">Solicitante</div>
                    <div class="detail-field-value"><?= esc($sol['solicitante']) ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Area</div>
                    <div class="detail-field-value"><?= esc($sol['area']) ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Correo electronico</div>
                    <div class="detail-field-value">
                        <?= $sol['email_solicitante']
                            ? '<a href="mailto:' . esc($sol['email_solicitante']) . '">' . esc($sol['email_solicitante']) . '</a>'
                            : '<span class="text-muted">—</span>' ?>
                    </div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Fecha de registro</div>
                    <div class="detail-field-value"><?= formatearFecha($sol['fecha_creacion']) ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Ultima actualizacion</div>
                    <div class="detail-field-value"><?= formatearFecha($sol['fecha_actualizacion']) ?></div>
                </div>
                <?php if (!empty($sol['subtipo_sistemas'])): ?>
                <div class="detail-field" style="grid-column: 1 / -1; background: rgba(139, 92, 246, 0.05); padding: 12px; border-radius: var(--radius-sm); border: 1px solid rgba(139, 92, 246, 0.2); margin-top: 8px;">
                    <div class="detail-field-label" style="color: #8b5cf6;">
                        <i class="fa-solid fa-code-branch"></i> Requerimiento Web / Sistema Solicitado
                    </div>
                    <div class="detail-field-value" style="font-weight: 600; color: #6d28d9;">
                        <?= esc(ETIQUETAS_SUBTIPO_SISTEMAS[$sol['subtipo_sistemas']] ?? $sol['subtipo_sistemas']) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($sol['estatus'] === 'completada' && $sol['resuelto_por']): ?>
                <div class="detail-field" style="grid-column: 1 / -1; background: rgba(22, 163, 74, 0.05); padding: 12px; border-radius: var(--radius-sm); border: 1px solid rgba(22, 163, 74, 0.2); margin-top: 8px;">
                    <div class="detail-field-label" style="color: var(--color-completada);">
                        <i class="fa-solid fa-check-double"></i> Atendido y cerrado por
                    </div>
                    <div class="detail-field-value" style="font-weight: 600;">
                        <?= esc($sol['resuelto_por']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card-header" style="margin-top: 16px;">
                <h3 class="card-title">
                    <i class="fa-solid fa-align-left"></i>
                    Descripcion
                </h3>
            </div>
            <div class="detail-description">
                <?= nl2br(esc($sol['descripcion'])) ?>
            </div>
        </div>

        <?php
        $adjuntosRaw  = $sol['archivos_adjuntos'] ?? null;
        $adjuntosArr  = $adjuntosRaw ? json_decode($adjuntosRaw, true) : null;
        // Fallback al campo legado si la lista nueva está vacía
        if (empty($adjuntosArr) && !empty($sol['archivo_adjunto'])) {
            $adjuntosArr = [$sol['archivo_adjunto']];
        }
        $subtipoLabel = !empty($sol['subtipo_sistemas'])
            ? (ETIQUETAS_SUBTIPO_SISTEMAS[$sol['subtipo_sistemas']] ?? $sol['subtipo_sistemas'])
            : null;
        ?>
        <?php if (!empty($adjuntosArr)): ?>
        <div class="card mb-16" style="margin-bottom: 20px; border-left: 4px solid #8b5cf6;">
            <div class="card-header">
                <h2 class="card-title" style="color: #8b5cf6;">
                    <i class="fa-solid fa-paperclip"></i>
                    Archivos Sistemas / Web
                </h2>
            </div>
            <div class="card-body" style="padding: 20px;">
                <p class="text-muted" style="margin-bottom: 12px; font-size: 0.9rem;">Archivos adjuntados por el usuario:</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($adjuntosArr as $idx => $archivo): ?>
                    <?php 
                        // Extraer nombre original quitando el prefijo interno req_sys_xxxxxxxxxxxxx_
                        $nombreRef = preg_replace('/^req_sys_[^_]+_/', '', $archivo);
                    ?>
                    <a href="<?= BASE_URL ?>public/uploads/solicitudes/<?= esc($archivo) ?>"
                      
                       class="btn btn-sm btn-outline"
                       style="border-color: #8b5cf6; color: #8b5cf6;"
                       download="<?= esc($nombreRef) ?>">
                        <i class="fa-solid fa-download"></i>
                        <?= esc($nombreRef) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($sol['equipo_id'])): ?>
        <!-- Tarjeta de Equipo Vinculado -->
        <div class="card mb-16" style="margin-bottom: 20px; border-left: 4px solid var(--color-accent);">
            <div class="card-header">
                <h2 class="card-title" style="color: var(--color-accent);">
                    <i class="fa-solid fa-laptop-medical"></i>
                    Equipo Informático Vinculado
                </h2>
                <?php if ($sol['estatus_alta'] === 'pendiente'): ?>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.75rem;">
                        <i class="fa-solid fa-clock"></i> Pendiente Autorizar
                    </span>
                <?php endif; ?>
            </div>
            <div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
                <div class="detail-field">
                    <div class="detail-field-label">Marca</div>
                    <div class="detail-field-value" style="font-weight: 600;"><?= esc($sol['marca']) ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Modelo</div>
                    <div class="detail-field-value"><?= esc($sol['modelo']) ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">No. Serie</div>
                    <div class="detail-field-value"><?= !empty($sol['num_serie']) ? esc($sol['num_serie']) : '<span class="text-muted">N/D</span>' ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Inventario COMECyT</div>
                    <div class="detail-field-value"><?= !empty($sol['num_inventario']) ? esc($sol['num_inventario']) : '<span class="text-muted">N/D</span>' ?></div>
                </div>
            </div>
            <div style="margin-top: 1rem; text-align: right;">
                <a href="<?= BASE_URL ?>admin/equipos.php" class="btn btn-outline btn-sm">Ver en Gestor de Equipos <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Columna derecha: Historial + Comentarios -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fa-solid fa-timeline"></i>
                    Historial de Cambios
                </h2>
                <span class="text-muted fs-sm"><?= count($historial) ?> evento<?= count($historial) !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (!empty($historial)): ?>
            <div class="timeline">
                <?php foreach ($historial as $evento): ?>
                <?php
                    $claseEst = str_replace('_', '-', $evento['estatus_nuevo']);
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot dot-<?= esc($claseEst) ?>">
                        <i class="<?= getIconoEstatus($evento['estatus_nuevo']) ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="d-flex align-center gap-8">
                                <?php if ($evento['estatus_anterior']): ?>
                                <span class="badge <?= getBadgeClase('estatus', $evento['estatus_anterior']) ?>" style="font-size: 0.65rem; padding: 1px 7px;">
                                    <?= esc(getEtiqueta('estatus', $evento['estatus_anterior'])) ?>
                                </span>
                                <i class="fa-solid fa-arrow-right text-muted" style="font-size:0.7rem;"></i>
                                <?php endif; ?>
                                <span class="badge <?= getBadgeClase('estatus', $evento['estatus_nuevo']) ?>" style="font-size: 0.65rem; padding: 1px 7px;">
                                    <?= esc(getEtiqueta('estatus', $evento['estatus_nuevo'])) ?>
                                </span>
                            </div>
                            <span class="timeline-date"><?= formatearFecha($evento['fecha_cambio']) ?></span>
                        </div>
                        <?php if ($evento['usuario_nombre']): ?>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">
                            <i class="fa-solid fa-user"></i> Por: <strong><?= esc($evento['usuario_nombre']) ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($evento['comentario']): ?>
                        <p class="timeline-comment"><?= nl2br(esc($evento['comentario'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding: 30px 20px;">
                <i class="fa-solid fa-clock"></i>
                <p>Sin historial registrado.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Estatus -->
<?php if (!empty($opcionesEstatus)): ?>
<div class="modal-backdrop" id="modalEstatus">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-pen-to-square"></i>
                Cambiar Estatus
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEstatus')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="cambiar_estatus">
            <div class="modal-body">
                <p class="text-muted fs-sm mb-16">
                    Solicitud: <strong class="text-accent"><?= esc($sol['folio']) ?></strong>
                </p>
                <div class="form-group">
                    <label class="form-label" for="estatus_nuevo">
                        Nuevo estatus <span class="required">*</span>
                    </label>
                    <select name="estatus_nuevo" id="estatus_nuevo" class="form-control" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($opcionesEstatus as $opcion): ?>
                        <option value="<?= esc($opcion) ?>">
                            <?= esc(getEtiqueta('estatus', $opcion)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($plantillas)): ?>
                <div class="form-group">
                    <label class="form-label" for="plantillaSel">Plantilla de Respuesta</label>
                    <select id="plantillaSel" class="form-control" onchange="aplicarPlantilla(this.value)">
                        <option value="">— Seleccionar plantilla (opcional) —</option>
                        <?php foreach ($plantillas as $pl): ?>
                        <option value="<?= esc($pl['contenido']) ?>"><?= esc($pl['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label" for="comentario">Comentario</label>
                    <textarea name="comentario" id="comentario" class="form-control" rows="3"
                              placeholder="Descripcion de la accion realizada..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEstatus')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Guardar cambio
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Sección de Comentarios Internos -->
<div class="card" style="margin-top:20px; border-left:4px solid var(--color-primary);">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-comments"></i>
            Notas Internas del Equipo TI
        </h2>
        <span class="badge" style="background:rgba(102,35,49,0.1);color:var(--color-primary);border:1px solid rgba(102,35,49,0.2);font-size:0.7rem;">Solo admins</span>
    </div>

    <!-- Lista de comentarios -->
    <div id="comentariosLista" style="margin-bottom:16px;"></div>

    <!-- Formulario de nuevo comentario -->
    <form id="formComentario" style="display:flex;gap:10px;align-items:flex-end;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
        <div style="flex:1;">
            <label class="form-label" for="nuevoComentario">Agregar nota interna</label>
            <textarea id="nuevoComentario" name="comentario" class="form-control" rows="2"
                      placeholder="Escribe una nota solo visible al equipo de TI..."
                      maxlength="2000" style="resize:vertical;"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" id="btnAgregarComentario">
            <i class="fa-solid fa-paper-plane"></i> Enviar
        </button>
    </form>
</div>

<script>
// ── Comentarios internos ────────────────────────────────────────
const SOLICITUD_ID_COMENTARIOS = <?= (int)$sol['id'] ?>;
const CSRF_TOKEN_COMENTARIOS = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';
const BASE_URL_COMENTARIOS    = '<?= BASE_URL ?>';

function cargarComentarios() {
    fetch(BASE_URL_COMENTARIOS + 'admin/api/comentarios.php?solicitud_id=' + SOLICITUD_ID_COMENTARIOS)
        .then(r => r.json())
        .then(data => {
            const lista = document.getElementById('comentariosLista');
            if (!data.ok) return;
            if (!data.comentarios.length) {
                lista.innerHTML = '<p class="text-muted fs-sm" style="padding:8px 0;">Sin notas internas aún.</p>';
                return;
            }
            lista.innerHTML = data.comentarios.map(c => `
                <div data-cid="${c.id}" style="background:var(--bg-muted);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                        <div>
                            <span style="font-size:0.78rem;font-weight:700;color:var(--color-primary);">
                                <i class="fa-solid fa-user"></i> ${c.admin_nombre}
                            </span>
                            <span class="text-muted" style="font-size:0.72rem;margin-left:8px;">${c.fecha_fmt}</span>
                        </div>
                        <button onclick="eliminarComentario(${c.id})" class="btn btn-outline btn-sm" style="padding:3px 7px;color:#F87171;border:none;"
                                title="Eliminar mi nota"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                    <p style="margin:0;font-size:0.88rem;color:var(--text-primary);line-height:1.5;white-space:pre-wrap;">${escapeHtml(c.comentario)}</p>
                </div>
            `).join('');
        })
        .catch(() => {});
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function eliminarComentario(id) {
    if (!confirm('¿Eliminar esta nota?')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN_COMENTARIOS);
    fd.append('accion', 'eliminar');
    fd.append('comentario_id', id);
    fetch(BASE_URL_COMENTARIOS + 'admin/api/comentarios.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => { if (d.ok) cargarComentarios(); })
        .catch(() => {});
}

document.getElementById('formComentario').addEventListener('submit', function(e) {
    e.preventDefault();
    const textarea = document.getElementById('nuevoComentario');
    const texto = textarea.value.trim();
    if (!texto) return;
    const btn = document.getElementById('btnAgregarComentario');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN_COMENTARIOS);
    fd.append('accion', 'agregar');
    fd.append('solicitud_id', SOLICITUD_ID_COMENTARIOS);
    fd.append('comentario', texto);

    fetch(BASE_URL_COMENTARIOS + 'admin/api/comentarios.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                textarea.value = '';
                cargarComentarios();
            } else {
                alert('Error: ' + (d.error || 'No se pudo enviar'));
            }
        })
        .catch(() => alert('Error de red'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar';
        });
});

// Plantillas de respuesta
function aplicarPlantilla(contenido) {
    if (contenido) {
        document.getElementById('comentario').value = contenido;
    }
}

// Cargar comentarios al mostrarse la página
cargarComentarios();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
