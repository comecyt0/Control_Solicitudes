<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Control de Equipos
 *
 * Módulo para gestionar el inventario de bienes informáticos
 * y su asignación a los resguardatarios.
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

    if ($accion === 'crear_equipo') {
        $marca          = trim(postParam('marca'));
        $modelo         = trim(postParam('modelo'));
        $num_serie      = trim(postParam('num_serie'));
        $num_inventario = trim(postParam('num_inventario'));
        $resguardatario = (int) postParam('resguardatario');
        $cve_area       = (int) postParam('cve_area');

        if (!empty($marca) && !empty($modelo) && $resguardatario > 0 && $cve_area > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO sb_bienes (marca, modelo, num_serie, num_inventario, resguardatario, cve_area, cve_estatus) 
                 VALUES (?, ?, ?, ?, ?, ?, 1)"
            );
            $stmt->execute([$marca, $modelo, $num_serie, $num_inventario, $resguardatario, $cve_area]);
            header('Location: ' . BASE_URL . 'admin/equipos.php?flash=equipo_creado');
            exit;
        } else {
            $mensajeFlash = 'Marca, modelo, resguardatario y área son obligatorios.';
            $tipoFlash = 'error';
        }
    } elseif ($accion === 'eliminar_equipo') {
        $id = (int) postParam('cve_bienes');
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM sb_bienes WHERE cve_bienes = ?");
            $stmt->execute([$id]);
            header('Location: ' . BASE_URL . 'admin/equipos.php?flash=equipo_eliminado');
            exit;
        }
    } elseif ($accion === 'editar_equipo') {
        $id = (int) postParam('cve_bienes');
        $marca = trim(postParam('marca'));
        $modelo = trim(postParam('modelo'));
        $num_serie = trim(postParam('num_serie'));
        $num_inventario = trim(postParam('num_inventario'));
        $resguardatario = (int) postParam('resguardatario');
        $cve_area = (int) postParam('cve_area');

        if ($id > 0 && !empty($marca) && !empty($modelo) && $resguardatario > 0 && $cve_area > 0) {
            $stmt = $pdo->prepare(
                "UPDATE sb_bienes 
                 SET marca = ?, modelo = ?, num_serie = ?, num_inventario = ?, resguardatario = ?, cve_area = ?
                 WHERE cve_bienes = ?"
            );
            $stmt->execute([$marca, $modelo, $num_serie, $num_inventario, $resguardatario, $cve_area, $id]);
            header('Location: ' . BASE_URL . 'admin/equipos.php?flash=equipo_editado');
            exit;
        } else {
            $mensajeFlash = 'Marca, modelo, resguardatario y área son obligatorios.';
            $tipoFlash = 'error';
        }
    } elseif ($accion === 'aprobar_equipo') {
        $id = (int) postParam('cve_bienes');
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE sb_bienes SET estatus_alta = 'aprobado' WHERE cve_bienes = ?");
            $stmt->execute([$id]);
            header('Location: ' . BASE_URL . 'admin/equipos.php?flash=equipo_aprobado');
            exit;
        }
    }
}

// Leer flash redirect
$flashCode = getParam('flash');
if ($flashCode === 'equipo_creado') {
    $mensajeFlash = "El equipo fue registrado en el inventario exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'equipo_eliminado') {
    $mensajeFlash = "El equipo fue eliminado del sistema.";
    $tipoFlash = "success";
} elseif ($flashCode === 'equipo_editado') {
    $mensajeFlash = "El equipo fue actualizado en el inventario exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'equipo_aprobado') {
    $mensajeFlash = "El equipo introducido por el solicitante fue verificado y aprobado oficialmente en el inventario.";
    $tipoFlash = "success";
}

// Filtros de busqueda
$busqueda = getParam('busqueda');

$whereAprobados = ["b.estatus_alta = 'aprobado'"];
$wherePendientes = ["b.estatus_alta = 'pendiente'"];
$params = [];

if ($busqueda !== '') {
    $busquedaCond = "(b.marca LIKE ? OR b.modelo LIKE ? OR b.num_serie LIKE ? OR b.num_inventario LIKE ? OR p.nombre LIKE ? OR p.appat LIKE ? OR p.apmat LIKE ? OR a.des_area LIKE ?)";
    $whereAprobados[] = $busquedaCond;
    $wherePendientes[] = $busquedaCond;
    
    $like = '%' . $busqueda . '%';
    $params = array_fill(0, 8, $like);
}

