<?php
/**
 * COMECyT Control de Solicitudes
 * API: Toggle Dark Mode
 *
 * POST → guarda preferencia en BD y sesión, retorna JSON
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$tokenRecibido = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $tokenRecibido)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
    exit;
}

$adminId   = (int) ($_SESSION['admin_id'] ?? 0);
$darkMode  = isset($_POST['dark_mode']) && $_POST['dark_mode'] === '1' ? 1 : 0;

try {
    $pdo  = getConnection();
    $stmt = $pdo->prepare("UPDATE administradores SET dark_mode = ? WHERE id = ?");
    $stmt->execute([$darkMode, $adminId]);

    $_SESSION['admin_dark_mode'] = $darkMode;

    echo json_encode(['ok' => true, 'dark_mode' => $darkMode]);
} catch (Throwable $e) {
    // Columna puede no existir aún (antes de ejecutar SQL)
    $_SESSION['admin_dark_mode'] = $darkMode;
    echo json_encode(['ok' => true, 'dark_mode' => $darkMode, 'warn' => 'BD no actualizada']);
}
