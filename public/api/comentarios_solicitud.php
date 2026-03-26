<?php
/**
 * COMECyT Control de Solicitudes
 * API: Retroalimentación de Solicitudes (Chat de Seguimiento)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

inicializarSesion();
header('Content-Type: application/json; charset=utf-8');

$pdo = getConnection();
$isAdmin = !empty($_SESSION['admin_id']);
$adminId = $_SESSION['admin_id'] ?? null;
$adminNom = getNombreAdmin();

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';

// --- LISTAR ---
if ($accion === 'listar') {
    $solId = (int) ($_GET['solicitud_id'] ?? 0);
    $folio = $_GET['folio'] ?? '';

    if ($solId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID de solicitud inválido']);
        exit;
    }

    // Seguridad para público: verificar que el folio coincida con el ID
    if (!$isAdmin) {
        $chk = $pdo->prepare("SELECT id FROM solicitudes WHERE id = ? AND folio = ?");
        $chk->execute([$solId, $folio]);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        "SELECT id, remitente_tipo, nombre_remitente, mensaje, archivo_nombre, fecha_creacion
         FROM solicitud_respuestas
         WHERE solicitud_id = ?
         ORDER BY fecha_creacion ASC"
    );
    $stmt->execute([$solId]);
    $respuestas = $stmt->fetchAll();

    foreach ($respuestas as &$r) {
        $r['fecha_fmt'] = (new DateTime($r['fecha_creacion']))->format('d/m/Y H:i');
        $r['archivo_url'] = $r['archivo_nombre'] ? BASE_URL . 'public/uploads/respuestas/' . $r['archivo_nombre'] : null;
    }

    echo json_encode(['ok' => true, 'comentarios' => $respuestas]);
    exit;
}

// --- ENVIAR ---
if ($accion === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $solId   = (int) ($_POST['solicitud_id'] ?? 0);
    $folio   = $_POST['folio'] ?? '';
    $mensaje = trim($_POST['mensaje'] ?? '');
    
    // Si no hay mensaje pero hay archivo, permitir
    $hasFile = isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK;

    if ($solId <= 0 || (!$mensaje && !$hasFile)) {
        echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
        exit;
    }

    // Seguridad
    $nombreRemitente = '';
    $tipoRemitente = '';

    if ($isAdmin) {
        $nombreRemitente = $adminNom;
        $tipoRemitente   = 'admin';
    } else {
        // Verificar que el folio coincida
        $stmtS = $pdo->prepare("SELECT solicitante FROM solicitudes WHERE id = ? AND folio = ?");
        $stmtS->execute([$solId, $folio]);
        $sol = $stmtS->fetch();
        if (!$sol) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }
        $nombreRemitente = $sol['solicitante'];
        $tipoRemitente   = 'usuario';
    }

    $archivoNombre = null;
    if ($hasFile) {
        $file = $_FILES['archivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf','docx','doc','xls','xlsx','zip','rar'];
        
        if (!in_array($ext, $allowed)) {
            echo json_encode(['ok' => false, 'error' => 'Formato de archivo no permitido']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/respuestas/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $archivoNombre = 'res_' . $solId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $archivoNombre)) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar el archivo']);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO solicitud_respuestas (solicitud_id, remitente_tipo, admin_id, nombre_remitente, mensaje, archivo_nombre)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$solId, $tipoRemitente, $adminId, $nombreRemitente, $mensaje, $archivoNombre]);

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Acción no permitida']);
