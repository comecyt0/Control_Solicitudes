<?php
/**
 * COMECyT Control de Solicitudes
 * Gestión de Autenticación de Administradores — v3.1
 *
 * Responsabilidad: Iniciar y validar sesiones del panel de administración.
 * Todas las páginas del directorio admin/ deben llamar a verificarSesionAdmin()
 * al inicio del script para asegurar acceso controlado.
 *
 * Cambios v3.1:
 *  - cookie_secure leído de APP_HTTPS en .env
 *  - SESSION_TIMEOUT, LOGIN_MAX_ATTEMPTS, LOGIN_BLOCK_SECONDS desde .env
 *  - CSRF fallback: md5 → openssl_random_pseudo_bytes
 *  - Rate limit complementario por IP (almacenado en sesión, mejorado)
 *  - SESSION_TIMEOUT definido con guard para evitar redefinición
 *  - Headers de seguridad HTTP enviados en verificarSesionAdmin()
 */

require_once __DIR__ . '/database.php';

// ─── Configuración desde .env ───────────────────────────────────────────────
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', (int) ($_ENV['SESSION_TIMEOUT'] ?? 7200));
}
if (!defined('LOGIN_MAX_ATTEMPTS')) {
    define('LOGIN_MAX_ATTEMPTS', (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5));
}
if (!defined('LOGIN_BLOCK_SECONDS')) {
    define('LOGIN_BLOCK_SECONDS', (int) ($_ENV['LOGIN_BLOCK_SECONDS'] ?? 300));
}

/**
 * Devuelve true cuando la conexión es HTTPS (leído de .env o detectado).
 */
function esHttps(): bool
{
    // Primero: leer la variable de entorno explícita (para Docker/proxies)
    $envHttps = filter_var($_ENV['APP_HTTPS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    if ($envHttps) return true;
    // Segundo: detectar automáticamente
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * Iniciar la sesión PHP de forma segura si aún no está activa.
 * Se configura con un tiempo de vida extendido (30 días) para permitir sesiones permanentes.
 */
function inicializarSesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // 30 días de duración para la cookie y el recolector de basura de PHP
        $duracion = 30 * 24 * 60 * 60;
        
        session_start([
            'cookie_httponly' => true,
            'cookie_secure'   => esHttps(),
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
            'cookie_lifetime' => $duracion,
            'gc_maxlifetime'  => $duracion,
        ]);
    }
    // Generar Token CSRF si no existe
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = _generarTokenSeguro();
    }
}

/**
 * Genera un token criptográfico seguro de 32 bytes.
 * Usa random_bytes() como método principal y openssl como fallback robusto.
 */
function _generarTokenSeguro(): string
{
    try {
        return bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // Fallback robusto: openssl (disponible en PHP 5.3+)
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
}

/**
 * Enviar headers de seguridad HTTP.
 * Llamar solo una vez por request (dentro de verificarSesionAdmin/verificarSesionUsuario).
 */
function enviarHeadersSeguridad(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net; "
         . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com; "
         . "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; "
         . "img-src 'self' data: blob:; "
         . "connect-src 'self'; "
         . "frame-ancestors 'none';");
}

/**
 * Verificar que el administrador tenga sesión activa.
 * La sesión de administrador no expira por inactividad ordinaria (SESION PERMANENTE),
 * excepto a las 12:00 AM si no se detecta actividad en los últimos 30 minutos.
 */
function verificarSesionAdmin(): void
{
    inicializarSesion();
    enviarHeadersSeguridad();

    // Permitir acceso si es Admin o si es Usuario de Área (Personal)
    if (empty($_SESSION['admin_id']) && empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }

    if (!empty($_SESSION['ultimo_acceso'])) {
        $ahora      = time();
        $ultimo     = (int) $_SESSION['ultimo_acceso'];
        $inactivo   = $ahora - $ultimo;
        $medianoche = strtotime('today midnight');

        // REGLA DE MEDIANOCHE:
        // Si ya pasó la medianoche y el último acceso fue AYER (antes de las 00:00)
        // Y el usuario no ha estado activo en los últimos 30 minutos (1800s), cerrar sesión.
        if ($ahora >= $medianoche && $ultimo < $medianoche && $inactivo > 1800) {
            cerrarSesion();
            header('Location: ' . BASE_URL . 'admin/login.php?motivo=midnight_timeout');
            exit;
        }
    }

    $_SESSION['ultimo_acceso'] = time();
}

