<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Correos Oficiales
 *
 * Módulo para gestionar las cuentas de correo institucional
 * y su asignación a personal y áreas.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// -------------------------------------------------------
// Procesar acciones (PRG)
// -------------------------------------------------------
$mensajeFlash = '';
$tipoFlash    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];

    if ($accion === 'crear_correo') {
        $correo       = trim(postParam('correo'));
        $cve_area     = (int) postParam('cve_area');
        $cve_personal = (int) postParam('cve_personal');

        if (!empty($correo) && $cve_area > 0 && $cve_personal > 0) {
            $stmt = $pdo->prepare("INSERT INTO sb_correos_oficial (correo, cve_area, cve_personal) VALUES (?, ?, ?)");
            $stmt->execute([$correo, $cve_area, $cve_personal]);
            header('Location: ' . BASE_URL . 'admin/correos.php?flash=correo_creado');
            exit;
        } else {
            $mensajeFlash = 'Por favor, complete todos los campos obligatorios.';
            $tipoFlash = 'error';
        }
    } elseif ($accion === 'eliminar_correo') {
        $id = (int) postParam('cve_correos_oficial');
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM sb_correos_oficial WHERE cve_correos_oficial = ?");
            $stmt->execute([$id]);
            header('Location: ' . BASE_URL . 'admin/correos.php?flash=correo_eliminado');
            exit;
        }
    } elseif ($accion === 'editar_correo') {
        $id = (int) postParam('cve_correos_oficial');
        $correo = trim(postParam('correo'));
        $cve_area = (int) postParam('cve_area');
        $cve_personal = (int) postParam('cve_personal');

        if ($id > 0 && !empty($correo) && $cve_area > 0 && $cve_personal > 0) {
            $stmt = $pdo->prepare("UPDATE sb_correos_oficial SET correo = ?, cve_area = ?, cve_personal = ? WHERE cve_correos_oficial = ?");
            $stmt->execute([$correo, $cve_area, $cve_personal, $id]);
            header('Location: ' . BASE_URL . 'admin/correos.php?flash=correo_editado');
            exit;
        } else {
            $mensajeFlash = 'Todos los campos son obligatorios.';
            $tipoFlash = 'error';
        }
    }
}

// Leer flash redirect
$flashCode = getParam('flash');
if ($flashCode === 'correo_creado') {
    $mensajeFlash = "La cuenta de correo oficial fue registrada exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'correo_eliminado') {
    $mensajeFlash = "El correo oficial fue eliminado del sistema.";
    $tipoFlash = "success";
} elseif ($flashCode === 'correo_editado') {
    $mensajeFlash = "La cuenta de correo oficial fue actualizada exitosamente.";
    $tipoFlash = "success";
}

// Filtros de busqueda
$busqueda = getParam('busqueda');

$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = "(c.correo LIKE ? OR a.des_area LIKE ? OR p.nombre LIKE ? OR p.appat LIKE ? OR p.apmat LIKE ?)";
    $like = '%' . $busqueda . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$condicion = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener lista de correos
$sql = "SELECT c.cve_correos_oficial, c.correo, c.cve_area, c.cve_personal, a.des_area, p.nombre, p.appat, p.apmat 
        FROM sb_correos_oficial c
        LEFT JOIN cat_areas a ON c.cve_area = a.cve_area
        LEFT JOIN cat_personal p ON c.cve_personal = p.cve_personal
        {$condicion}
        ORDER BY c.correo ASC";
$stmtCorreos = $pdo->prepare($sql);
$stmtCorreos->execute($params);
$listaCorreos = $stmtCorreos->fetchAll();

// Obtener catalogos para selects
$areas = $pdo->query("SELECT cve_area, des_area FROM cat_areas WHERE cve_area > 0 ORDER BY des_area ASC")->fetchAll();
$personal = $pdo->query("SELECT cve_personal, nombre, appat, apmat FROM cat_personal ORDER BY nombre ASC, appat ASC")->fetchAll();

// -------------------------------------------------------
// Variables para la vista
// -------------------------------------------------------
$pageTitle  = 'Correos Oficiales';
$activeMenu = 'correos';
$helpPage   = 'correos';

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
            placeholder="Buscar por correo, personal o área..."
            value="<?= esc($busqueda) ?>">
    </div>

    <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
        <i class="fa-solid fa-filter"></i> Filtrar
    </button>
    <?php if ($busqueda !== ''): ?>
    <a href="?" class="btn btn-outline" style="white-space:nowrap;">
        <i class="fa-solid fa-xmark"></i> Limpiar
    </a>
    <?php endif; ?>
</form>

