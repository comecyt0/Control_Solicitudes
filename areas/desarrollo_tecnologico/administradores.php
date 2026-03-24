<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Gestion de Administradores
 *
 * Permite listar, crear, editar y activar/desactivar el acceso
 * al panel de control de otros administradores.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// -------------------------------------------------------
// Procesar acciones de administradores (PRG)
// -------------------------------------------------------
$mensajeFlash = '';
$tipoFlash    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];

    // Solo el administrador principal o sesion activa puede gestionar
    if ($accion === 'crear_admin') {
        $nombre = trim(postParam('nombre'));
        $email  = trim(postParam('email'));
        $pass   = postParam('password');

        if ($nombre && $email && $pass) {
            $stmtV = $pdo->prepare("SELECT id FROM administradores WHERE email = ?");
            $stmtV->execute([$email]);
            if ($stmtV->fetch()) {
                $mensajeFlash = "Error: El correo '$email' ya esta registrado.";
                $tipoFlash = "error";
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $rol  = in_array(postParam('rol'), ['superadmin','admin','revisor'], true) ? postParam('rol') : 'admin';
                $stmtI = $pdo->prepare("INSERT INTO administradores (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)");
                $stmtI->execute([$nombre, $email, $hash, $rol]);
                header('Location: ' . BASE_URL . 'admin/administradores.php?flash=admin_creado');
                exit;
            }
        } else {
            $mensajeFlash = "Todos los campos (Nombre, Email, Contrasena) son obligatorios.";
            $tipoFlash = "error";
        }
    } elseif ($accion === 'editar_admin') {
        $idAdmin = (int) postParam('admin_id');
        $nombre  = trim(postParam('nombre'));
        $email   = trim(postParam('email'));
        $pass    = postParam('password'); // Opcional

        if ($idAdmin > 0 && $nombre && $email) {
            // Verificar colision de email
            $stmtV = $pdo->prepare("SELECT id FROM administradores WHERE email = ? AND id != ?");
            $stmtV->execute([$email, $idAdmin]);
            if ($stmtV->fetch()) {
                $mensajeFlash = "Error: El correo '$email' ya pertenece a otro usuario.";
                $tipoFlash = "error";
            } else {
                $rol = in_array(postParam('rol'), ['superadmin','admin','revisor'], true) ? postParam('rol') : 'admin';
                if (!empty($pass)) {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    $stmtU = $pdo->prepare("UPDATE administradores SET nombre = ?, email = ?, password_hash = ?, rol = ? WHERE id = ?");
                    $stmtU->execute([$nombre, $email, $hash, $rol, $idAdmin]);
                } else {
                    $stmtU = $pdo->prepare("UPDATE administradores SET nombre = ?, email = ?, rol = ? WHERE id = ?");
                    $stmtU->execute([$nombre, $email, $rol, $idAdmin]);
                }
                header('Location: ' . BASE_URL . 'admin/administradores.php?flash=admin_editado');
                exit;
            }
        }
    } elseif ($accion === 'toggle_admin') {
        $idAdmin = (int) postParam('admin_id');
        $nuevoActivo = (int) postParam('activo');
        
        // Evitar que el admin en sesion se desactive a si mismo por error
        if ($idAdmin > 0) {
            if ($idAdmin === (int) $_SESSION['admin_id'] && $nuevoActivo === 0) {
                 $mensajeFlash = "No puedes desactivar tu propia cuenta en sesion activa.";
                 $tipoFlash = "error";
            } else {
                 $stmtT = $pdo->prepare("UPDATE administradores SET activo = ? WHERE id = ?");
                 $stmtT->execute([$nuevoActivo, $idAdmin]);
                 header('Location: ' . BASE_URL . 'admin/administradores.php?flash=admin_toggle');
                 exit;
            }
        }
    }
}

// Leer flash redirect
$flashCode = getParam('flash');
if ($flashCode === 'admin_creado') {
    $mensajeFlash = "Administrador creado exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'admin_editado') {
    $mensajeFlash = "Administrador actualizado correctamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'admin_toggle') {
    $mensajeFlash = "Estatus de acceso actualizado.";
    $tipoFlash = "success";
}

// Obtener lista de administradores
$stmtAdmin = $pdo->query(
    "SELECT id, nombre, email, activo, ultimo_login, fecha_creacion,
            COALESCE(rol, 'admin') AS rol
     FROM administradores ORDER BY nombre ASC"
);
$listaAdmins = $stmtAdmin->fetchAll();

// -------------------------------------------------------
// Variables para la vista
// -------------------------------------------------------
$pageTitle  = 'Gestión de Administradores';
$activeMenu = 'administradores';
$helpPage   = 'administradores';

require_once __DIR__ . '/../includes/header_admin.php';
?>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= esc($tipoFlash) ?>" data-auto-close="4000">
    <i class="fa-solid <?= $tipoFlash === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- SECCION: GESTION DE ADMINISTRADORES                     -->