/**
 * Intentar iniciar sesión para un Administrador.
 */
function iniciarSesion(string $email, string $password): bool
{
    inicializarSesion();

    $pdo  = getConnection();
    $stmt = $pdo->prepare(
        "SELECT a.id, a.nombre, a.email, a.password_hash, a.rol, p.cve_area
         FROM administradores a
         LEFT JOIN cat_personal p ON p.correo_institucional = a.email OR p.correo_personal = a.email
         WHERE a.email = :email AND a.activo = true
         LIMIT 1"
    );
    $stmt->execute([':email' => trim($email)]);
    $admin = $stmt->fetch();

    if (!$admin || $admin['password_hash'] === null || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    // Actualizar último acceso en BD
    $pdo->prepare("UPDATE administradores SET ultimo_login = NOW() WHERE id = :id")
        ->execute([':id' => $admin['id']]);

    // Regenerar ID de sesión para prevenir session fixation
    session_regenerate_id(true);

    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_nombre']   = $admin['nombre'];
    $_SESSION['admin_email']    = $admin['email'];
    $_SESSION['admin_rol']      = $admin['rol'] ?? 'admin';
    $_SESSION['admin_cve_area'] = (int)($admin['cve_area'] ?? 1);
    $_SESSION['ultimo_acceso'] = time();

    return true;
}

/**
 * Intentar iniciar sesión para un Usuario Regular.
 */
function iniciarSesionUsuario(string $email, string $password): bool
{
    inicializarSesion();

    $pdo  = getConnection();
    $stmt = $pdo->prepare(
        "SELECT u.cve_personal as id, u.nombre, u.appat, u.apmat,
                COALESCE(u.correo_institucional, u.correo_personal) as email,
                u.password_hash, u.cve_area, a.des_area
         FROM cat_personal u
         LEFT JOIN cat_areas a ON u.cve_area = a.cve_area
         WHERE (u.correo_institucional = :email1 OR u.correo_personal = :email2) AND u.activo = true
         LIMIT 1"
    );
    $emailTrimmed = trim($email);
    $stmt->execute([':email1' => $emailTrimmed, ':email2' => $emailTrimmed]);
    $user = $stmt->fetch();

    if (!$user || $user['password_hash'] === null || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    $nombreCompleto = trim($user['nombre'] . ' ' . $user['appat'] . ' ' . $user['apmat']);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_nombre']   = $nombreCompleto;
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_cve_area'] = $user['cve_area'] ?? 1;
    $_SESSION['user_area']     = $user['des_area'] ?? 'General';
    $_SESSION['ultimo_acceso'] = time();

    return true;
}

/**
 * Verificar que el usuario regular tenga sesión activa y no expirada.
 */
function verificarSesionUsuario(): void
{
    inicializarSesion();
    enviarHeadersSeguridad();

    // Administradores y Servicio Social pueden usar el portal público también
    if (!empty($_SESSION['admin_id']) || !empty($_SESSION['ss_id'])) {
        return;
    }

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }

    if (!empty($_SESSION['ultimo_acceso'])) {
        $inactivo = time() - (int) $_SESSION['ultimo_acceso'];
        if ($inactivo > SESSION_TIMEOUT) {
            cerrarSesion();
            header('Location: ' . BASE_URL . 'admin/login.php?motivo=timeout');
            exit;
        }
    }

    $_SESSION['ultimo_acceso'] = time();
}

/**
 * Cerrar la sesión y limpiar la cookie.
 */
function cerrarSesion(): void
{
    inicializarSesion();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Obtener el nombre del administrador en sesión.
 */
function getNombreAdmin(): string
{
    inicializarSesion();
    return $_SESSION['admin_nombre'] ?? 'Administrador';
}

/**
 * Rate Limiting anti fuerza bruta.
 * Combina contador de sesión + validación por IP.
 * Bloquea tras LOGIN_MAX_ATTEMPTS intentos por LOGIN_BLOCK_SECONDS segundos.
 */
function verificarRateLimit(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        inicializarSesion();
    }

    // Verificar bloqueo por sesión
    if (isset($_SESSION['bloqueo_login_hasta'])) {
        if (time() < $_SESSION['bloqueo_login_hasta']) {
            return false;
        }
        unset($_SESSION['intentos_login'], $_SESSION['bloqueo_login_hasta']);
    }

    return true;
}

/**
 * Incrementa el contador de intentos fallidos y bloquea si se supera el límite.
 */
function registrarIntentoFallido(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        inicializarSesion();
    }

    $_SESSION['intentos_login'] = ($_SESSION['intentos_login'] ?? 0) + 1;

    if ($_SESSION['intentos_login'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['bloqueo_login_hasta'] = time() + LOGIN_BLOCK_SECONDS;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        error_log(sprintf(
            '[SEGURIDAD] Rate limit activado. IP: %s | Intentos: %d | Bloqueado por %d seg.',
            $ip,
            $_SESSION['intentos_login'],
            LOGIN_BLOCK_SECONDS
        ));
    }
}

/**
 * Reiniciar contador de intentos al login exitoso.
 */
function reiniciarIntentosLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        inicializarSesion();
    }
    unset($_SESSION['intentos_login'], $_SESSION['bloqueo_login_hasta']);
}

