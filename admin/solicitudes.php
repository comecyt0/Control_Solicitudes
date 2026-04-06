<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Listado de Solicitudes
 *
 * Muestra todas las solicitudes con filtros por tipo, estatus y prioridad,
 * busqueda de texto, paginacion, y modal para cambio de estatus.
 * El cambio de estatus se procesa via POST en esta misma pagina (PRG pattern).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// -------------------------------------------------------
// Procesar cambio de estatus (POST -> Redirect -> GET)
// -------------------------------------------------------
$mensajeFlash = '';
$tipoFlash    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion']) && $_POST['_accion'] === 'cambiar_estatus') {
    validarCsrfPost();
    $idSol          = (int) ($_POST['solicitud_id'] ?? 0);
    $estatusNuevo   = postParam('estatus_nuevo');
    $comentario     = postParam('comentario');

    $estatusValidos = ['pendiente', 'en_proceso', 'completada', 'cancelada'];

    if ($idSol > 0 && in_array($estatusNuevo, $estatusValidos, true)) {
        // Obtener estatus actual
        $stmt = $pdo->prepare("SELECT estatus FROM solicitudes WHERE id = ?");
        $stmt->execute([$idSol]);
        $actual = $stmt->fetchColumn();

        if ($actual !== false) {
            $nombreAdmin = getNombreAdmin();

            if ($estatusNuevo === 'completada' && $actual !== 'completada') {
                $upd = $pdo->prepare("UPDATE solicitudes SET estatus = ?, resuelto_por = ? WHERE id = ?");
                $upd->execute([$estatusNuevo, $nombreAdmin, $idSol]);
            } else {
                $upd = $pdo->prepare("UPDATE solicitudes SET estatus = ? WHERE id = ?");
                $upd->execute([$estatusNuevo, $idSol]);
            }

            // Registrar en historial con nombre del administrador
            $ins = $pdo->prepare(
                "INSERT INTO historial_solicitudes (solicitud_id, estatus_anterior, estatus_nuevo, comentario, usuario_nombre)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ins->execute([$idSol, $actual, $estatusNuevo, $comentario ?: null, $nombreAdmin]);

            // Redirigir (PRG) con mensaje de exito
            $qs = http_build_query(['flash' => 'ok', 'flash_id' => $idSol]);
            header('Location: ' . BASE_URL . 'admin/solicitudes.php?' . $qs);
            exit;
        }
    }

    $mensajeFlash = 'No se pudo actualizar la solicitud. Verifique los datos.';
    $tipoFlash    = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion']) && $_POST['_accion'] === 'eliminar_solicitud') {
    $idSol = (int) ($_POST['solicitud_id'] ?? 0);
    if ($idSol > 0) {
        $delH = $pdo->prepare("DELETE FROM historial_solicitudes WHERE solicitud_id = ?");
        $delH->execute([$idSol]);

        $delS = $pdo->prepare("DELETE FROM solicitudes WHERE id = ?");
        $delS->execute([$idSol]);

        $qs = http_build_query(['flash' => 'deleted']);
        header('Location: ' . BASE_URL . 'admin/solicitudes.php?' . $qs);
        exit;
    }
    $mensajeFlash = 'No se pudo eliminar la solicitud.';
    $tipoFlash    = 'error';
}

// Leer flash de redirect
if (getParam('flash') === 'ok') {
    $mensajeFlash = 'Estatus actualizado correctamente.';
    $tipoFlash    = 'success';
} elseif (getParam('flash') === 'deleted') {
    $mensajeFlash = 'Solicitud y su historial eliminados permanentemente.';
    $tipoFlash    = 'success';
}

// -------------------------------------------------------
// Filtros de busqueda
// -------------------------------------------------------
$busqueda   = getParam('busqueda');
$filtroTipo = getParam('tipo');
$filtroEst  = getParam('estatus');
$filtroPrio = getParam('prioridad');
$page       = max(1, (int) getParam('page', '1'));
$limite     = 15;
$offset     = ($page - 1) * $limite;

// -------------------------------------------------------
// Construccion dinamica de la consulta
// -------------------------------------------------------
$where  = [];
$params = [];

