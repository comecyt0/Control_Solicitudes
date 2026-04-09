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
            // PRIORIDAD (Fase 3): Si el usuario es Director, mandarlo al Hub de Jurisdicción
            if ($_SESSION['is_director'] ?? false) {
                $destLogin = BASE_URL . 'admin/jurisdiccion.php';
            } else {
                // Si el admin pertenece a un área específica, mandarlo al router
                $cveAreaAdmin = (int) ($_SESSION['admin_cve_area'] ?? 1);
                $destLogin = ($cveAreaAdmin > 1) ? BASE_URL . 'public/router.php' : BASE_URL . 'public/index.php';
            }
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
    <title>Acceso Administrativo | COMECyT Intranet</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/MARCA.png">
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
                <div class="login-alert-item <?= $index === 0 ? 'active' : '' ?>" id="alert-<?= $alerta['id'] ?>" data-titulo="<?= esc($alerta['titulo']) ?>">
                    <img src="<?= BASE_URL . $alerta['imagen_path'] ?>" alt="<?= esc($alerta['titulo']) ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="login-alert-external-info">
            <h2 id="activeAlertTitle"><?= esc($alertas[0]['titulo']) ?></h2>
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
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(12px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.5s ease;
}
.login-alerts-container {
    width: 100%;
    max-width: 1100px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 25px;
}
.login-alert-external-info {
    width: 100%;
    text-align: center;
    color: #fff;
    margin-top: 5px;
}
.login-alert-external-info h2 {
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 4px 10px rgba(0,0,0,0.5);
    background: linear-gradient(135deg, #fff, #b19a6d);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.login-alerts-content {
    width: 100%;
    background: #000;
    border-radius: 30px;
    overflow: hidden;
    box-shadow: 0 40px 100px rgba(0,0,0,0.8);
    border: 1px solid rgba(255,255,255,0.1);
    position: relative;
    max-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.login-alert-item {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    display: none;
}
.login-alert-item.active {
    opacity: 1;
    display: flex;
    position: relative;
    width: 100%;
}
.login-alert-item img {
    width: 100%;
    height: auto;
    max-height: 70vh;
    object-fit: contain;
}
.login-alerts-nav {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
    background: rgba(255,255,255,0.05);
    padding: 10px 25px;
    border-radius: 40px;
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
}
.login-alerts-nav button {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.4rem;
    cursor: pointer;
    transition: 0.2s;
}
.login-alerts-nav button:hover { color: #B19A6D; transform: scale(1.1); }

.btn-close-alerts {
    background: linear-gradient(135deg, var(--primary), #8a1d31);
    color: #fff;
    border: none;
    padding: 18px 50px;
    border-radius: 20px;
    font-size: 1.1rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 15px 35px rgba(102,35,49,0.4);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    align-items: center;
    gap: 12px;
}
.btn-close-alerts:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 20px 45px rgba(102,35,49,0.6);
}

@keyframes fadeIn { from { opacity: 0; backdrop-filter: blur(0); } to { opacity: 1; backdrop-filter: blur(12px); } }

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
    const nextAlert = alerts[currentAlertIndex];
    nextAlert.classList.add('active');
    
    // Actualizar título externo
    document.getElementById('activeAlertTitle').textContent = nextAlert.dataset.titulo;
    
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

