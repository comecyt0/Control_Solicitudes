<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Gestion de Usuarios
 *
 * Permite listar y activar/desactivar el acceso de los usuarios 
 * solicitantes registrados en el sistema.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// -------------------------------------------------------
// Procesar acciones de usuarios (PRG)
// -------------------------------------------------------
$mensajeFlash = '';
$tipoFlash    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];

    if ($accion === 'toggle_usuario') {
        $idUsuario = (int) postParam('usuario_id');
        $nuevoActivo = (int) postParam('activo');
        
        if ($idUsuario > 0) {
            $stmtT = $pdo->prepare("UPDATE cat_personal SET activo = ? WHERE cve_personal = ?");
            $stmtT->execute([$nuevoActivo, $idUsuario]);
            header('Location: ' . BASE_URL . 'admin/personal.php?flash=usuario_toggle');
            exit;
        }
    } elseif ($accion === 'editar_personal') {
        $idUsuario = (int) postParam('usuario_id');
        $nombre = mb_strtoupper(trim(postParam('nombre')));
        $appat = mb_strtoupper(trim(postParam('appat')));
        $apmat = mb_strtoupper(trim(postParam('apmat')));
        $correo_institucional = trim(postParam('correo_institucional'));
        $correo_personal = trim(postParam('correo_personal'));
        $ext_telefonica = trim(postParam('ext_telefonica'));
        $password = trim(postParam('password'));

        if ($idUsuario > 0 && !empty($nombre) && !empty($appat)) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtU = $pdo->prepare(
                    "UPDATE cat_personal 
                     SET nombre = ?, appat = ?, apmat = ?, correo_institucional = ?, correo_personal = ?, ext_telefonica = ?, password_hash = ?
                     WHERE cve_personal = ?"
                );
                $stmtU->execute([$nombre, $appat, $apmat, $correo_institucional, $correo_personal, $ext_telefonica, $hash, $idUsuario]);
            } else {
                $stmtU = $pdo->prepare(
                    "UPDATE cat_personal 
                     SET nombre = ?, appat = ?, apmat = ?, correo_institucional = ?, correo_personal = ?, ext_telefonica = ?
                     WHERE cve_personal = ?"
                );
                $stmtU->execute([$nombre, $appat, $apmat, $correo_institucional, $correo_personal, $ext_telefonica, $idUsuario]);
            }
            // Eliminar solicitud de actualizacion nativa pendiente si es que habia
            $stmtDelPend = $pdo->prepare("DELETE FROM solicitudes_actualizacion_personal WHERE cve_personal = ?");
            $stmtDelPend->execute([$idUsuario]);

            header('Location: ' . BASE_URL . 'admin/personal.php?flash=usuario_editado');
            exit;
        } else {
            $mensajeFlash = 'Nombre y Apellido Paterno son obligatorios.';
            $tipoFlash = 'error';
        }
    } elseif ($accion === 'eliminar_personal') {
        $idUsuario = (int) postParam('usuario_id');
        if ($idUsuario > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM cat_personal WHERE cve_personal = ?");
                $stmt->execute([$idUsuario]);
                header('Location: ' . BASE_URL . 'admin/personal.php?flash=usuario_eliminado');
                exit;
            } catch (PDOException $e) {
                // If it fails (e.g., due to foreign keys)
                header('Location: ' . BASE_URL . 'admin/personal.php?flash=error_eliminar');
                exit;
            }
        }
    } elseif ($accion === 'aceptar_actualizacion') {
        $idReq = (int) postParam('req_id');
        
        $stmtS = $pdo->prepare("SELECT * FROM solicitudes_actualizacion_personal WHERE id = ?");
        $stmtS->execute([$idReq]);
        $req = $stmtS->fetch();

        if ($req) {
            $stmtUpd = $pdo->prepare(
                "UPDATE cat_personal 
                 SET nombre = ?, appat = ?, apmat = ?, ext_telefonica = ?
                 WHERE cve_personal = ?"
            );
            // El correo institucional no se sobrescribe por este medio para no romper login
            $stmtUpd->execute([$req['nombre'], $req['appat'], $req['apmat'], $req['ext_telefonica'], $req['cve_personal']]);
            
            $stmtDel = $pdo->prepare("DELETE FROM solicitudes_actualizacion_personal WHERE id = ?");
            $stmtDel->execute([$idReq]);
            
            header('Location: ' . BASE_URL . 'admin/personal.php?flash=actualizacion_aceptada');
            exit;
        }
    } elseif ($accion === 'rechazar_actualizacion') {
        $idReq = (int) postParam('req_id');
        $stmtDel = $pdo->prepare("DELETE FROM solicitudes_actualizacion_personal WHERE id = ?");
        $stmtDel->execute([$idReq]);
        header('Location: ' . BASE_URL . 'admin/personal.php?flash=actualizacion_rechazada');
        exit;
    }
}