<!-- ======================================================= -->
<!-- SECCION: LISTADO DE CORREOS                             -->
<!-- ======================================================= -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h2 class="card-title">
            <i class="fa-solid fa-envelope-open-text"></i>
            Cuentas Asignadas
        </h2>
        <button class="btn btn-primary btn-sm" onclick="abrirModal('modalCrearCorreo')">
            <i class="fa-solid fa-plus"></i> Registrar Correo
        </button>
    </div>

    <?php if (!empty($listaCorreos)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Cuenta de Correo</th>
                    <th>Personal Asignado</th>
                    <th>Área de Adscripción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaCorreos as $c): 
                    $nombreCompleto = trim($c['nombre'] . ' ' . $c['appat'] . ' ' . $c['apmat']);
                ?>
                <tr>
                    <td class="fw-600" style="color: var(--primary);">
                        <i class="fa-regular fa-envelope" style="margin-right: 5px;"></i>
                        <?= esc($c['correo']) ?>
                    </td>
                    <td><?= esc($nombreCompleto) ?></td>
                    <td class="text-muted fs-sm"><?= esc($c['des_area'] ?? 'No definida') ?></td>
                    <td class="td-actions">
                        <!-- Editar -->
                        <button type="button" class="btn btn-outline btn-icon" title="Editar correo" 
                                onclick="abrirModalEditar(<?= htmlspecialchars(json_encode([
                                    'id' => $c['cve_correos_oficial'],
                                    'correo' => $c['correo'],
                                    'cve_personal' => $c['cve_personal'],
                                    'cve_area' => $c['cve_area']
                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="eliminar_correo">
                            <input type="hidden" name="cve_correos_oficial" value="<?= $c['cve_correos_oficial'] ?>">
                            <button type="button" class="btn btn-outline btn-icon" title="Eliminar correo" style="color: #F87171; border-color: transparent;"
                                    onclick="confirmarAccionDoble(this, '¿Quieres borrar este registro de correo?', '¿Estás SEGURO? Esta acción no se puede deshacer y eliminará la cuenta oficial del sistema.')">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-inbox empty-icon"></i>
        <p>No hay correos oficiales registrados en el sistema.</p>
    </div>
    <?php endif; ?>
</div>

<!-- ======================================================= -->
<!-- MODAL: CREAR CORREO                                     -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="modalCrearCorreo">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Nuevo Correo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalCrearCorreo')">&times;</button>
        </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="_accion" value="crear_correo">

                    <div class="form-group">
                        <label class="form-label" for="correo">Dirección de Correo <span class="required">*</span></label>
                        <input type="email" id="correo" name="correo" class="form-control" required placeholder="ejemplo@edomex.gob.mx">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="cve_personal">Personal Asignado <span class="required">*</span></label>
                        <select id="cve_personal" name="cve_personal" class="form-control" required>
                            <option value="">-- Seleccione un empleado --</option>
                            <?php foreach ($personal as $p): 
                                $nom = trim($p['nombre'] . ' ' . $p['appat'] . ' ' . $p['apmat']);
                            ?>
                                <option value="<?= $p['cve_personal'] ?>"><?= esc($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="cve_area">Área a la que pertenece <span class="required">*</span></label>
                        <select id="cve_area" name="cve_area" class="form-control" required>
                            <option value="">-- Seleccione el área --</option>
                            <?php foreach ($areas as $a): ?>
                                <option value="<?= $a['cve_area'] ?>"><?= esc($a['des_area']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearCorreo')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Guardar Correo</button>
                    </div>
                </form>
            </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: EDITAR CORREO                                    -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="modalEditarCorreo">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editar Correo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarCorreo')">&times;</button>
        </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="_accion" value="editar_correo">
                    <input type="hidden" name="cve_correos_oficial" id="edit_id" value="">

                    <div class="form-group">
                        <label class="form-label" for="edit_correo">Dirección de Correo <span class="required">*</span></label>
                        <input type="email" id="edit_correo" name="correo" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_cve_personal">Personal Asignado <span class="required">*</span></label>
                        <select id="edit_cve_personal" name="cve_personal" class="form-control" required>
                            <option value="">-- Seleccione un empleado --</option>
                            <?php foreach ($personal as $p): 
                                $nom = trim($p['nombre'] . ' ' . $p['appat'] . ' ' . $p['apmat']);
                            ?>
                                <option value="<?= $p['cve_personal'] ?>"><?= esc($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="edit_cve_area">Área a la que pertenece <span class="required">*</span></label>
                        <select id="edit_cve_area" name="cve_area" class="form-control" required>
                            <option value="">-- Seleccione el área --</option>
                            <?php foreach ($areas as $a): ?>
                                <option value="<?= $a['cve_area'] ?>"><?= esc($a['des_area']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarCorreo')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Guardar Cambios</button>
                    </div>
                </form>
            </div>
    </div>
</div>

<script>
function abrirModalEditar(data) {
    document.getElementById('edit_id').value = data.id || '';
    document.getElementById('edit_correo').value = data.correo || '';
    document.getElementById('edit_cve_personal').value = data.cve_personal || '';
    document.getElementById('edit_cve_area').value = data.cve_area || '';
    
    abrirModal('modalEditarCorreo');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
