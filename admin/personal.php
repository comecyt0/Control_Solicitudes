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
        $fecha_nacimiento = postParam('fecha_nacimiento');

        if ($idUsuario > 0 && !empty($nombre) && !empty($appat)) {
            $cve_area = !empty($_POST['cve_area']) ? (int) $_POST['cve_area'] : null;
            $rol_jefatura = !empty(postParam('rol_jefatura')) ? postParam('rol_jefatura') : null;
            $nombre_jefatura = !empty(postParam('nombre_jefatura')) ? postParam('nombre_jefatura') : null;

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtU = $pdo->prepare(
                    "UPDATE cat_personal 
                     SET nombre = ?, appat = ?, apmat = ?, correo_institucional = ?, correo_personal = ?, ext_telefonica = ?, password_hash = ?, fecha_nacimiento = ?, cve_area = ?, rol_jefatura = ?, nombre_jefatura = ?
                     WHERE cve_personal = ?"
                );
                $stmtU->execute([$nombre, $appat, $apmat, $correo_institucional, $correo_personal, $ext_telefonica, $hash, $fecha_nacimiento, $cve_area, $rol_jefatura, $nombre_jefatura, $idUsuario]);
            } else {
                $stmtU = $pdo->prepare(
                    "UPDATE cat_personal 
                     SET nombre = ?, appat = ?, apmat = ?, correo_institucional = ?, correo_personal = ?, ext_telefonica = ?, fecha_nacimiento = ?, cve_area = ?, rol_jefatura = ?, nombre_jefatura = ?
                     WHERE cve_personal = ?"
                );
                $stmtU->execute([$nombre, $appat, $apmat, $correo_institucional, $correo_personal, $ext_telefonica, $fecha_nacimiento, $cve_area, $rol_jefatura, $nombre_jefatura, $idUsuario]);
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
    } elseif ($accion === 'aprobar_reemplazo_perfil') {
        $idReq = (int) postParam('cve_personal');
        $stmtS = $pdo->prepare("SELECT cambios_tmp FROM cat_personal WHERE cve_personal = ?");
        $stmtS->execute([$idReq]);
        $req = $stmtS->fetch();
        if ($req && !empty($req['cambios_tmp'])) {
            $cambios = json_decode($req['cambios_tmp'], true);
            $stmtUpd = $pdo->prepare("UPDATE cat_personal SET fecha_nacimiento = ?, foto_perfil = COALESCE(?, foto_perfil), perfil_en_revision = false, cambios_tmp = NULL WHERE cve_personal = ?");
            $stmtUpd->execute([$cambios['fecha_nacimiento'], $cambios['foto_perfil'] ?? null, $idReq]);
            header('Location: ' . BASE_URL . 'admin/personal.php?flash=perfil_aprobado');
            exit;
        }
    } elseif ($accion === 'rechazar_reemplazo_perfil') {
        $idReq = (int) postParam('cve_personal');
        $stmtUpd = $pdo->prepare("UPDATE cat_personal SET perfil_en_revision = false, cambios_tmp = NULL WHERE cve_personal = ?");
        $stmtUpd->execute([$idReq]);
        // Borrar imagen subida en tmp
        $stmtFoto = $pdo->prepare("SELECT cambios_tmp FROM cat_personal WHERE cve_personal = ?");
        $stmtFoto->execute([$idReq]);
        $rowFoto = $stmtFoto->fetch();
        if ($rowFoto && !empty($rowFoto['cambios_tmp'])) {
            $cambiosRech = json_decode($rowFoto['cambios_tmp'], true);
            if (!empty($cambiosRech['foto_perfil']) && file_exists(__DIR__ . '/../public/uploads/avatares/' . $cambiosRech['foto_perfil'])) {
                @unlink(__DIR__ . '/../public/uploads/avatares/' . $cambiosRech['foto_perfil']);
            }
        }
        header('Location: ' . BASE_URL . 'admin/personal.php?flash=perfil_rechazado');
        exit;
    } elseif ($accion === 'asignar_operativos') {
        $jefe_id = (int) postParam('jefe_id');
        $operativos = $_POST['operativos'] ?? [];
        if ($jefe_id > 0) {
            $pdo->beginTransaction();
            try {
                $stmtCl = $pdo->prepare("UPDATE cat_personal SET jefe_directo_id = NULL WHERE jefe_directo_id = ?");
                $stmtCl->execute([$jefe_id]);
                if (!empty($operativos) && is_array($operativos)) {
                    $inQuery = implode(',', array_map('intval', $operativos));
                    $stmtUpd = $pdo->prepare("UPDATE cat_personal SET jefe_directo_id = ? WHERE cve_personal IN ($inQuery)");
                    $stmtUpd->execute([$jefe_id]);
                }
                $pdo->commit();
                header('Location: ' . BASE_URL . 'admin/personal.php?flash=operativos_asignados');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
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
} elseif ($flashCode === 'perfil_aprobado') {
    $mensajeFlash = "El cambio de fotografía y cumpleaños fue aprobado exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'perfil_rechazado') {
    $mensajeFlash = "Se rechazaron los cambios gráficos del perfil y se descartó la imagen.";
    $tipoFlash = "success";
} elseif ($flashCode === 'operativos_asignados') {
    $mensajeFlash = "El equipo de trabajo ha sido asignado correctamente.";
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

// Obtener cambios estéticos pendientes de Perfiles
$stmtPerfilesList = $pdo->query(
    "SELECT cve_personal, nombre, appat, apmat, correo_institucional, cambios_tmp 
     FROM cat_personal 
     WHERE perfil_en_revision = true"
);
$listaPerfilesPendientes = $stmtPerfilesList->fetchAll();

// Cargar catálogo de áreas para el selector del modal
$stmtAreas = $pdo->query("SELECT cve_area, des_area FROM cat_areas ORDER BY cve_area ASC");
$listaAreas = $stmtAreas->fetchAll(PDO::FETCH_ASSOC);

// Filtros de busqueda
$busqueda = getParam('busqueda');
$filtroEstatus = getParam('estatus');

$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = "(u.nombre LIKE ? OR u.appat LIKE ? OR u.apmat LIKE ? OR u.correo_institucional LIKE ? OR u.correo_personal LIKE ? OR a.des_area LIKE ? OR u.ext_telefonica LIKE ? OR u.rol_jefatura LIKE ? OR u.nombre_jefatura LIKE ?)";
    $like = '%' . $busqueda . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like, $like]);
}

