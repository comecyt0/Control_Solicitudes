<?php
/**
 * COMECyT — Health Check Endpoint
 *
 * Verifica que el sistema esté operativo:
 *  - Conexión a PostgreSQL
 *  - Variables de entorno críticas configuradas
 *  - Directorio de uploads con permisos de escritura
 *
 * Acceso: GET /public/health.php
 * Uso en Docker: curl http://localhost:8080/public/health.php
 * Uso en Makefile: make health
 */

require_once __DIR__ . '/../config/database.php';

$checks = [];
$allOk  = true;

// ─── 1. Conexión a BD ────────────────────────────────────────────────────
try {
    $pdo  = getConnection();
    $rows = $pdo->query("SELECT COUNT(*) as total FROM solicitudes")->fetch();
    $checks['database'] = [
        'ok'      => true,
        'message' => 'PostgreSQL conectado. Solicitudes totales: ' . ($rows['total'] ?? 0),
    ];
} catch (Throwable $e) {
    $checks['database'] = ['ok' => false, 'message' => 'Error BD: ' . $e->getMessage()];
    $allOk = false;
}

// ─── 2. Variables de entorno críticas ────────────────────────────────────
$envRequired = ['DB_HOST', 'DB_NAME', 'DB_USER', 'FOLIO_PREFIX'];
$missingEnv  = [];
foreach ($envRequired as $key) {
    if (empty($_ENV[$key])) {
        $missingEnv[] = $key;
    }
}
$checks['env'] = [
    'ok'      => empty($missingEnv),
    'message' => empty($missingEnv)
        ? 'Variables de entorno configuradas correctamente'
        : 'Faltan variables: ' . implode(', ', $missingEnv),
];
if (!empty($missingEnv)) $allOk = false;

// ─── 3. Directorio de uploads ─────────────────────────────────────────────
$uploadsDir = __DIR__ . '/uploads/solicitudes/';
$uploadOk   = is_dir($uploadsDir) && is_writable($uploadsDir);
$checks['uploads'] = [
    'ok'      => $uploadOk,
    'message' => $uploadOk
        ? 'Directorio de uploads accesible y con permisos'
        : 'PROBLEMA: ' . $uploadsDir . ' no existe o sin permisos de escritura',
];
if (!$uploadOk) $allOk = false;

// ─── 4. Extensiones PHP requeridas ───────────────────────────────────────
$extRequired = ['pdo', 'pdo_pgsql', 'mbstring', 'json', 'openssl'];
$missingExt  = array_filter($extRequired, fn($e) => !extension_loaded($e));
$checks['php_extensions'] = [
    'ok'      => empty($missingExt),
    'message' => empty($missingExt)
        ? 'Todas las extensiones PHP requeridas están activas'
        : 'Extensiones faltantes: ' . implode(', ', $missingExt),
];
if (!empty($missingExt)) $allOk = false;

// ─── Respuesta ────────────────────────────────────────────────────────────
$statusCode = $allOk ? 200 : 503;
http_response_code($statusCode);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'status'    => $allOk ? 'ok' : 'error',
    'timestamp' => date('c'),
    'app'       => 'COMECyT Control de Solicitudes v3.1',
    'checks'    => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