$condAprobados = implode(' AND ', $whereAprobados);
$condPendientes = implode(' AND ', $wherePendientes);

// Obtener lista de equipos aprobados
$sqlAprobados = "SELECT b.cve_bienes, b.marca, b.modelo, b.num_serie, b.num_inventario, b.cve_area, b.resguardatario, b.estatus_alta, a.des_area, p.nombre, p.appat, p.apmat, p.foto_perfil 
                 FROM sb_bienes b
                 LEFT JOIN cat_areas a ON b.cve_area = a.cve_area
                 LEFT JOIN cat_personal p ON b.resguardatario = p.cve_personal
                 WHERE {$condAprobados}
                 ORDER BY b.cve_bienes DESC";
$stmtAprobados = $pdo->prepare($sqlAprobados);
$stmtAprobados->execute($params);
$listaEquiposAprobados = $stmtAprobados->fetchAll();

// Obtener lista de equipos pendientes
$sqlPendientes = "SELECT b.cve_bienes, b.marca, b.modelo, b.num_serie, b.num_inventario, b.cve_area, b.resguardatario, b.estatus_alta, a.des_area, p.nombre, p.appat, p.apmat, p.foto_perfil 
                 FROM sb_bienes b
                 LEFT JOIN cat_areas a ON b.cve_area = a.cve_area
                 LEFT JOIN cat_personal p ON b.resguardatario = p.cve_personal
                 WHERE {$condPendientes}
                 ORDER BY b.cve_bienes DESC";
$stmtPendientes = $pdo->prepare($sqlPendientes);
$stmtPendientes->execute($params);
$listaEquiposPendientes = $stmtPendientes->fetchAll();

// Obtener catalogos para selects
$areas = $pdo->query("SELECT cve_area, des_area FROM cat_areas WHERE cve_area > 0 ORDER BY des_area ASC")->fetchAll();
$personal = $pdo->query("SELECT cve_personal, nombre, appat, apmat FROM cat_personal ORDER BY nombre ASC, appat ASC")->fetchAll();

