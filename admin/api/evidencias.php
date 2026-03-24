<?php
/**
 * COMECyT Control de Solicitudes
 * API Endpoint: Evidencias de Solicitud (Files)
 * Handling uploads and listing for admin detailed view.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();
header('Content-Type: application/json');

$pdo = getConnection();
$adminNombre = getNombreAdmin();
$solicitudId = (int) ($_REQUEST['solicitud_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($solicitudId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Solicitud ID inválida']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM solicitud_evidencias WHERE solicitud_id = ? ORDER BY fecha_creacion DESC");
    $stmt->execute([$solicitudId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear fechas
    foreach ($items as &$item) {
        $item['fecha_fmt'] = (new DateTime($item['fecha_creacion']))->format('d M Y, H:i');
        // Ruta absoluta del navegador
        $item['url'] = BASE_URL . 'public/uploads/evidencias/' . $item['archivo_nombre'];
    }

    echo json_encode(['ok' => true, 'evidencias' => $items]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    $accion = $_POST['accion'] ?? 'agregar';

    if ($accion === 'agregar') {
        if ($solicitudId <= 0 || !isset($_FILES['archivo'])) {
            echo json_encode(['ok' => false, 'error' => 'No se recibió archivo o solicitud ID']);
            exit;
        }

        $comentario = trim($_POST['comentario'] ?? '');
        $file = $_FILES['archivo'];

        // Validar file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Error al subir archivo']);
            exit;
        }

        // Permitir solo documentos e imágenes (puedes ampliar si es necesario)
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf','docx','doc','xls','xlsx','zip'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['ok' => false, 'error' => 'Tipo de archivo no permitido (.' . $ext . ')']);
            exit;
        }

        // Crear directorio si no existe
        $uploadDir = __DIR__ . '/../../public/uploads/evidencias/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Nombre de archivo seguro
        $newName = 'ev_' . $solicitudId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
            $stmt = $pdo->prepare("INSERT INTO solicitud_evidencias (solicitud_id, archivo_nombre, comentario, usuario_nombre) VALUES (?, ?, ?, ?)");
            $stmt->execute([$solicitudId, $newName, $comentario ?: null, $adminNombre]);

            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'No se pudo mover el archivo al servidor']);
        }
    }

    if ($accion === 'eliminar') {
        $evidenciaId = (int) $_POST['evidencia_id'];
        if ($evidenciaId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        // Obtener nombre de archivo para borrarlo físicamente
        $stmt = $pdo->prepare("SELECT archivo_nombre FROM solicitud_evidencias WHERE id = ?");
        $stmt->execute([$evidenciaId]);
        $row = $stmt->fetch();

        if ($row) {
            $filePath = __DIR__ . '/../../public/uploads/evidencias/' . $row['archivo_nombre'];
            if (file_exists($filePath)) { @unlink($filePath); }

            $stmt = $pdo->prepare("DELETE FROM solicitud_evidencias WHERE id = ?");
            $stmt->execute([$evidenciaId]);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Evidencia no encontrada']);
        }
    }
    exit;
}
