<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Publica — Nueva Cuenta
 *
 * Permite a un visitante registrar su usuario. Las cuentas creadas
 * quedan inactivas hasta que un administrador las aprueba.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

inicializarSesion();

// Si ya hay sesion activa
if (!empty($_SESSION['admin_id'])) {
    redirigir('admin/dashboard.php');
} elseif (!empty($_SESSION['user_id'])) {
    redirigir('public/index.php');
}

$pdo = getConnection();
$errores = [];
$exito = false;

// Áreas predefinidas COMECyT (lista canónica, sin consulta dinámica)
$AREAS_PREDEFINIDAS = [
    'Dirección General',
    'Subdirección de TI',
    'Departamento de Soporte Técnico',
    'Departamento de Redes',
    'Departamento de Sistemas',
    'Recursos Humanos',
    'Administración y Finanzas',
    'Comunicación Social',
    'Jurídico',
    'Vinculación y Difusión',
    'Control Escolar',
    'Planeación',
    'Atención Ciudadana',
    'Archivo General',
    'Servicios Generales',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    $nombre = trim(postParam('nombre'));
    $appat  = trim(postParam('appat'));
    $apmat  = trim(postParam('apmat'));
    $email  = trim(postParam('email'));
    $pass   = postParam('password');
    $areaSelect = trim(postParam('cve_area'));          // Valor del select (nombre o 'otro')
    $areaOtro   = trim(postParam('area_otro'));          // Texto libre cuando se elige "Otro"
    $areaTexto  = ($areaSelect === 'otro') ? $areaOtro : $areaSelect; // Nombre final del área
    $ext    = trim(postParam('ext_telefonica'));

    if (empty($nombre) || empty($appat) || empty($email) || empty($pass) || empty($areaTexto)) {
        $errores[] = 'Todos los campos marcados con * son obligatorios.';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El formato del correo electrónico no es válido.';
    }

    if (mb_strlen($pass) > 0 && mb_strlen($pass) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if (empty($errores)) {
        // Checar que no sea admin (se hace primero por seguridad)
        $stmtV2 = $pdo->prepare("SELECT id FROM administradores WHERE email = ?");
        $stmtV2->execute([$email]);

        if ($stmtV2->fetch()) {
            $errores[] = "El correo '$email' pertenece a un Administrador y no puede ser usado para cuentas de usuario.";
        } else {
            // Checar si existe en cat_personal
            $stmtV1 = $pdo->prepare("SELECT cve_personal FROM cat_personal WHERE correo_institucional = ? OR correo_personal = ?");
            $stmtV1->execute([$email, $email]);
            $usrRow = $stmtV1->fetch();

            if ($usrRow) {
                // El usuario ya existe, registramos solicitud de actualización en vez de cuenta nueva
                $idUsuario = $usrRow['cve_personal'];
                
                // Revisar si ya tiene una solicitud pendiente para no duplicar spam
                $stmtCheckPend = $pdo->prepare("SELECT id FROM solicitudes_actualizacion_personal WHERE cve_personal = ? AND estatus = 'pendiente'");
                $stmtCheckPend->execute([$idUsuario]);
                
                if ($stmtCheckPend->fetch()) {
                    $errores[] = "Ya existe una solicitud de actualización de perfil en revisión para este correo.";
                } else {
                    $stmtInsUpd = $pdo->prepare(
                        "INSERT INTO solicitudes_actualizacion_personal (cve_personal, nombre, appat, apmat, correo_personal, ext_telefonica, cve_area) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    // Por ahora en el registro público piden un solo email que funge como el institucional de login
                    // El campo password no se pide actualizar por la via automatica por seguridad
                    // Buscar cve_area por nombre, usar 1 como fallback seguro
                    $stmtCveA = $pdo->prepare("SELECT cve_area FROM cat_areas WHERE LOWER(TRIM(des_area)) = LOWER(TRIM(?)) LIMIT 1");
                    $stmtCveA->execute([$areaTexto]);
                    $cveArea = (int)($stmtCveA->fetchColumn() ?: 1);
                    $stmtInsUpd->execute([$idUsuario, $nombre, $appat, $apmat, '', $ext, $cveArea]);
                    
                    $exito_actualizacion = true; // Variable bandera para mostrar un layout especial
                }
                
            } else {
                // Guardar usuario inactivo totalmente (Registro Nuevo)
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                // Resolver cve_area a partir del nombre seleccionado
                $stmtCveA2 = $pdo->prepare("SELECT cve_area FROM cat_areas WHERE LOWER(TRIM(des_area)) = LOWER(TRIM(?)) LIMIT 1");
                $stmtCveA2->execute([$areaTexto]);
                $cveAreaReg = (int)($stmtCveA2->fetchColumn() ?: 1);

                $stmtI = $pdo->prepare(
                    "INSERT INTO cat_personal (nombre, appat, apmat, correo_institucional, password_hash, cve_area, ext_telefonica, activo, cve_estatus) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, FALSE, 1)"
                );
                $stmtI->execute([$nombre, $appat, $apmat, $email, $hash, $cveAreaReg, $ext]);
                
                $exito = true; // Registro normal
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crear cuenta para solicitud interna en el sistema COMECyT.">
    <title>Crear Cuenta — COMECyT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: var(--bg-body);
        }
        .register-card {
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid var(--border-color);
        }
        .register-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-logo img {
            max-width: 300px;
            height: auto;
        }
    </style>
</head>
<body class="layout-public">
<?php require_once __DIR__ . '/../includes/loader.php'; ?>

<div class="login-wrapper">
    <div class="register-card">
        
        <div class="register-logo">
            <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT">
            <h2 style="margin-top: 1rem; color: var(--primary);">Crear tu cuenta</h2>
            <p class="text-muted" style="font-size: 0.95rem;">Regístrate para dar seguimiento a tus solicitudes. Tu cuenta deberá ser activada por un administrador.</p>
        </div>

        <?php if (!empty($exito_actualizacion) && $exito_actualizacion): ?>
        <div style="text-align: center; background: rgba(16, 185, 129, 0.1); border: 2px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 2rem; color: #047857;">
            <i class="fa-solid fa-user-clock" style="font-size: 3rem; margin-bottom: 1rem; color: #10B981;"></i>
            <h3 style="margin-bottom: 1rem; color: #065F46;">¡Solicitud de Actualización Enviada!</h3>
            <p style="margin-bottom: 1rem; line-height: 1.6;">
                Detectamos que el correo <strong><?= esc($email) ?></strong> ya está registrado en nuestro inventario institucional de personal.
            </p>
            <p style="margin-bottom: 1rem; line-height: 1.6;">
                En lugar de crear una cuenta duplicada, hemos enviado tu nueva información al departamento administrativo para que evalúen y apliquen la actualización sobre tu perfil existente.
            </p>
            <p style="margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 500; color: #047857;">
                Serás notificado cuando tus datos hayan sido validados.
            </p>
            <div>
                <a href="<?= BASE_URL ?>admin/login.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    De acuerdo, volver a Iniciar Sesión
                </a>
            </div>
        </div>
        
        <?php elseif ($exito): ?>
        <div class="alert alert-success" style="text-align: center;">
            <i class="fa-solid fa-circle-check" style="font-size: 2rem; margin-bottom: 10px; display: block; color: var(--color-success);"></i>
            <strong>¡Registro completado!</strong><br><br>
            Tu cuenta ha sido creada exitosamente. Sin embargo, para mantener la seguridad del sistema, <strong>un administrador debe autorizar tu acceso.</strong><br><br>
            Serás notificado o podrás intentar iniciar sesión más tarde.
            <div style="margin-top: 1.5rem;">
                <a href="<?= BASE_URL ?>admin/login.php" class="btn btn-primary" style="width: 100%;">Volver al inicio de sesión</a>
            </div>
        </div>
        <?php else: ?>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <ul style="margin-top: 5px; padding-left: 20px; font-size: 0.9rem;">
                    <?php foreach ($errores as $err): ?>
                        <li><?= esc($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label" for="nombre">Nombre(s) <span class="required">*</span></label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required value="<?= esc(postParam('nombre')) ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="appat">Apellido Paterno <span class="required">*</span></label>
                        <input type="text" id="appat" name="appat" class="form-control" required value="<?= esc(postParam('appat')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="apmat">Apellido Materno</label>
                        <input type="text" id="apmat" name="apmat" class="form-control" value="<?= esc(postParam('apmat')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Correo Institucional (o personal) <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?= esc(postParam('email')) ?>">
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="cve_area">Área de Adscripción <span class="required">*</span></label>
                        <select id="cve_area" name="cve_area" class="form-control" required
                                onchange="toggleAreaOtro(this.value)">
                            <option value="">-- Seleccione su área --</option>
                            <?php foreach ($AREAS_PREDEFINIDAS as $nombreArea): ?>
                                <option value="<?= esc($nombreArea) ?>"
                                    <?= postParam('cve_area') === $nombreArea ? 'selected' : '' ?>>
                                    <?= esc($nombreArea) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="otro" <?= postParam('cve_area') === 'otro' ? 'selected' : '' ?>>
                                Otro (especificar)
                            </option>
                        </select>
                        <div id="areaOtroWrap" style="margin-top:8px; display:<?= postParam('cve_area') === 'otro' ? 'block' : 'none' ?>;">
                            <input type="text" id="area_otro" name="area_otro"
                                   class="form-control"
                                   placeholder="Especifica tu área de adscripción"
                                   maxlength="150"
                                   value="<?= esc(postParam('area_otro')) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ext_telefonica">Extensión</label>
                        <input type="text" id="ext_telefonica" name="ext_telefonica" class="form-control" value="<?= esc(postParam('ext_telefonica')) ?>" placeholder="Ej. 123">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Mínimo 6 caracteres">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="fa-solid fa-user-plus"></i>
                    Crear mi cuenta
                </button>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="<?= BASE_URL ?>admin/login.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">
                        <i class="fa-solid fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            </form>

        <?php endif; ?>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/app.js?v=2.0"></script>
<script>
function toggleAreaOtro(val) {
    var wrap = document.getElementById('areaOtroWrap');
    var input = document.getElementById('area_otro');
    if (val === 'otro') {
        wrap.style.display = 'block';
        input.required = true;
    } else {
        wrap.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}
</script>
</body>
</html>
