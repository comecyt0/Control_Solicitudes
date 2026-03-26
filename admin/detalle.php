<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Detalle de Solicitud
 *
 * Muestra toda la informacion de una solicitud individual junto
 * con su historial de cambios de estatus en formato timeline.
 * Permite cambiar el estatus directamente desde esta vista.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';
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

            // Procesar evidencia si se subió una
            if (isset($_FILES['evidencia_archivo']) && $_FILES['evidencia_archivo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['evidencia_archivo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','pdf','docx','doc','xls','xlsx','zip'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/../public/uploads/evidencias/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $newName = 'ev_status_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                        $stmtEv = $pdo->prepare("INSERT INTO solicitud_evidencias (solicitud_id, archivo_nombre, comentario, usuario_nombre) VALUES (?, ?, ?, ?)");
                        $stmtEv->execute([$id, $newName, 'Adjunto al cambiar estatus a: ' . $estatusNuevo, $nombreAdmin]);
                    }
                }
            }

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

require_once __DIR__ . '/../includes/header_admin.php';
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
            <a href="<?= BASE_URL ?>admin/api/exportar_pdf.php?id=<?= $sol['id'] ?>" target="_blank"
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
                       target="_blank"
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

        <!-- Sección de Evidencias (Staff) -->
        <div class="card mb-16" style="margin-top: 20px; border-left: 4px solid var(--color-primary);">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fa-solid fa-file-shield"></i>
                    Evidencias y Adjuntos de Seguimiento
                </h2>
                <button class="btn btn-sm btn-outline" onclick="abrirModalEvidencia()" style="font-size: 0.7rem;">
                    <i class="fa-solid fa-plus"></i> Cargar
                </button>
            </div>
            <div class="card-body" style="padding: 15px;">
                <div id="evidenciasLista" style="display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <!-- Dinámico -->
                </div>
            </div>
        </div>

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
        <form method="POST" action="" enctype="multipart/form-data">
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
                <div class="form-group" style="margin-top: 15px; background: rgba(102, 35, 49, 0.03); padding: 12px; border-radius: 8px; border: 1px dashed rgba(102, 35, 49, 0.2);">
                    <label class="form-label" for="evidencia_archivo">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Adjuntar Evidencia (Opcional)
                    </label>
                    <input type="file" name="evidencia_archivo" id="evidencia_archivo" class="form-control" style="font-size: 0.85rem;">
                    <p class="text-muted" style="font-size: 0.7rem; margin-top: 5px;">Imágenes, PDF, Office o ZIP.</p>
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

    <!-- Notas Internas del equipo (Colapsable) -->
    <details style="margin-top:16px; border:1px solid #e2e8f0; border-radius:var(--radius-md);">
        <summary style="padding:10px 14px; cursor:pointer; font-weight:600; color:var(--text-muted); font-size:13px; background:#f8fafc;">
            <i class="fa-solid fa-lock" style="margin-right:6px;"></i> Notas Internas de Gestión (Solo Staff)
        </summary>
        <div style="padding:16px;">
            <div id="comentariosLista" style="margin-bottom:12px;"></div>
            <form id="formComentario" style="display:flex; gap:8px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                <input type="text" id="nuevoComentario" name="comentario" class="form-control" placeholder="Añadir nota interna rápida..." style="height:36px; font-size:13px;">
                <button type="submit" class="btn btn-primary btn-sm" id="btnAgregarComentario">Guardar</button>
            </form>
        </div>
    </details>
</div>

<!-- CANAL DE COMUNICACIÓN (RETROALIMENTACIÓN) -->
<div class="card" style="margin-top:20px; border-top: 4px solid var(--color-primary); display: flex; flex-direction: column; height: 500px;">
    <div class="card-header" style="border-bottom: 1px solid #eee; padding: 12px 20px; background: linear-gradient(to right, #fff, #fbfbfb);">
        <h2 class="card-title" style="font-size: 1rem; color: var(--color-primary);">
            <i class="fa-solid fa-comments"></i> Canal de Comunicación con el Solicitante
        </h2>
        <div class="d-flex align-center gap-8">
            <span class="badge" style="background:#f1f5f9; color:#64748b; font-size:10px;">Enlace Directo</span>
        </div>
    </div>
    
    <!-- Area de Chat -->
    <div id="retroLista" style="flex: 1; overflow-y: auto; padding: 20px; background: #fafafa; display: flex; flex-direction: column; gap: 4px;">
        <!-- Mensajes dinámicos -->
    </div>

    <!-- Input de Chat -->
    <div style="padding: 16px; background: #fff; border-top: 1px solid #eee;">
        <form id="formRetro" style="display: flex; flex-direction: column; gap: 10px;">
            <div style="display: flex; gap: 8px;">
                <textarea id="retroMensaje" class="form-control" rows="1" placeholder="Enviar mensaje al ciudadano..." 
                          style="flex: 1; resize: none; border-radius: 20px; padding: 10px 18px; line-height: 1.4; border-color: #e2e8f0;"></textarea>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label for="retroArchivo" class="btn btn-outline" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; border-color: #cbd5e1; color: #64748b;" title="Adjuntar archivo">
                        <i class="fa-solid fa-paperclip"></i>
                    </label>
                    <input type="file" id="retroArchivo" style="display: none;">
                    <button type="submit" id="btnEnviarRetro" class="btn btn-primary" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
            <div id="filePreview" style="display: none; font-size: 0.75rem; color: var(--color-primary); padding-left: 10px;">
                <i class="fa-solid fa-paperclip"></i> <b>Archivo seleccionado:</b> <span id="fileName"></span>
                <button type="button" onclick="document.getElementById('retroArchivo').value=''; document.getElementById('filePreview').style.display='none';" style="border:none; background:none; color:#ef4444; margin-left:8px; cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Manejo de preview de archivo