// -------------------------------------------------------
// Variables para la vista
// -------------------------------------------------------
$pageTitle  = 'Control de Equipos';
$activeMenu = 'equipos';
$helpPage   = 'equipos';

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
            placeholder="Buscar por marca, modelo, serie, resguardatario o área..."
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
<!-- SECCION: EQUIPOS PENDIENTES DE APROBACION               -->
<!-- ======================================================= -->
<?php if (!empty($listaEquiposPendientes)): ?>
<div class="card" style="border: 2px solid #F59E0B; background-color: #FEF3C7; margin-bottom: 2rem;">
    <div class="card-header" style="border-bottom: 1px solid rgba(245, 158, 11, 0.3);">
        <h2 class="card-title" style="color: #B45309;">
            <i class="fa-solid fa-laptop-medical"></i> Solicitudes de Alta de Equipo Pendientes
        </h2>
    </div>
    <div class="table-wrapper">
        <table style="background: transparent;">
            <thead>
                <tr>
                    <th>Equipo Detectado</th>
                    <th>Solicitante / Área</th>
                    <th>Serie / Modelo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaEquiposPendientes as $ep): 
                    $nombreResguardo = trim($ep['nombre'] . ' ' . $ep['appat'] . ' ' . $ep['apmat']);
                    $equipoDesc = trim($ep['marca'] . ' ' . $ep['modelo']);
                ?>
                <tr>
                    <td>
                        <div class="fw-600" style="color: #92400E; font-size: 1.05rem;"><?= esc($equipoDesc) ?></div>
                        <div class="text-muted fs-sm" style="color: #B45309 !important;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Requiere Verificación de TI
                        </div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <?php
                            $defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjOTRhM2I4IiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHBhdGggZD0iTTIwIDIxdi0yYTRgNCAwIDAgMC00LTRIOTRhNCA0IDAgMCAwLTQgNHYyIi8+PGNpcmNsZSBjeD0iMTIiIGN5PSI3IiByPSI0Ii8+PC9zdmc+';
                            $fotoUrl = !empty($ep['foto_perfil']) ? BASE_URL . 'public/uploads/avatares/' . esc($ep['foto_perfil']) : $defaultAvatar;
                            ?>
                            <img src="<?= $fotoUrl ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink:0;" onerror="this.onerror=null; this.src='<?= $defaultAvatar ?>'" alt="Avatar">
                            <div>
                                <div style="color: #92400E; font-weight: 600;"><?= esc($nombreResguardo) ?></div>
                                <div class="fs-sm"><i class="fa-solid fa-building fa-fw text-muted" style="color: #B45309 !important;"></i> <?= esc($ep['des_area'] ?? 'Área no vinculada') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-muted fs-sm">
                        SN: <?= esc($ep['num_serie'] ?: 'Desconocido') ?>
                    </td>
                    <td class="td-actions">
                        <!-- Aprobar -->
                        <form method="POST" action="" style="display:inline-block; margin:0; margin-right: 5px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="aprobar_equipo">
                            <input type="hidden" name="cve_bienes" value="<?= $ep['cve_bienes'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Aprobar Alta de Equipo" data-confirm="¿Aprobar y certificar este equipo formalmente en el inventario institucional?">
                                <i class="fa-solid fa-check-double"></i> Aprobar Equipo
                            </button>
                        </form>
                        
                        <!-- Editar antes de aprobar -->
                        <button type="button" class="btn btn-outline btn-icon" title="Completar datos faltantes" 
                                onclick="abrirModalEditar(<?= htmlspecialchars(json_encode([
                                    'id' => $ep['cve_bienes'],
                                    'marca' => $ep['marca'],
                                    'modelo' => $ep['modelo'],
                                    'num_serie' => $ep['num_serie'],
                                    'num_inventario' => $ep['num_inventario'],
                                    'resguardatario' => $ep['resguardatario'],
                                    'cve_area' => $ep['cve_area']
                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>

                        <!-- Rechazar / Eliminar falso reporte -->
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="eliminar_equipo">
                            <input type="hidden" name="cve_bienes" value="<?= $ep['cve_bienes'] ?>">
                            <button type="button" class="btn btn-outline btn-icon" title="Descartar reporte fantasma" style="color: #DC2626; border-color: transparent;" onclick="confirmarAccionDoble(this, '¿Rechazar este reporte y eliminar el equipo prospecto del sistema?', '¿Estás SEGURO? Esta acción no se puede deshacer.')">
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
<!-- SECCION: LISTADO DE EQUIPOS ACTIVOS                     -->
<!-- ======================================================= -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h2 class="card-title">
            <i class="fa-solid fa-laptop-code"></i>
            Inventario de Bienes Informáticos
        </h2>
        <button class="btn btn-primary btn-sm" onclick="abrirModal('modalCrearEquipo')">
            <i class="fa-solid fa-plus"></i> Registrar Equipo
        </button>
    </div>

    <?php if (!empty($listaEquiposAprobados)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Num. Inventario</th>
                    <th>Equipo</th>
                    <th>Serie</th>
                    <th>Resguardatario</th>
                    <th>Área</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaEquiposAprobados as $e): 
                    $nombreCompleto = trim($e['nombre'] . ' ' . $e['appat'] . ' ' . $e['apmat']);
                    $equipoDesc = trim($e['marca'] . ' ' . $e['modelo']);
                ?>
                <tr>
                    <td class="fw-600">
                        <i class="fa-solid fa-barcode" style="margin-right: 5px; color: var(--text-muted);"></i>
                        <?= esc($e['num_inventario'] ?: 'S/N') ?>
                    </td>
                    <td>
                        <?= esc($equipoDesc) ?>
                    </td>
                    <td class="text-muted fs-sm"><?= esc($e['num_serie'] ?: 'N/A') ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <?php
                            $defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjOTRhM2I4IiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHBhdGggZD0iTTIwIDIxdi0yYTRgNCAwIDAgMC00LTRIOTRhNCA0IDAgMCAwLTQgNHYyIi8+PGNpcmNsZSBjeD0iMTIiIGN5PSI3IiByPSI0Ii8+PC9zdmc+';
                            $fotoUrl = !empty($e['foto_perfil']) ? BASE_URL . 'public/uploads/avatares/' . esc($e['foto_perfil']) : $defaultAvatar;
                            ?>
                            <img src="<?= $fotoUrl ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink:0;" onerror="this.onerror=null; this.src='<?= $defaultAvatar ?>'" alt="Avatar">
                            <?= esc($nombreCompleto) ?>
                        </div>
                    </td>
                    <td class="text-muted fs-sm"><?= esc($e['des_area'] ?? 'No definida') ?></td>
                    <td class="td-actions">
                        <!-- Editar -->
                        <button type="button" class="btn btn-outline btn-icon" title="Editar equipo" 
                                onclick="abrirModalEditar(<?= htmlspecialchars(json_encode([
                                    'id' => $e['cve_bienes'],
                                    'marca' => $e['marca'],
                                    'modelo' => $e['modelo'],
                                    'num_serie' => $e['num_serie'],
                                    'num_inventario' => $e['num_inventario'],
                                    'resguardatario' => $e['resguardatario'],
                                    'cve_area' => $e['cve_area']
                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>



                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="eliminar_equipo">
                            <input type="hidden" name="cve_bienes" value="<?= $e['cve_bienes'] ?>">
                            <button type="button" class="btn btn-outline btn-icon" title="Eliminar equipo" style="color: #F87171; border-color: transparent;"
                                    onclick="confirmarAccionDoble(this, '¿Quieres borrar este equipo del inventario?', '¿Estás SEGURO? Esta acción no se puede deshacer.')">
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
        <i class="fa-solid fa-box-open empty-icon"></i>
        <p>No hay equipos registrados en el inventario.</p>
    </div>
    <?php endif; ?>