// Leer flash redirect
$flashCode = getParam('flash');
if ($flashCode === 'usuario_toggle') {
    $mensajeFlash = "Estatus de acceso del personal actualizado correctamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'usuario_editado') {
    $mensajeFlash = "Los datos del personal fueron actualizados correctamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'usuario_eliminado') {
    $mensajeFlash = "El registro del empleado fue eliminado complemente del sistema.";
    $tipoFlash = "success";
} elseif ($flashCode === 'error_eliminar') {
    $mensajeFlash = "No se puede eliminar el empleado porque tiene registros asociados (por ejemplo, correos oficiales o equipos asignados).";
    $tipoFlash = "error";
} elseif ($flashCode === 'actualizacion_aceptada') {
    $mensajeFlash = "Los cambios propuestos por el usuario fueron aceptados e instalados en su perfil exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'actualizacion_rechazada') {
    $mensajeFlash = "La solicitud de actualización de perfil fue rechazada y eliminada.";
    $tipoFlash = "success";
}

// Obtener lista de actualizaciones pendientes
$stmtUpdates = $pdo->query(
    "SELECT s.*, p.correo_institucional 
     FROM solicitudes_actualizacion_personal s 
     INNER JOIN cat_personal p ON s.cve_personal = p.cve_personal 
     WHERE s.estatus = 'pendiente' 
     ORDER BY s.fecha_solicitud DESC"
);
$listaUpdates = $stmtUpdates->fetchAll();

// Filtros de busqueda
$busqueda = getParam('busqueda');
$filtroEstatus = getParam('estatus');

$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = "(u.nombre LIKE ? OR u.appat LIKE ? OR u.apmat LIKE ? OR u.correo_institucional LIKE ? OR u.correo_personal LIKE ? OR a.des_area LIKE ? OR u.ext_telefonica LIKE ?)";
    $like = '%' . $busqueda . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

if ($filtroEstatus === '1' || $filtroEstatus === '0') {
    $where[] = "u.activo = ?";
    $params[] = $filtroEstatus;
}

$condicion = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener lista de personal con su area aplicando filtros
$sql = "SELECT u.cve_personal as id, u.nombre, u.appat, u.apmat, u.correo_institucional, u.correo_personal, u.ext_telefonica, COALESCE(u.correo_institucional, u.correo_personal, 'Sin correo') as email, u.activo, a.des_area 
        FROM cat_personal u
        LEFT JOIN cat_areas a ON u.cve_area = a.cve_area
        {$condicion}
        ORDER BY u.nombre ASC";
$stmtUsuarios = $pdo->prepare($sql);
$stmtUsuarios->execute($params);
$listaUsuarios = $stmtUsuarios->fetchAll();

// -------------------------------------------------------
// Variables para la vista
// -------------------------------------------------------
$pageTitle  = 'Gestión de Personal';
$activeMenu = 'personal';
$helpPage   = 'personal';

require_once __DIR__ . '/../includes/header_admin.php';
?>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= esc($tipoFlash) ?>" data-auto-close="4000">
    <i class="fa-solid <?= $tipoFlash === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- SECCION: SOLICITUDES DE ACTUALIZACION DE PERFIL         -->
