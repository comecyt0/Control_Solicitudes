<?php
/**
 * API para gestión de Alertas de Login
 * COMECyT — Control de Solicitudes
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

inicializarSesion();

// Verificación de sesión administrativa básica
if (empty($_SESSION['admin_id']) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
    exit;
}

header('Content-Type: application/json');

$accion = getParam('accion') ?: postParam('accion');
$pdo = getConnection();

try {
    switch ($accion) {
        case 'listar':
            $stmt = $pdo->query("SELECT * FROM login_alertas ORDER BY orden ASC, creado_en DESC");
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'alertas' => $alertas]);
            break;

        case 'obtener_activas':
            $stmt = $pdo->query("SELECT * FROM login_alertas WHERE activo = TRUE ORDER BY orden ASC");
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'alertas' => $alertas]);
            break;

        case 'crear':
            validarCsrfPost(); // Solo para POST que modifican datos
            $titulo = postParam('titulo');
            
            if (empty($titulo) || empty($_FILES['imagen'])) {
                echo json_encode(['ok' => false, 'msg' => 'Título e imagen requeridos']);
                exit;
            }

            // Procesar Imagen
            $file = $_FILES['imagen'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($ext, $allowed)) {
                echo json_encode(['ok' => false, 'msg' => 'Extensión no permitida']);
                exit;
            }

            $nombreFile = 'alerta_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = ROOT . '/public/uploads/alertas/' . $nombreFile;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $stmt = $pdo->prepare("INSERT INTO login_alertas (titulo, imagen_path, creado_por) VALUES (?, ?, ?)");
                $stmt->execute([$titulo, 'public/uploads/alertas/' . $nombreFile, $_SESSION['admin_id'] ?? $_SESSION['user_id']]);
                echo json_encode(['ok' => true, 'msg' => 'Alerta creada correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al subir la imagen']);
            }
            break;

        case 'toggle':
            validarCsrfPost();
            $id = (int) postParam('id');
            $activo = postParam('activo') === 'true' || postParam('activo') == 1;
            
            $stmt = $pdo->prepare("UPDATE login_alertas SET activo = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$activo ? 1 : 0, $id]);
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado']);
            break;

        case 'eliminar':
            validarCsrfPost();
            $id = (int) postParam('id');
            
            // Obtener ruta para borrar archivo
            $stmt = $pdo->prepare("SELECT imagen_path FROM login_alertas WHERE id = ?");
            $stmt->execute([$id]);
            $path = $stmt->fetchColumn();
            
            if ($path && file_exists(ROOT . '/' . $path)) {
                unlink(ROOT . '/' . $path);
            }
            
            $stmt = $pdo->prepare("DELETE FROM login_alertas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['ok' => true, 'msg' => 'Alerta eliminada']);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}

/**
 * Validación CSRF Inline para APIs JSON
 */
function validarCsrfPost() {
    $tokenPost = $_POST['csrf_token'] ?? '';
    $tokenSess = $_SESSION['csrf_token'] ?? '';
    if (empty($tokenPost) || $tokenPost !== $tokenSess) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Error de seguridad CSRF']);
        exit;
    }
}
