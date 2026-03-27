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
if (!empty($_SESSION['ss_id'])) {
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . BASE_URL . 'servicio_social/dashboard.php"></head><body>Redirigiendo al panel SS... <a href="' . BASE_URL . 'servicio_social/dashboard.php">Click aquí</a></body></html>';
    exit;
} elseif (!empty($_SESSION['admin_id']) || !empty($_SESSION['user_id'])) {
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . BASE_URL . 'public/index.php"></head><body>Redirigiendo al hub... <a href="' . BASE_URL . 'public/index.php">Click aquí</a></body></html>';
    exit;
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
            // Si el admin pertenece a un área específica, mandarlo al router
            $cveAreaAdmin = (int) ($_SESSION['admin_cve_area'] ?? 1);
            $destLogin = ($cveAreaAdmin > 1) ? BASE_URL . 'public/router.php' : BASE_URL . 'public/index.php';
            echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . $destLogin . '"></head><body>Autenticado. <a href="' . $destLogin . '">Click aquí</a></body></html>'; exit;

        } elseif (iniciarSesionUsuario($email, $password)) {
            reiniciarIntentosLogin();
            // Si el usuario pertenece a un área específica, mandarlo al router
            $cveAreaUser = (int) ($_SESSION['user_cve_area'] ?? 1);
            $destLoginUser = ($cveAreaUser > 1) ? BASE_URL . 'public/router.php' : BASE_URL . 'public/index.php';
            echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . $destLoginUser . '"></head><body>Autenticado. <a href="' . $destLoginUser . '">Click aquí</a></body></html>'; exit;
        } elseif (iniciarSesionSS($email, $password)) {
            reiniciarIntentosLogin();
            echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . BASE_URL . 'servicio_social/dashboard.php"></head><body>Autenticado SS. <a href="' . BASE_URL . 'servicio_social/dashboard.php">Click aquí</a></body></html>'; exit;
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
        <p>COMECyT Intranet</p>
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

</div>

<!-- Sistema de Alertas de Login (Fase 7) -->
<?php
$alertas = [];
try {
    $pdo = getConnection();
    $stmtA = $pdo->query("SELECT * FROM login_alertas WHERE activo = TRUE ORDER BY orden ASC");
    $alertas = $stmtA->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

if (!empty($alertas)):
?>
<div id="loginAlertsOverlay" class="login-alerts-overlay">
    <div class="login-alerts-container">
        <div class="login-alerts-content">
            <?php foreach ($alertas as $index => $alerta): ?>
                <div class="login-alert-item <?= $index === 0 ? 'active' : '' ?>" id="alert-<?= $alerta['id'] ?>">
                    <img src="<?= BASE_URL . $alerta['imagen_path'] ?>" alt="<?= esc($alerta['titulo']) ?>">
                    <div class="login-alert-caption">
                        <h3><?= esc($alerta['titulo']) ?></h3>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($alertas) > 1): ?>
            <div class="login-alerts-nav">
                <button onclick="changeAlert(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                <span id="alertCounter">1 / <?= count($alertas) ?></span>
                <button onclick="changeAlert(1)"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        <?php endif; ?>

        <button class="btn-close-alerts" onclick="closeLoginAlerts()">
            <i class="fa-solid fa-xmark"></i> Entrar al Login
        </button>
    </div>
</div>

<style>
.login-alerts-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(8px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.4s ease-out;
}
.login-alerts-container {
    width: 100%;
    max-width: 900px;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.login-alerts-content {
    width: 100%;
    background: #000;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 30px 60px rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.1);
    position: relative;
    aspect-ratio: 16 / 9;
}
.login-alert-item {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.5s ease;
    display: flex;
    flex-direction: column;
}
.login-alert-item.active {
    opacity: 1;
    position: relative;
}
.login-alert-item img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.login-alert-caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 30px;
    background: linear-gradient(transparent, rgba(0,0,0,0.9));
    color: #fff;
    text-align: center;
}
.login-alert-caption h3 { margin: 0; font-size: 1.5rem; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }

.login-alerts-nav {
    display: flex;
    align-items: center;
    gap: 15px;
    color: #fff;
    background: rgba(255,255,255,0.1);
    padding: 8px 15px;
    border-radius: 30px;
    backdrop-filter: blur(5px);
}
.login-alerts-nav button {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.2rem;
    cursor: pointer;
    transition: transform 0.2s;
}
.login-alerts-nav button:hover { transform: scale(1.2); }

.btn-close-alerts {
    background: var(--primary);
    color: #fff;
    border: none;
    padding: 15px 40px;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 20px rgba(102,35,49,0.3);
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 10px;
}
.btn-close-alerts:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(102,35,49,0.5);
    background: var(--primary-light);
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

@media (max-width: 768px) {
    .login-alerts-content { aspect-ratio: 4 / 5; }
    .login-alert-caption h3 { font-size: 1.1rem; }
}
</style>

<script>
let currentAlertIndex = 0;
const alerts = document.querySelectorAll('.login-alert-item');
const counter = document.getElementById('alertCounter');

function changeAlert(dir) {
    alerts[currentAlertIndex].classList.remove('active');
    currentAlertIndex = (currentAlertIndex + dir + alerts.length) % alerts.length;
    alerts[currentAlertIndex].classList.add('active');
    if(counter) counter.textContent = `${currentAlertIndex + 1} / ${alerts.length}`;
}

function closeLoginAlerts() {
    const overlay = document.getElementById('loginAlertsOverlay');
    overlay.style.transition = 'opacity 0.4s ease';
    overlay.style.opacity = '0';
    setTimeout(() => overlay.remove(), 400);
}
</script>
<?php endif; ?>
</body>
</html>