<!-- ======================================================= -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h2 class="card-title">
            <i class="fa-solid fa-users-gear"></i>
            Administradores del Sistema
        </h2>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModal('modalCrearAdmin')">
            <i class="fa-solid fa-plus"></i> Nuevo Administrador
        </button>
    </div>

    <?php if (!empty($listaAdmins)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estatus</th>
                    <th>Último Acceso</th>
                    <th>Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaAdmins as $adm): ?>
                <tr>
                    <td class="fw-600"><?= esc($adm['nombre']) ?></td>
                    <td><?= esc($adm['email']) ?></td>
                    <td>
                        <?php
                        $rolColors = ['superadmin'=>['#662331','rgba(102,35,49,0.1)'],'admin'=>['#B19A6D','rgba(177,154,109,0.1)'],'revisor'=>['#2563EB','rgba(37,99,235,0.1)']];
                        [$rolColor, $rolBg] = $rolColors[$adm['rol']] ?? ['#6B7280','rgba(107,114,128,0.1)'];
                        $rolLabels = ['superadmin'=>'Super Admin','admin'=>'Administrador','revisor'=>'Revisor'];
                        ?>
                        <span class="badge" style="background:<?= $rolBg ?>;color:<?= $rolColor ?>;border:1px solid <?= $rolColor ?>40;">
                            <?= $rolLabels[$adm['rol']] ?? 'Administrador' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($adm['activo']): ?>
                            <span class="badge" style="background: rgba(22, 163, 74, 0.1); color: #16A34A; border: 1px solid rgba(22, 163, 74, 0.2);">
                                Activo
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(248, 113, 113, 0.1); color: #DC2626; border: 1px solid rgba(248, 113, 113, 0.2);">
                                Inactivo
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted fs-sm">
                        <?= $adm['ultimo_login'] ? formatearFecha($adm['ultimo_login']) : 'Nunca' ?>
                    </td>
                    <td class="text-muted fs-sm"><?= formatearFecha($adm['fecha_creacion']) ?></td>
                    <td class="td-actions">
                        <button type="button" class="btn btn-outline btn-icon" title="Editar"
                                onclick="abrirModalEditarAdmin(<?= $adm['id'] ?>, '<?= esc(addslashes($adm['nombre'])) ?>', '<?= esc(addslashes($adm['email'])) ?>', '<?= esc($adm['rol']) ?>')">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        
                        <!-- Toggle Activo/Inactivo -->
                        <?php if ($adm['id'] !== (int) $_SESSION['admin_id']): ?>
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion" value="toggle_admin">
                            <input type="hidden" name="admin_id" value="<?= $adm['id'] ?>">
                            <input type="hidden" name="activo" value="<?= $adm['activo'] ? '0' : '1' ?>">
                            <button type="button" class="btn btn-outline btn-icon" 
                                    title="<?= $adm['activo'] ? 'Desactivar acceso' : 'Permitir acceso' ?>"
                                    style="color: <?= $adm['activo'] ? '#F87171' : '#4ADE80' ?>; border-color: transparent;"
                                    onclick="mostrarConfirmacionPersonalizada('Confirmación de Accesos', '¿Seguro que deseas <?= $adm['activo'] ? 'desactivar' : 'activar' ?> este administrador?', () => { this.closest('form').submit(); }, <?= $adm['activo'] ? 'true' : 'false' ?>)">
                                <i class="fa-solid <?= $adm['activo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p>No hay administradores registrados.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Crear Admin -->
<div class="modal-backdrop" id="modalCrearAdmin">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-user-plus"></i> Nuevo Administrador
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalCrearAdmin')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_admin">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="c_nombre">Nombre Completo <span class="required">*</span></label>
                    <input type="text" name="nombre" id="c_nombre" class="form-control" required placeholder="Ej. Juan Pérez">
                </div>
                <div class="form-group">
                    <label class="form-label" for="c_email">Correo Electrónico <span class="required">*</span></label>
                    <input type="email" name="email" id="c_email" class="form-control" required placeholder="correo@comecyt.gob.mx">
                </div>
                <div class="form-group">
                    <label class="form-label" for="c_pass_display">Contraseña Provisional <span class="required">*</span></label>
                    <input type="text" name="password" id="c_password" class="form-control" required value="Admin2026!" minlength="6">
                    <small class="text-muted" style="display:block; margin-top:4px;">El usuario usará esta contraseña para su primer ingreso.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="c_rol">Rol de Acceso <span class="required">*</span></label>
                    <select name="rol" id="c_rol" class="form-control">
                        <option value="admin">Administrador</option>
                        <option value="revisor">Revisor (solo lectura)</option>
                        <option value="superadmin">Super Administrador</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearAdmin')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Crear Administrador</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Admin -->
<div class="modal-backdrop" id="modalEditarAdmin">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-user-pen"></i> Editar Administrador
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarAdmin')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="editar_admin">
            <input type="hidden" name="admin_id" id="e_admin_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="e_nombre">Nombre Completo <span class="required">*</span></label>
                    <input type="text" name="nombre" id="e_nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="e_email">Correo Electrónico <span class="required">*</span></label>
                    <input type="email" name="email" id="e_email" class="form-control" required>
                </div>
                <div class="form-group" style="margin-top:20px; padding-top:20px; border-top: 1px dashed var(--border-color);">
                    <label class="form-label" for="e_password">Cambiar Contraseña (Opcional)</label>
                    <input type="text" name="password" id="e_password" class="form-control" placeholder="Dejar en blanco para conservar actual" minlength="6">
                    <small class="text-muted" style="display:block; margin-top:4px;">Si escribes una nueva, reemplazará a la existente de forma permanente.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="e_rol">Rol de Acceso</label>
                    <select name="rol" id="e_rol" class="form-control">
                        <option value="admin">Administrador</option>
                        <option value="revisor">Revisor (solo lectura)</option>
                        <option value="superadmin">Super Administrador</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarAdmin')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEditarAdmin(id, nombre, email, rol) {
    document.getElementById('e_admin_id').value = id;
    document.getElementById('e_nombre').value   = nombre;
    document.getElementById('e_email').value     = email;
    document.getElementById('e_password').value  = '';
    const rolSel = document.getElementById('e_rol');
    if (rolSel) rolSel.value = rol || 'admin';
    abrirModal('modalEditarAdmin');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