if ($filtroEstatus === '1' || $filtroEstatus === '0') {
    $where[] = "u.activo = ?";
    $params[] = $filtroEstatus;
}

$condicion = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener lista de personal con su area aplicando filtros
$sql = "SELECT u.cve_personal as id, u.nombre, u.appat, u.apmat, u.correo_institucional, u.correo_personal, u.ext_telefonica, COALESCE(u.correo_institucional, u.correo_personal, 'Sin correo') as email, u.activo, u.cve_area, a.des_area, u.fecha_nacimiento, u.foto_perfil, u.rol_jefatura, u.nombre_jefatura
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
<!-- SECCION: EDICION DE FOTOGRAFIA Y CUMPLEAÑOS (PERFILES)  -->
<!-- ======================================================= -->
<?php if (!empty($listaPerfilesPendientes)): ?>
<div class="card" style="border: 2px solid #3B82F6; background-color: #EFF6FF; margin-bottom: 2rem;">
    <div class="card-header" style="border-bottom: 1px solid rgba(59, 130, 246, 0.3);">
        <h2 class="card-title" style="color: #1D4ED8;">
            <i class="fa-solid fa-image-portrait"></i> Autorización de Cambios de Perfil (Fase 1)
        </h2>
    </div>
    <div class="table-wrapper">
        <table style="background: transparent;">
            <thead>
                <tr>
                    <th>Empleado Identificado</th>
                    <th>Nuevos Parámetros (Fotografía / Cumpleaños)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaPerfilesPendientes as $perfil): 
                    $nombresProps = trim($perfil['nombre'] . ' ' . $perfil['appat'] . ' ' . $perfil['apmat']);
                    $cambiosJSON = json_decode($perfil['cambios_tmp'], true);
                ?>
                <tr>
                    <td>
                        <div class="fw-600" style="color: #1E40AF;"><?= esc($perfil['correo_institucional']) ?></div>
                        <div><i class="fa-solid fa-user fa-fw text-muted"></i> <?= esc($nombresProps) ?></div>
                    </td>
                    <td>
                        <div style="display:flex; gap: 15px; align-items:center;">
                            <?php if (!empty($cambiosJSON['foto_perfil'])): ?>
                                <img src="<?= BASE_URL ?>public/uploads/avatares/<?= esc($cambiosJSON['foto_perfil']) ?>" alt="Avatar" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                                <span style="font-size: 0.8rem; color: #3B82F6;">[Nueva Foto]</span>
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: #CBD5E1; display:flex; align-items:center; justify-content:center; color:#94A3B8;"><i class="fa-solid fa-image"></i></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($cambiosJSON['fecha_nacimiento'])): ?>
                                <div>
                                    <strong><i class="fa-solid fa-cake-candles" style="color: #B45309;"></i> Cumpleaños propuesto:</strong><br>
                                    <?= date('d/m/Y', strtotime($cambiosJSON['fecha_nacimiento'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="td-actions">
                        <!-- Aceptar -->
                        <form method="POST" action="" style="display:inline-block; margin:0; margin-right: 5px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="aprobar_reemplazo_perfil">
                            <input type="hidden" name="cve_personal" value="<?= $perfil['cve_personal'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Aprobar Foto y Cumpleaños" data-confirm="¿Aprobar que el usuario cambie su foto de perfil y mostrarla en el calendario?">
                                <i class="fa-solid fa-check"></i> Aprobar
                            </button>
                        </form>

                        <!-- Rechazar -->
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="rechazar_reemplazo_perfil">
                            <input type="hidden" name="cve_personal" value="<?= $perfil['cve_personal'] ?>">
                            <button type="submit" class="btn btn-outline btn-icon" title="Rechazar Cambios Estéticos" style="color: #DC2626; border-color: transparent;" data-confirm="¿Rechazar los cambios fotográficos de este usuario? (La imagen temporal será descartada).">
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
    <style>
    .user-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        padding: 1rem 0;
    }
    .user-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .user-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    }
    .user-card-header {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 1.5rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        background: #e2e8f0;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .user-header-info {
        flex: 1;
        min-width: 0;
    }
    .user-name {
        margin: 0 0 4px 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .user-card-body {
        padding: 1.2rem 1.5rem;
        flex: 1;
        color: #475569;
        font-size: 0.95rem;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .user-info-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .user-info-row i {
        width: 16px;
        color: #94a3b8;
        margin-top: 3px;
        text-align: center;
    }
    .user-info-row span {
        flex: 1;
        word-break: break-word;
    }
    .user-card-footer {
        padding: 1rem 1.5rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }
    .user-card-footer .btn-icon {
        width: 34px;
        height: 34px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        border-radius: 6px;
    }
    </style>
    
    <?php
    $lideres = [];
    $operativos = [];
    foreach ($listaUsuarios as $u) {
        if (!empty($u['rol_jefatura'])) $lideres[] = $u;
        else $operativos[] = $u;
    }

    $renderUserCard = function($usr) {
        ob_start();
        $nombreCompleto = trim($usr['nombre'] . ' ' . $usr['appat'] . ' ' . $usr['apmat']);
        $defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjOTRhM2I4IiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHBhdGggZD0iTTIwIDIxdi0yYTRgNCAwIDAgMC00LTRIOTRhNCA0IDAgMCAwLTQgNHYyIi8+PGNpcmNsZSBjeD0iMTIiIGN5PSI3IiByPSI0Ii8+PC9zdmc+';
        $fotoUrl = !empty($usr['foto_perfil']) ? BASE_URL . 'public/uploads/avatares/' . esc($usr['foto_perfil']) : $defaultAvatar;
    ?>
        <div class="user-card">
            <div class="user-card-header">
                <img src="<?= $fotoUrl ?>" alt="Avatar" class="user-avatar" onerror="this.onerror=null; this.src='<?= $defaultAvatar ?>'">
                
                <div class="user-header-info">
                    <h3 class="user-name" title="<?= esc($nombreCompleto) ?>"><?= esc($nombreCompleto) ?></h3>
                    <div class="user-badges">
                        <?php if ($usr['activo']): ?>
                            <span class="badge" style="background: rgba(22, 163, 74, 0.1); color: #16A34A; border: 1px solid rgba(22, 163, 74, 0.2); font-size: 0.7rem; padding: 2px 6px;">
                                Activo
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(248, 113, 113, 0.1); color: #DC2626; border: 1px solid rgba(248, 113, 113, 0.2); font-size: 0.7rem; padding: 2px 6px;">
                                Inactivo
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($usr['rol_jefatura'])): ?>
                            <span class="badge" style="background: rgba(109, 40, 217, 0.1); color: #6D28D9; border: 1px solid rgba(109, 40, 217, 0.2); font-size: 0.7rem; padding: 2px 6px;">
                                <i class="fa-solid <?= $usr['rol_jefatura'] === 'director_area' ? 'fa-user-tie' : 'fa-user-shield' ?>"></i>
                                <?= esc(ucwords(str_replace('_', ' ', $usr['rol_jefatura']))) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="user-card-body">
                <div class="user-info-row" title="Correo Electrónico">
                    <i class="fa-regular fa-envelope"></i>
                    <span><?= esc($usr['email']) ?></span>
                </div>
                
                <?php if (!empty($usr['ext_telefonica'])): ?>
                <div class="user-info-row" title="Extensión Telefónica">
                    <i class="fa-solid fa-phone"></i>
                    <span>Ext: <strong><?= esc($usr['ext_telefonica']) ?></strong></span>
                </div>
                <?php endif; ?>
                
                <div class="user-info-row" title="Área Asignada">
                    <i class="fa-solid fa-building"></i>
                    <span>
                        <?= esc($usr['des_area'] ?? 'General') ?>
                        <?php if (!empty($usr['nombre_jefatura'])): ?>
                            <br><small style="color:#64748b; font-weight: 500;"><i class="fa-solid fa-sitemap" style="font-size:0.75rem; color:#94a3b8;"></i> <?= esc($usr['nombre_jefatura']) ?></small>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="user-card-footer">
                <!-- Editar -->
                <button type="button" class="btn btn-outline btn-icon" title="Editar personal" 
                        onclick="abrirModalEditar(<?= htmlspecialchars(json_encode([
                            'id'                   => $usr['id'],
                            'nombre'               => $usr['nombre'],
                            'appat'                => $usr['appat'],
                            'apmat'                => $usr['apmat'],
                            'correo_institucional' => $usr['correo_institucional'],
                            'correo_personal'      => $usr['correo_personal'],
                            'ext_telefonica'       => $usr['ext_telefonica'],
                            'fecha_nacimiento'     => $usr['fecha_nacimiento'],
                            'cve_area'             => (int)($usr['cve_area'] ?? 0),
                            'rol_jefatura'         => $usr['rol_jefatura'],
                            'nombre_jefatura'      => $usr['nombre_jefatura']
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

                <!-- Gestionar Operativos -->
                <?php if (!empty($usr['rol_jefatura'])): ?>
                    <button type="button" class="btn btn-outline btn-icon" title="Gestionar equipo operativo" style="color: #6D28D9; border-color: transparent;"
                            onclick="abrirModalOperativos(<?= $usr['id'] ?>, '<?= esc(addslashes($nombreCompleto)) ?>')">
                        <i class="fa-solid fa-users-gear"></i>
                    </button>
                <?php endif; ?>

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
            </div>
        </div>
    <?php
        return ob_get_clean();
    };
    ?>

    <?php if (!empty($lideres)): ?>
        <h3 style="margin: 1.5rem 0 1rem; color: #6D28D9; font-size: 1.15rem; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
            <i class="fa-solid fa-users-viewfinder"></i> Equipo Directivo y Jefaturas
        </h3>
        <div class="user-cards-grid">
            <?php foreach ($lideres as $usr) echo $renderUserCard($usr); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($operativos)): ?>
        <h3 style="margin: 2rem 0 1rem; color: #475569; font-size: 1.15rem; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
            <i class="fa-solid fa-users"></i> Personal Operativo
        </h3>
        <div class="user-cards-grid">
            <?php foreach ($operativos as $usr) echo $renderUserCard($usr); ?>
        </div>
    <?php endif; ?>
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
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto; padding-right: 15px;">
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

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="edit_ext_telefonica">Extensión Telefónica</label>
                            <input type="text" id="edit_ext_telefonica" name="ext_telefonica" class="form-control" placeholder="Ej. 123">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="edit_fecha_nacimiento">Fecha Nacimiento <span class="required">*</span></label>
                            <input type="date" id="edit_fecha_nacimiento" name="fecha_nacimiento" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 0.5rem;">
                        <label class="form-label" for="edit_cve_area">
                            <i class="fa-solid fa-building" style="color: var(--color-primary); margin-right: 4px;"></i>
                            Área Departamental
                            <small style="font-weight:400; color:#64748b; margin-left:4px;">(define a qué panel se redirige)</small>
                        </label>
                        <select id="edit_cve_area" name="cve_area" class="form-control">
                            <option value="">-- Sin área asignada --</option>
                            <?php foreach ($listaAreas as $area): ?>
                            <option value="<?= (int)$area['cve_area'] ?>"><?= esc($area['cve_area'] . ' – ' . $area['des_area']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display:block; margin-top:5px; color: #64748b; font-size:0.82rem;">
                            <i class="fa-solid fa-circle-info" style="margin-right:3px;"></i>
                            Al cambiar el área, el inicio de sesión del empleado lo redirigirá automáticamente al panel correcto.
                        </small>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; padding: 1rem; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="edit_rol_jefatura">
                                <i class="fa-solid fa-user-tie" style="color: #6D28D9; margin-right: 4px;"></i> Roles de Alta Dirección
                            </label>
                            <select id="edit_rol_jefatura" name="rol_jefatura" class="form-control">
                                <option value="">-- Empleado regular --</option>
                                <option value="jefe_departamento">Jefe de Departamento</option>
                                <option value="director_area">Director de Área</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="edit_nombre_jefatura">
                                Nombre Específico del Departamento/Área
                            </label>
                            <input type="text" id="edit_nombre_jefatura" name="nombre_jefatura" class="form-control" placeholder="Ej. Departamento de Soporte Técnico">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0; margin-top: 1rem;">
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
    document.getElementById('edit_usuario_id').value         = data.id || '';
    document.getElementById('edit_nombre').value             = data.nombre || '';
    document.getElementById('edit_appat').value              = data.appat || '';
    document.getElementById('edit_apmat').value              = data.apmat || '';
    document.getElementById('edit_correo_inst').value        = data.correo_institucional || '';
    document.getElementById('edit_correo_per').value         = data.correo_personal || '';
    document.getElementById('edit_ext_telefonica').value     = data.ext_telefonica || '';
    document.getElementById('edit_fecha_nacimiento').value   = data.fecha_nacimiento || '';
    document.getElementById('edit_password').value           = '';
    document.getElementById('edit_rol_jefatura').value       = data.rol_jefatura || '';
    document.getElementById('edit_nombre_jefatura').value    = data.nombre_jefatura || '';

    // Seleccionar el área correcta
    const selArea = document.getElementById('edit_cve_area');
    if (selArea) {
        selArea.value = data.cve_area || '';
    }
    
    abrirModal('modalEditarPersonal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- ======================================================= -->
<!-- MODAL: GESTIONAR OPERATIVOS                             -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="modalOperativos">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Asignar Equipo: <span id="op_jefe_nombre"></span></h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalOperativos')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="_accion" value="asignar_operativos">
                <input type="hidden" name="jefe_id" id="op_jefe_id" value="">
                
                <p class="text-muted" style="margin-bottom: 15px;">Selecciona al personal que reporta directamente a este líder departamental.</p>
                
                <div id="op_loading" style="text-align:center; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando personal...</div>
                
                <div id="op_listado" style="display:none; max-height: 400px; overflow-y:auto; border:1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                    <!-- checkboxes -->
                </div>
                
                <div style="margin-top: 1.5rem; text-align: right;">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalOperativos')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left: 10px;" id="btn_save_ops">Guardar Asignaciones</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function abrirModalOperativos(jefeId, jefeNombre) {
    document.getElementById('op_jefe_id').value = jefeId;
    document.getElementById('op_jefe_nombre').innerText = jefeNombre;
    document.getElementById('op_loading').style.display = 'block';
    document.getElementById('op_listado').style.display = 'none';
    document.getElementById('op_listado').innerHTML = '';
    
    abrirModal('modalOperativos');
    
    try {
        const resp = await fetch(`api/operativos.php?jefe_id=${jefeId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        
        if(data.ok) {
            let html = '';
            let currentArea = null;
            
            data.data.forEach(emp => {
                if (currentArea !== emp.des_area) {
                    currentArea = emp.des_area;
                    html += `<div style="font-weight:bold; background:#f1f5f9; padding:5px 8px; margin-top:10px; border-radius:4px; font-size:0.9rem;">${currentArea || 'Generales'}</div>`;
                }
                const checked = (emp.jefe_directo_id == jefeId) ? 'checked' : '';
                html += `
                    <label style="display:flex; align-items:center; gap:8px; padding:5px 8px; cursor:pointer; border-bottom:1px solid #f1f5f9;">
                        <input type="checkbox" name="operativos[]" value="${emp.id}" ${checked}>
                        <span>${emp.nombre} ${emp.appat} ${emp.apmat || ''} 
                            <small style="color:#64748b; margin-left:5px;">${emp.correo_institucional || 'Sin correo'}</small>
                        </span>
                    </label>
                `;
            });
            
            document.getElementById('op_listado').innerHTML = html;
            document.getElementById('op_loading').style.display = 'none';
            document.getElementById('op_listado').style.display = 'block';
        }
    } catch(e) {
        console.error("Error cargando operativos:", e);
        document.getElementById('op_loading').innerHTML = '<span style="color:red;">Error de conexión. Intente otra vez.</span>';
    }
}
</script>