</div>

<!-- ======================================================= -->
<!-- MODAL: CREAR EQUIPO                                     -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="modalCrearEquipo">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Nuevo Equipo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalCrearEquipo')">&times;</button>
        </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="_accion" value="crear_equipo">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="marca">Marca <span class="required">*</span></label>
                            <input type="text" id="marca" name="marca" class="form-control" required placeholder="Ej. LENOVO">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="modelo">Modelo <span class="required">*</span></label>
                            <input type="text" id="modelo" name="modelo" class="form-control" required placeholder="Ej. ThinkCentre">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="num_serie">Num. de Serie</label>
                            <input type="text" id="num_serie" name="num_serie" class="form-control" placeholder="Opcional">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="num_inventario">Num. de Inventario</label>
                            <input type="text" id="num_inventario" name="num_inventario" class="form-control" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="resguardatario">Resguardatario <span class="required">*</span></label>
                        <select id="resguardatario" name="resguardatario" class="form-control" required>
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
                        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearEquipo')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Guardar Equipo</button>
                    </div>
                </form>
            </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: EDITAR EQUIPO                                    -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="modalEditarEquipo">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editar Equipo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarEquipo')">&times;</button>
        </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="_accion" value="editar_equipo">
                    <input type="hidden" name="cve_bienes" id="edit_id" value="">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="edit_marca">Marca <span class="required">*</span></label>
                            <input type="text" id="edit_marca" name="marca" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_modelo">Modelo <span class="required">*</span></label>
                            <input type="text" id="edit_modelo" name="modelo" class="form-control" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="edit_num_serie">Num. de Serie</label>
                            <input type="text" id="edit_num_serie" name="num_serie" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_num_inventario">Num. de Inventario</label>
                            <input type="text" id="edit_num_inventario" name="num_inventario" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_resguardatario">Resguardatario <span class="required">*</span></label>
                        <select id="edit_resguardatario" name="resguardatario" class="form-control" required>
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
                        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarEquipo')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Guardar Cambios</button>
                    </div>
                </form>
            </div>
    </div>
</div>

<script>
function abrirModalEditar(data) {
    document.getElementById('edit_id').value = data.id || '';
    document.getElementById('edit_marca').value = data.marca || '';
    document.getElementById('edit_modelo').value = data.modelo || '';
    document.getElementById('edit_num_serie').value = data.num_serie || '';
    document.getElementById('edit_num_inventario').value = data.num_inventario || '';
    document.getElementById('edit_resguardatario').value = data.resguardatario || '';
    document.getElementById('edit_cve_area').value = data.cve_area || '';
    
    abrirModal('modalEditarEquipo');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