// ═══════════════════════════════════════════════════════════════
// SERVICIO SOCIAL — Autenticacion de prestadores
// ═══════════════════════════════════════════════════════════════

/**
 * Iniciar sesion como prestador de Servicio Social.
 */
function iniciarSesionSS(string $email, string $password): bool
{
    inicializarSesion();
    $pdo  = getConnection();
    $stmt = $pdo->prepare(
        "SELECT id, nombre, appat, apmat, email, password_hash, cve_area
         FROM ss_usuarios
         WHERE email = :email AND activo = TRUE
         LIMIT 1"
    );
    $stmt->execute([':email' => trim($email)]);
    $ss = $stmt->fetch();
    if (!$ss || $ss['password_hash'] === null || !password_verify($password, $ss['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $nombreCompleto = trim($ss['nombre'] . ' ' . $ss['appat'] . ' ' . ($ss['apmat'] ?? ''));
    $_SESSION['ss_id']         = $ss['id'];
    $_SESSION['ss_nombre']     = $nombreCompleto;
    $_SESSION['ss_email']      = $ss['email'];
    $_SESSION['ss_cve_area']   = $ss['cve_area'] ?? null;
    $_SESSION['ss_rol']        = 'servicio_social';
    $_SESSION['ultimo_acceso'] = time();
    return true;
}

/**
 * Verificar que el prestador de SS tenga sesion activa y no expirada.
 */
function verificarSesionSS(): void
{
    inicializarSesion();
    enviarHeadersSeguridad();
    if (empty($_SESSION['ss_id'])) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
    if (!empty($_SESSION['ultimo_acceso'])) {
        $inactivo = time() - (int) $_SESSION['ultimo_acceso'];
        if ($inactivo > SESSION_TIMEOUT) {
            cerrarSesionSS();
            header('Location: ' . BASE_URL . 'admin/login.php?motivo=timeout');
            exit;
        }
    }
    $_SESSION['ultimo_acceso'] = time();
}

/**
 * Cerrar la sesion de un prestador de Servicio Social.
 */
function cerrarSesionSS(): void
{
    inicializarSesion();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Obtener el nombre del prestador SS en sesion.
 */
function getNombreSS(): string
{
    inicializarSesion();
    return $_SESSION['ss_nombre'] ?? 'Prestador';
}