<!-- ======================================================= -->
<?php if (!empty($listaUpdates)): ?>
<div class="card" style="border: 2px solid #F59E0B; background-color: #FEF3C7; margin-bottom: 2rem;">
    <div class="card-header" style="border-bottom: 1px solid rgba(245, 158, 11, 0.3);">
        <h2 class="card-title" style="color: #B45309;">
            <i class="fa-solid fa-user-clock"></i> Solicitudes de Actualización de Datos Pendientes
        </h2>
    </div>
    <div class="table-wrapper">
        <table style="background: transparent;">
            <thead>
                <tr>
                    <th>Empleado Identificado</th>
                    <th>Nuevos Datos Propuestos</th>
                    <th>Fecha Solicitud</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaUpdates as $upd): 
                    $nombresProps = trim($upd['nombre'] . ' ' . $upd['appat'] . ' ' . $upd['apmat']);
                ?>
                <tr>
                    <td>
                        <div class="fw-600" style="color: #92400E;"><?= esc($upd['correo_institucional']) ?></div>
                    </td>
                    <td>
                        <div><i class="fa-solid fa-user fa-fw text-muted"></i> <?= esc($nombresProps) ?></div>
                        <div><i class="fa-solid fa-phone fa-fw text-muted"></i> Ext: <?= esc($upd['ext_telefonica'] ?: 'S/N') ?></div>
                    </td>
                    <td class="text-muted fs-sm">
                        <?= date('d/m/Y H:i', strtotime($upd['fecha_solicitud'])) ?>
                    </td>
                    <td class="td-actions">
                        <!-- Aceptar -->
                        <form method="POST" action="" style="display:inline-block; margin:0; margin-right: 5px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="aceptar_actualizacion">
                            <input type="hidden" name="req_id" value="<?= $upd['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Aprobar Actualización" data-confirm="¿Aprobar y sobrescribir el perfil del usuario con estos nuevos datos?">
                                <i class="fa-solid fa-check"></i> Aprobar
                            </button>
                        </form>
                        
                        <!-- Editar / Ajustar antes de aprobar -->
                        <button type="button" class="btn btn-outline btn-icon" title="Ajustar propuesta (Editar Perfil)" 
                                onclick="abrirModalEditar(<?= htmlspecialchars(json_encode([
                                    'id' => $upd['cve_personal'],
                                    'nombre' => $upd['nombre'],
                                    'appat' => $upd['appat'],
                                    'apmat' => $upd['apmat'],
                                    'correo_institucional' => $upd['correo_institucional'],
                                    'correo_personal' => $upd['correo_personal'],
                                    'ext_telefonica' => $upd['ext_telefonica']
                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>

                        <!-- Rechazar -->
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="rechazar_actualizacion">
                            <input type="hidden" name="req_id" value="<?= $upd['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-icon" title="Rechazar y Eliminar Consulta" style="color: #DC2626; border-color: transparent;" data-confirm="¿Rechazar esta solicitud de actualización referenciada? Los datos no se alterarán.">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- SECCION: GESTION DE PERSONAL                            -->
<!-- ======================================================= -->

<!-- Filtros -->
<form method="GET" action="" class="filter-bar mb-16">
    <div class="search-input-wrapper">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input
            type="text"
            name="busqueda"
            class="form-control"
            placeholder="Buscar por nombre, correo, área o extensión..."
            value="<?= esc($busqueda) ?>">
    </div>

    <select name="estatus" class="form-control" style="max-width: 160px;">
        <option value="">Todos los estatus</option>
        <option value="1" <?= $filtroEstatus === '1' ? 'selected' : '' ?>>Activo</option>
        <option value="0" <?= $filtroEstatus === '0' ? 'selected' : '' ?>>Inactivo</option>
    </select>

    <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
        <i class="fa-solid fa-filter"></i> Filtrar
    </button>
    <?php if ($busqueda !== '' || $filtroEstatus !== ''): ?>
    <a href="?" class="btn btn-outline" style="white-space:nowrap;">
        <i class="fa-solid fa-xmark"></i> Limpiar
    </a>
    <?php endif; ?>