if ($busqueda !== '') {
    $where[]  = "(folio LIKE ? OR solicitante LIKE ? OR area LIKE ? OR descripcion LIKE ? OR email_solicitante LIKE ?)";
    $like     = '%' . $busqueda . '%';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($filtroTipo !== '') {
    $where[]  = "tipo = ?";
    $params[] = $filtroTipo;
}
if ($filtroEst !== '') {
    $where[]  = "estatus = ?";
    $params[] = $filtroEst;
}
if ($filtroPrio !== '') {
    $where[]  = "prioridad = ?";
    $params[] = $filtroPrio;
}

$condicion = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Conteo total para paginacion
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM solicitudes {$condicion}");
$stmtTotal->execute($params);
$total      = (int) $stmtTotal->fetchColumn();
$totalPages = (int) ceil($total / $limite);

// Solicitudes de la pagina actual
$sql  = "SELECT *, (SELECT foto_perfil FROM cat_personal WHERE correo_institucional = solicitudes.email_solicitante OR correo_personal = solicitudes.email_solicitante LIMIT 1) as foto_perfil FROM solicitudes {$condicion} ORDER BY fecha_creacion DESC LIMIT {$limite} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

// URL base para paginacion (preservar filtros)
function urlPagina(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    return '?' . http_build_query($q);
}

// -------------------------------------------------------
// Transiciones de estatus validas
// -------------------------------------------------------
$transiciones = [
    'pendiente'  => ['en_proceso', 'cancelada'],
    'en_proceso' => ['completada', 'cancelada'],
    'completada' => [],
    'cancelada'  => [],
];

// -------------------------------------------------------
// Vista
// -------------------------------------------------------
$pageTitle  = 'Solicitudes';
$activeMenu = $filtroEst === 'pendiente' ? 'pendientes'
            : ($filtroEst === 'en_proceso' ? 'en_proceso'
            : ($filtroEst === 'completada' ? 'completadas' : 'solicitudes'));
$helpPage   = 'solicitudes';

require_once __DIR__ . '/../includes/header_admin.php';
?>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= esc($tipoFlash) ?>" data-auto-close="4000">
    <i class="fa-solid <?= $tipoFlash === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<!-- Filtros -->
<form method="GET" action="" class="filter-bar mb-16">
    <div class="search-input-wrapper">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input
            type="text"
            name="busqueda"
            class="form-control"
            placeholder="Buscar por folio, solicitante, area..."
            value="<?= esc($busqueda) ?>">
    </div>

    <select name="tipo" class="form-control" style="max-width: 160px;">
        <option value="">Todos los tipos</option>
        <?php foreach (ETIQUETAS_TIPO as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $filtroTipo === $val ? 'selected' : '' ?>>
            <?= esc($lbl) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="estatus" class="form-control" style="max-width: 160px;">
        <option value="">Todos los estatus</option>
        <?php foreach (ETIQUETAS_ESTATUS as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $filtroEst === $val ? 'selected' : '' ?>>
            <?= esc($lbl) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="prioridad" class="form-control" style="max-width: 140px;">
        <option value="">Toda prioridad</option>
        <?php foreach (ETIQUETAS_PRIORIDAD as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $filtroPrio === $val ? 'selected' : '' ?>>
            <?= esc($lbl) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-filter"></i> Filtrar
    </button>
    <?php if ($busqueda || $filtroTipo || $filtroEst || $filtroPrio): ?>
    <a href="<?= BASE_URL ?>admin/solicitudes.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark"></i> Limpiar
    </a>
    <?php endif; ?>
</form>

<!-- Tabla -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h2 class="card-title">
            <i class="fa-solid fa-list-ul"></i>
            Solicitudes
            <span class="text-muted fs-sm fw-600" style="margin-left: 6px;"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
        </h2>
        
        <a href="<?= BASE_URL ?>admin/export_csv.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-file-csv"></i> 
            Exportar CSV
        </a>
    </div>

    <?php if (!empty($solicitudes)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Tipo</th>
                    <th>Solicitante</th>
                    <th>Area</th>
                    <th>Prioridad</th>
                    <th>Estatus</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $sol): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>admin/detalle.php?id=<?= $sol['id'] ?>"
                           class="folio-link">
                            <?= esc($sol['folio']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="badge <?= getBadgeClase('tipo', $sol['tipo']) ?>">
                            <i class="<?= getIconoTipo($sol['tipo']) ?>"></i>
                            <?= esc(getEtiqueta('tipo', $sol['tipo'])) ?>
                        </span>
                        <?php if ($sol['tipo'] === 'sistemas' && !empty($sol['sistema_especifico'])): ?>
                            <div style="margin-top: 4px; font-size: 0.70rem; color: #4f46e5; border-top: 1px dotted #c7d2fe; padding-top: 4px; font-weight: 600; max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= esc($sol['sistema_especifico']) ?>">
                                <i class="fa-solid fa-layer-group"></i> <?= esc($sol['sistema_especifico']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <?php
                            $defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjOTRhM2I4IiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHBhdGggZD0iTTIwIDIxdi0yYTRgNCAwIDAgMC00LTRIOTRhNCA0IDAgMCAwLTQgNHYyIi8+PGNpcmNsZSBjeD0iMTIiIGN5PSI3IiByPSI0Ii8+PC9zdmc+';
                            $fotoUrl = !empty($sol['foto_perfil']) ? BASE_URL . 'public/uploads/avatares/' . esc($sol['foto_perfil']) : $defaultAvatar;
                            ?>
                            <img src="<?= $fotoUrl ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" onerror="this.onerror=null; this.src='<?= $defaultAvatar ?>'" alt="Avatar">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px; display:inline-block;" title="<?= esc($sol['solicitante']) ?>"><?= esc($sol['solicitante']) ?></span>
                        </div>
                    </td>
                    <td class="text-muted fs-sm"><?= esc($sol['area']) ?></td>
                    <td>
                        <span class="badge <?= getBadgeClase('prioridad', $sol['prioridad']) ?>">
                            <?= esc(getEtiqueta('prioridad', $sol['prioridad'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= getBadgeClase('estatus', $sol['estatus']) ?>">
                            <i class="<?= getIconoEstatus($sol['estatus']) ?>"></i>
                            <?= esc(getEtiqueta('estatus', $sol['estatus'])) ?>
                        </span>
                        <?php if ($sol['estatus'] === 'completada' && !empty($sol['resuelto_por'])): ?>
                            <div style="margin-top: 6px; font-size: 0.70rem; color: var(--color-completada); font-weight: 600;">
                                <i class="fa-solid fa-user-check"></i> <?= esc($sol['resuelto_por']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted fs-sm"><?= formatearFecha($sol['fecha_creacion']) ?></td>
                    <td class="td-actions">
                        <a href="<?= BASE_URL ?>admin/detalle.php?id=<?= $sol['id'] ?>"
                           class="btn btn-outline btn-icon" title="Ver detalle">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <?php if (!empty($transiciones[$sol['estatus']])): ?>
                        <button type="button"
                                class="btn btn-primary btn-icon"
                                title="Cambiar estatus"
                                onclick="abrirModalEstatus(
                                    <?= $sol['id'] ?>,
                                    '<?= esc($sol['folio']) ?>',
                                    '<?= $sol['estatus'] ?>'
                                )">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <?php endif; ?>

                        <!-- Boton de eliminar -->
                        <form method="POST" action=""
                              style="display:inline-block; margin:0;">
                            <input type="hidden" name="_accion" value="eliminar_solicitud">
                            <input type="hidden" name="solicitud_id" value="<?= $sol['id'] ?>">
                            <button type="button" class="btn btn-outline btn-icon"
                                    title="Eliminar solicitud"
                                    style="color: #F87171; border-color: transparent;"
                                    onclick="confirmarAccionDoble(this, '¿Quieres borrar esta solicitud?', '¿Estás SEGURO? Esta acción eliminará permanentemente la solicitud y todo su historial. No se puede deshacer.')">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginacion -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span>Pagina <?= $page ?> de <?= $totalPages ?> (<?= $total ?> registros)</span>
        <div class="pagination-buttons">
            <?php if ($page > 1): ?>
            <a href="<?= urlPagina($page - 1) ?>" class="pagination-btn">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="<?= urlPagina($p) ?>"
               class="pagination-btn <?= $p === $page ? 'active' : '' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="<?= urlPagina($page + 1) ?>" class="pagination-btn">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-inbox"></i>
        <h3>Sin resultados</h3>
        <p>Ninguna solicitud coincide con los filtros seleccionados.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Cambiar Estatus -->
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
            <input type="hidden" name="_accion"      value="cambiar_estatus">
            <input type="hidden" name="solicitud_id" id="modalSolicitudId">
            <div class="modal-body">
                <p class="text-muted fs-sm mb-16">
                    Solicitud: <strong id="modalFolioLabel" class="text-accent"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label" for="modalEstatusNuevo">
                        Nuevo estatus <span class="required">*</span>
                    </label>
                    <select name="estatus_nuevo" id="modalEstatusNuevo" class="form-control" required>
                        <option value="">Seleccionar...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="modalComentario">Comentario</label>
                    <textarea
                        name="comentario"
                        id="modalComentario"
                        class="form-control"
                        rows="3"
                        placeholder="Descripcion del cambio o accion realizada (opcional)"></textarea>
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

<script>
/**
 * Abrir el modal de cambio de estatus preconfigurado para una solicitud.
 * Las transiciones validas se deciden en PHP y se embeben como JSON.
 */
const TRANSICIONES = <?= json_encode($transiciones) ?>;
const ETIQUETAS_ESTATUS = <?= json_encode(array_map(fn($k,$v)=>['value'=>$k,'label'=>$v], array_keys(ETIQUETAS_ESTATUS), ETIQUETAS_ESTATUS)) ?>;

function abrirModalEstatus(id, folio, estatusActual) {
    document.getElementById('modalSolicitudId').value = id;
    document.getElementById('modalFolioLabel').textContent = folio;

    const select = document.getElementById('modalEstatusNuevo');
    select.innerHTML = '<option value="">Seleccionar...</option>';

    const opciones = TRANSICIONES[estatusActual] || [];
    opciones.forEach(val => {
        const found = ETIQUETAS_ESTATUS.find(e => e.value === val);
        const label = found ? found.label : val;
        const opt   = new Option(label, val);
        select.appendChild(opt);
    });

    document.getElementById('modalComentario').value = '';
    abrirModal('modalEstatus');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
