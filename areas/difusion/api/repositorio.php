<?php
/**
 * COMECyT Control de Solicitudes
 * API de Módulo de Difusión - Repositorio
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

header('Content-Type: application/json');

// Inicializar sesión ANTES de leer $_SESSION (obligatorio en APIs AJAX)
// Ver claude.md §Troubleshooting — CSRF void-vs-bool
if (session_status() === PHP_SESSION_NONE && function_exists('inicializarSesion')) {
    inicializarSesion();
}

// Validación CSRF inline — NO usar validarCsrfPost() aquí:
// esa función es :void y llama a mostrarError() que emite HTML,
// lo que rompe JSON.parse() en el cliente fetch().
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenPost    = $_POST['csrf_token'] ?? '';
    $tokenSession = $_SESSION['csrf_token'] ?? '';
    if (empty($tokenPost) || empty($tokenSession) || !hash_equals($tokenSession, $tokenPost)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido. Recarga la página e intenta de nuevo.']);
        exit;
    }
}

verificarSesionAdmin();

if ($_SESSION['area_slug_activa'] !== 'difusion') {
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos de autor editorial.']);
    exit;
}

$pdo = getConnection();
$accion = postParam('accion');
$id_admin = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

if ($accion === 'crear') {
    $titulo = trim(postParam('titulo'));
    $desc = trim(postParam('descripcion'));
    $tipo = postParam('tipo');
    
    if (!$titulo || !$tipo) {
        echo json_encode(['ok' => false, 'error' => 'Faltan campos obligatorios.']);
        exit;
    }
    
    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Error al recibir el archivo.']);
        exit;
    }
    
    $file = $_FILES['archivo'];
    if ($file['size'] > 50 * 1024 * 1024) { // 50MB max (videos cortos)
        echo json_encode(['ok' => false, 'error' => 'El archivo supera el límite de 50MB.']);
        exit;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $validas = ['jpg','jpeg','png','webp','gif','mp4','pdf','zip'];
    if (!in_array($ext, $validas)) {
        echo json_encode(['ok' => false, 'error' => 'Formato de archivo no válido.']);
        exit;
    }
    
    $nombreGuardado = bin2hex(random_bytes(10)) . '.' . $ext;
    // Directorio de almacenamiento a través de raíz de docker o local ROOT (Windows env fails with dirname)
    // El workaround es asegurar que se guarde en la subcarpeta publica:
    $directorioDestino = ROOT . '/public/uploads/multimedia';
    if (!is_dir($directorioDestino)) {
        @mkdir($directorioDestino, 0775, true);
    }
    
    $rutaFinal = $directorioDestino . '/' . $nombreGuardado;
    if (!move_uploaded_file($file['tmp_name'], $rutaFinal)) {
         echo json_encode(['ok' => false, 'error' => 'Error de escritura en el servidor.']);
         exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO df_multimedia (titulo, descripcion, tipo, archivo_ruta, creado_por) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $desc, $tipo, $nombreGuardado, $id_admin]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        // Borrar el archivo si la BD falla para no dejar basura
        if (file_exists($rutaFinal)) @unlink($rutaFinal);
        echo json_encode(['ok' => false, 'error' => 'Error de Base de Datos: ' . $e->getMessage()]);
    }
}
elseif ($accion === 'eliminar') {
    $id = (int)postParam('id');
    $stmt = $pdo->prepare("SELECT archivo_ruta FROM df_multimedia WHERE id = ?");
    $stmt->execute([$id]);
    $ruta = $stmt->fetchColumn();
    
    if ($ruta) {
        $fullPath = ROOT . '/public/uploads/multimedia/' . $ruta;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        $pdo->prepare("DELETE FROM df_multimedia WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Archivo no encontrado.']);
    }
}
else {
    echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
}