</form>
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h2 class="card-title">
            <i class="fa-solid fa-users"></i>
            Personal Registrado
        </h2>
    </div>

    <?php if (!empty($listaUsuarios)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Email</th>
                    <th>Área</th>
                    <th>Estatus Login</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaUsuarios as $usr): 
                    $nombreCompleto = trim($usr['nombre'] . ' ' . $usr['appat'] . ' ' . $usr['apmat']);
                ?>
                <tr>
                    <td class="fw-600"><?= esc($nombreCompleto) ?></td>
                    <td><?= esc($usr['email']) ?></td>
                    <td class="text-muted fs-sm"><?= esc($usr['des_area'] ?? 'General') ?></td>
                    <td>
                        <?php if ($usr['activo']): ?>
                            <span class="badge" style="background: rgba(22, 163, 74, 0.1); color: #16A34A; border: 1px solid rgba(22, 163, 74, 0.2);">
                                Activo
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(248, 113, 113, 0.1); color: #DC2626; border: 1px solid rgba(248, 113, 113, 0.2);">
                                Inactivo
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="td-actions">
                        <!-- Editar -->
                        <button type="button" class="btn btn-outline btn-icon" title="Editar personal" 
                                onclick="abrirModalEditar(<?= htmlspecialchars(json_encode([
                                    'id' => $usr['id'],
                                    'nombre' => $usr['nombre'],
                                    'appat' => $usr['appat'],
                                    'apmat' => $usr['apmat'],
                                    'correo_institucional' => $usr['correo_institucional'],
                                    'correo_personal' => $usr['correo_personal'],
                                    'ext_telefonica' => $usr['ext_telefonica']
                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        
                        <!-- Toggle Activo/Inactivo -->
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="toggle_usuario">
                            <input type="hidden" name="usuario_id" value="<?= $usr['id'] ?>">
                            <input type="hidden" name="activo" value="<?= $usr['activo'] ? '0' : '1' ?>">
                            
                            <?php if ($usr['activo']): ?>
                                <button type="submit" class="btn btn-outline btn-icon" title="Desactivar acceso" style="color: #F87171; border-color: transparent;" data-confirm="¿Seguro que deseas desactivar a este usuario?">
                                    <i class="fa-solid fa-user-slash"></i>
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-outline btn-icon" title="Aprobar acceso" style="color: #4ADE80; border-color: transparent;" data-confirm="¿Seguro que deseas activar a este usuario?">
                                    <i class="fa-solid fa-user-check"></i>
                                </button>
                            <?php endif; ?>
                        </form>

                        <!-- Eliminar Físicamente -->
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="eliminar_personal">
                            <input type="hidden" name="usuario_id" value="<?= $usr['id'] ?>">
                            <button type="button" class="btn btn-outline btn-icon" title="Eliminar personal permanentemente" style="color: #DC2626; border-color: transparent;"
                                    onclick="confirmarAccionDoble(this, '¿Quieres borrar este registro de personal?', '¿Estás SEGURO? Esta acción borrará al usuario permanentemente.')">
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
        <p>No hay usuarios registrados en el sistema.</p>
    </div>
    <?php endif; ?>
</div>

<!-- ======================================================= -->
<!-- MODAL: EDITAR PERSONAL                                  -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="modalEditarPersonal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editar Personal</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarPersonal')">&times;</button>
        </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="_accion" value="editar_personal">
                    <input type="hidden" name="usuario_id" id="edit_usuario_id" value="">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="edit_nombre">Nombre <span class="required">*</span></label>
                            <input type="text" id="edit_nombre" name="nombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_appat">Apellido Paterno <span class="required">*</span></label>
                            <input type="text" id="edit_appat" name="appat" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_apmat">Apellido Materno</label>
                        <input type="text" id="edit_apmat" name="apmat" class="form-control">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="edit_correo_inst">Correo Institucional</label>
                            <input type="email" id="edit_correo_inst" name="correo_institucional" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_correo_per">Correo Personal</label>
                            <input type="email" id="edit_correo_per" name="correo_personal" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_ext_telefonica">Extensión Telefónica</label>
                        <input type="text" id="edit_ext_telefonica" name="ext_telefonica" class="form-control" placeholder="Ej. 123">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="edit_password">Nueva Contraseña (Opcional)</label>
                        <input type="password" id="edit_password" name="password" class="form-control" placeholder="Dejar en blanco para mantener la actual">
                    </div>

                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarPersonal')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEditar(data) {
    document.getElementById('edit_usuario_id').value = data.id || '';
    document.getElementById('edit_nombre').value = data.nombre || '';
    document.getElementById('edit_appat').value = data.appat || '';
    document.getElementById('edit_apmat').value = data.apmat || '';
    document.getElementById('edit_correo_inst').value = data.correo_institucional || '';
    document.getElementById('edit_correo_per').value = data.correo_personal || '';
    document.getElementById('edit_ext_telefonica').value = data.ext_telefonica || '';
    document.getElementById('edit_password').value = '';
    
    abrirModal('modalEditarPersonal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
