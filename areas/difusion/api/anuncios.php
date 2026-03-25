<?php
/**
 * COMECyT Control de Solicitudes
 * API AJAX - Gestión de Anuncios
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['admin_id']) && empty($_SESSION['user_id'])) {
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
        if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = $_FILES['banner']['tmp_name'];

        // Validar que realmente es una imagen
        $tmpInfo = @getimagesize($tmpName);
        if ($tmpInfo === false) {
            error_log('[Anuncios] Banner rechazado: no es una imagen válida.');
            return null;
        }

        $mime = mime_content_type($tmpName);
        $exts = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp', 'image/gif' => '.gif'];
        if (!isset($exts[$mime])) {
            error_log('[Anuncios] Banner rechazado: tipo MIME no permitido: ' . $mime);
            return null;
        }

        $ext = $exts[$mime];
        $filename = 'banner_' . bin2hex(random_bytes(8)) . $ext;

        // RUTA CORRECTA: este archivo está en areas/difusion/api/ → subir 3 niveles llega a la raíz del proyecto
        // dirname(__DIR__, 2) solo sube 2 niveles → llega a areas/ (INCORRECTO)
        // dirname(__DIR__, 3) sube 3 niveles → llega a la raíz C:\Intranet (CORRECTO)
        // Dentro del contenedor Docker: /var/www/html/public/uploads/anuncios/ (montado como volumen)
        $uploadDir = ROOT . '/public/uploads/anuncios/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log('[Anuncios] No se pudo crear el directorio: ' . $uploadDir);
                return null;
            }
        }

        $destino = $uploadDir . $filename;
        if (!move_uploaded_file($tmpName, $destino)) {
            error_log('[Anuncios] move_uploaded_file falló. Origen: ' . $tmpName . ' | Destino: ' . $destino);
            return null;
        }

        error_log('[Anuncios] Banner guardado correctamente: ' . $destino);
        return $filename;
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