document.getElementById('retroArchivo').addEventListener('change', function() {
    const preview = document.getElementById('filePreview');
    const nameSpan = document.getElementById('fileName');
    if (this.files && this.files[0]) {
        nameSpan.textContent = this.files[0].name;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

<script>
// ── Comentarios internos ────────────────────────────────────────
const SOLICITUD_ID_COMENTARIOS = <?= (int)$sol['id'] ?>;
const CSRF_TOKEN_COMENTARIOS = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';
const BASE_URL_COMENTARIOS    = '<?= rtrim(BASE_URL, "/") ?>/';

// Depuración en consola
console.log('UI Evidencias cargando para solicitud: ' + SOLICITUD_ID_COMENTARIOS);
if (typeof COMECyTUI === 'undefined') {
    console.error('ALERTA: COMECyTUI no se cargó correctamente.');
    window.COMECyTUI = {
        alert: (m) => alert(m),
        confirm: (m, ok) => { if(confirm(m)) ok(); },
        prompt: (m, ok) => { let v = prompt(m); if(v !== null) ok(v); },
        toast: (m) => console.log('Toast: ' + m)
    };
}

// ── Retroalimentación y Seguimiento (Chat Público-Admin) ──────
function cargarRetroalimentacion() {
    fetch(BASE_URL_COMENTARIOS + 'public/api/comentarios_solicitud.php?accion=listar&solicitud_id=' + SOLICITUD_ID_COMENTARIOS)
        .then(r => r.json())
        .then(data => {
            const lista = document.getElementById('retroLista');
            if (!data.ok) return;
            if (!data.comentarios.length) {
                lista.innerHTML = '<p class="text-muted fs-sm" style="padding:15px; text-align:center;">No hay mensajes de seguimiento aún.</p>';
                return;
            }
            lista.innerHTML = data.comentarios.map(c => {
                const esAdmin = c.remitente_tipo === 'admin';
                const alineacion = esAdmin ? 'flex-end' : 'flex-start';
                const bg = esAdmin ? 'linear-gradient(135deg,#662331,#8b2f42)' : '#f3f4f6';
                const color = esAdmin ? '#fff' : '#111827';
                const radius = esAdmin ? '14px 14px 2px 14px' : '14px 14px 14px 2px';

                let adjuntoHtml = '';
                if (c.archivo_url) {
                    const ext = c.archivo_nombre.split('.').pop().toLowerCase();
                    const isImg = ['jpg','jpeg','png'].includes(ext);
                    adjuntoHtml = `
                        <div style="margin-top:8px; border-top:1px solid ${esAdmin ? 'rgba(255,255,255,0.2)' : '#e5e7eb'}; padding-top:8px;">
                            <a href="${c.archivo_url}" target="_blank" style="color:${esAdmin ? '#fff' : 'var(--color-primary)'}; text-decoration:none; font-size:0.75rem; display:flex; align-items:center; gap:6px;">
                                <i class="fa-solid ${isImg ? 'fa-image' : 'fa-file-pdf'}"></i>
                                ${c.archivo_nombre.substring(0, 20)}...
                            </a>
                        </div>
                    `;
                }

                return `
                <div style="display:flex; flex-direction:column; align-items:${alineacion}; margin-bottom:12px; max-width:100%;">
                    <div style="background:${bg}; color:${color}; border-radius:${radius}; padding:10px 14px; max-width:85%; box-shadow:var(--shadow-sm);">
                        <div style="font-size:0.65rem; opacity:0.8; margin-bottom:4px; font-weight:700; display:flex; justify-content:space-between; gap:10px;">
                            <span>${c.nombre_remitente}</span>
                            <span>${c.fecha_fmt}</span>
                        </div>
                        <p style="margin:0; font-size:0.85rem; line-height:1.4; white-space:pre-wrap;">${escapeHtml(c.mensaje)}</p>
                        ${adjuntoHtml}
                    </div>
                </div>
                `;
            }).join('');
            lista.scrollTop = lista.scrollHeight;
        });
}

document.getElementById('formRetro').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnEnviarRetro');
    const msg = document.getElementById('retroMensaje').value.trim();
    const fileInput = document.getElementById('retroArchivo');
    
    if (!msg && !fileInput.files.length) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const fd = new FormData();
    fd.append('accion', 'enviar');
    fd.append('solicitud_id', SOLICITUD_ID_COMENTARIOS);
    fd.append('mensaje', msg);
    if (fileInput.files[0]) fd.append('archivo', fileInput.files[0]);

    fetch(BASE_URL_COMENTARIOS + 'public/api/comentarios_solicitud.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                document.getElementById('retroMensaje').value = '';
                fileInput.value = '';
                cargarRetroalimentacion();
            } else {
                COMECyTUI.alert(d.error, 'Error');
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
        });
});

// Cargar datos iniciales
cargarComentarios();
cargarEvidencias();
cargarRetroalimentacion();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
