<?php
/**
 * COMECyT Control de Solicitudes
 * Configuración de Base de Datos — v3.1 (PostgreSQL)
 *
 * Responsabilidad: Proporcionar la conexión PDO singleton y utilidades de BD.
 * Driver: PostgreSQL 15+ (pgsql)
 *
 * Para DOCKER: DB_HOST=db, DB_PORT=5432
 * Para local sin Docker: DB_HOST=127.0.0.1, DB_PORT=5432
 */

// ─── Parsear .env ──────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// ─── Constantes de conexión ────────────────────────────────────────────────
// Defaults seguros: sin fallback a 'root' — forzar configuración explícita
define('DB_HOST',   $_ENV['DB_HOST']   ?? '127.0.0.1');
define('DB_PORT',   $_ENV['DB_PORT']   ?? '5432');
define('DB_NAME',   $_ENV['DB_NAME']   ?? 'bd_sisibic');
define('DB_USER',   $_ENV['DB_USER']   ?? '');
define('DB_PASS',   $_ENV['DB_PASS']   ?? '');

// Prefijo de folio institucional
// Prefijo de folio institucional
$folioEnv = $_ENV['FOLIO_PREFIX'] ?? '';
if (empty($folioEnv)) {
    error_log('[COMECyT] ADVERTENCIA: FOLIO_PREFIX no configurado en .env. Se requiere configuración para producción.');
    // En caso de emergencia o primer arranque, usamos CMCT, pero el código ya no lo tiene como fallback 'invisible'
    $folioEnv = 'CMCT'; 
}
define('FOLIO_PREFIX', $folioEnv);

// Rutas de archivos
define('UPLOAD_DIR_SOLICITUDES', $_ENV['UPLOAD_DIR_SOLICITUDES'] ?? (__DIR__ . '/../public/uploads/solicitudes/'));


/**
 * Obtener la conexión PDO PostgreSQL (patrón Singleton).
 *
 * @return PDO Instancia de conexión a la base de datos.
 * @throws RuntimeException Si las credenciales no están configuradas.
 */
function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    if (empty(DB_USER)) {
        error_log('[COMECyT] DB_USER no configurado en .env');
        http_response_code(500);
        mostrarError('Error de configuración del servidor. El archivo .env necesita atención.', 500);
    }

    // DSN PostgreSQL
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        // Establecer timezone del servidor PostgreSQL
        $pdo->exec("SET TIMEZONE TO 'America/Mexico_City'");
    } catch (PDOException $e) {
        error_log('[COMECyT] Error de BD: ' . $e->getMessage());
        http_response_code(500);
        mostrarError('No se pudo establecer conexión con la base de datos en este momento.', 503);
    }

    return $pdo;
}

/**
 * Generar un folio único con formato PREFIJO-YYYY-NNNN.
 * Compatible con PostgreSQL (usa SUBSTRING en lugar de funciones MySQL-only).
 *
 * @param  PDO    $pdo
 * @return string Folio generado.
 */
function generarFolio(PDO $pdo): string
{
    $anio    = date('Y');
    $prefijo = FOLIO_PREFIX . '-' . $anio . '-';

    $stmt = $pdo->prepare(
        "SELECT folio FROM solicitudes
         WHERE folio LIKE :prefijo
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([':prefijo' => $prefijo . '%']);
    $ultimo = $stmt->fetchColumn();

    $numero = $ultimo
        ? (int) substr($ultimo, strlen($prefijo)) + 1
        : 1;

    return $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);
}
