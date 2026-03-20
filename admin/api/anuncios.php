<?php
/**
 * COMECyT Control de Solicitudes
 * API AJAX - Gestión de Anuncios
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['admin_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Sesión expirada o no autorizada.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$accion = $_POST['accion'] ?? '';
$pdo = getConnection();

try {
    // Función helper para procesar el banner
    $procesarBanner = function() {
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $tmpInfo = @getimagesize($_FILES['banner']['tmp_name']);
            if ($tmpInfo !== false) {
                $exts = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
                $mime = mime_content_type($_FILES['banner']['tmp_name']);
                if (isset($exts[$mime])) {
                    $ext = $exts[$mime];
                    $filename = uniqid('banner_') . $ext;
                    // Asegurar la ruta absoluta usando dirname(__DIR__, 2)
                    $uploadDir = dirname(__DIR__, 2) . '/public/uploads/anuncios/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $path = $uploadDir . $filename;
                    if (@move_uploaded_file($_FILES['banner']['tmp_name'], $path)) {
                        return $filename;
                    }
                }
            }
        }
        return null; // Si falla algo retorna null, sin matar el script
    };

    if ($accion === 'crear') {
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'info');
        $activo = ($_POST['activo'] ?? '0') === '1' ? 'true' : 'false';
        $autor = getNombreAdmin();
        $bannerFile = $procesarBanner();

        if (empty($titulo) || empty($contenido)) {
            echo json_encode(['ok' => false, 'error' => 'Título y contenido son obligatorios.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO anuncios (titulo, contenido, tipo, activo, creado_por, banner_url) VALUES (?, ?, ?, $activo, ?, ?)");
        $stmt->execute([$titulo, $contenido, $tipo, $autor, $bannerFile]);
        
        echo json_encode(['ok' => true]);

    } elseif ($accion === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'info');
        $activo = ($_POST['activo'] ?? '0') === '1' ? 'true' : 'false';
        $bannerFile = $procesarBanner();

        if ($id <= 0 || empty($titulo) || empty($contenido)) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            exit;
        }

        if ($bannerFile) {
            $stmt = $pdo->prepare("UPDATE anuncios SET titulo = ?, contenido = ?, tipo = ?, activo = $activo, banner_url = ? WHERE id = ?");
            $stmt->execute([$titulo, $contenido, $tipo, $bannerFile, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE anuncios SET titulo = ?, contenido = ?, tipo = ?, activo = $activo WHERE id = ?");
            $stmt->execute([$titulo, $contenido, $tipo, $id]);
        }
        
        echo json_encode(['ok' => true]);

    } elseif ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM anuncios WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['ok' => true]);
        
    } else {
        echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
    }
} catch (PDOException $e) {
    error_log("[Anuncios API] Error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos.']);
}
