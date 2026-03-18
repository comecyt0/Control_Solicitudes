<?php
/**
 * COMECyT Control de Solicitudes
 * API Calendario Público — v1.0
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

// Validar sesión (Usuario o Admin)
inicializarSesion();
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Sesión no válida']);
    exit;
}

$pdo = getConnection();
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($accion === 'listar') {
        // Listar eventos REALES (aprobados) que caigan en el rango solicitado
        $start = $_GET['start'] ?? null;
        $end   = $_GET['end'] ?? null;

        if (!$start || !$end) {
            echo json_encode(['ok' => false, 'error' => 'Rango de fechas requerido']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, titulo AS title, descripcion AS description, fecha_inicio AS start, fecha_fin AS end, color 
                               FROM eventos 
                               WHERE fecha_inicio < ? AND fecha_fin > ?");
        $stmt->execute([$end, $start]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($eventos);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();

    if ($accion === 'solicitar') {
        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $inicio = postParam('fecha_inicio');
        $fin = postParam('fecha_fin');
        $color = postParam('color', '#B19A6D');
        $usuario_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'];

        if (!$titulo || !$inicio || !$fin) {
            echo json_encode(['ok' => false, 'error' => 'Título y fechas son obligatorios']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO sb_calendario_solicitudes (usuario_id, titulo, descripcion, fecha_inicio, fecha_fin, color, estatus) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'pendiente')");
            $stmt->execute([$usuario_id, $titulo, $descripcion, $inicio, $fin, $color]);
            
            echo json_encode(['ok' => true, 'mensaje' => 'Solicitud enviada correctamente']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar la solicitud: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($accion === 'verificar_respuestas') {
        // El usuario consulta si tiene respuestas nuevas a sus solicitudes
        $usuario_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'];
        
        $stmt = $pdo->prepare("SELECT id, titulo, estatus, motivo_rechazo 
                               FROM sb_calendario_solicitudes 
                               WHERE usuario_id = ? AND leido_por_usuario = FALSE AND estatus IN ('aceptado', 'rechazado')");
        $stmt->execute([$usuario_id]);
        $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'respuestas' => $respuestas]);
        exit;
    }

    if ($accion === 'marcar_leido') {
        $id = (int) postParam('id');
        $usuario_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'];

        $stmt = $pdo->prepare("UPDATE sb_calendario_solicitudes SET leido_por_usuario = TRUE WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $usuario_id]);

        echo json_encode(['ok' => true]);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
