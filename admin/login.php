<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion - Login
 *
 * Muestra el formulario de autenticacion y procesa las credenciales.
 * Si la sesion ya esta activa, redirige al dashboard.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

inicializarSesion();

// Si ya hay sesion activa
if (!empty($_SESSION['admin_id']) || !empty($_SESSION['user_id']) || !empty($_SESSION['ss_id'])) {
    redirigir('public/index.php');
}

$error   = '';
$motivo  = getParam('motivo');
$timeout = $motivo === 'timeout' || $motivo === 'midnight_timeout';

// Procesar submission del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    
    if (!verificarRateLimit()) {
        $error = 'Demasiados intentos fallidos. Por seguridad, el acceso ha sido bloqueado temporalmente por 5 minutos.';
        http_response_code(429);
    } else {
        $email    = postParam('email');
        $password = postParam('password');

        if (empty($email) || empty($password)) {
            $error = 'Por favor, complete todos los campos.';
        } elseif (iniciarSesion($email, $password)) {
            reiniciarIntentosLogin();
            redirigir('public/router.php');
        } elseif (iniciarSesionUsuario($email, $password)) {
            reiniciarIntentosLogin();
            redirigir('public/router.php');
        } elseif (iniciarSesionSS($email, $password)) {
            reiniciarIntentosLogin();
            redirigir('public/router.php');
        } else {
            registrarIntentoFallido();
            // Mensaje generico para no revelar existencia de cuenta
            $error = 'Credenciales incorrectas o cuenta inactiva. Verifique su correo y contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso Administrativo - COMECyT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/loader.php'; ?>
<div class="login-wrapper">

    <!-- Marca institucional -->
    <div class="login-brand">
        <div class="login-logo">
            <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT">
        </div>
        <p>Control de Solicitudes Internas</p>
    </div>

    <!-- Card de login -->
    <div class="login-card">
        <h2>Acceso Administrativo</h2>
        <p class="subtitle">Ingrese sus credenciales para continuar</p>

        <?php if ($timeout): ?>
        <div class="login-error">
            <i class="fa-solid fa-clock"></i>
            <?php if ($motivo === 'midnight_timeout'): ?>
                Su sesión se cerró por la limpieza diaria de medianoche. Inicie sesión para continuar.
            <?php else: ?>
                Su sesión expiró por inactividad. Inicie sesión nuevamente.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="login-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= esc($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <?= csrfField() ?>
            <div class="field">
                <label for="email">Correo electronico</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= esc(postParam('email')) ?>"
                        placeholder="usuario@comecyt.gob.mx"
                        required
                        autocomplete="email"
                    >
                </div>
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="password123"
                        required
                        autocomplete="current-password"
                    >
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i>
                Iniciar Sesion
            </button>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="<?= BASE_URL ?>public/registro.php" style="color: var(--primary); text-decoration: none; font-weight: 500; font-size: 0.9rem;">
                    ¿No tienes cuenta? <strong> Regístrate aquí</strong>
                </a>
            </div>
        </form>
    </div>

    <div class="login-footer">
        <a href="<?= BASE_URL ?>public/index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Volver a la vista de solicitudes
        </a>
    </div>
</div>
</body>
</html>

