<?php
/**
 * COMECyT Control de Solicitudes
 * API: Comentarios Internos por Solicitud
 *
 * GET  ?solicitud_id=N         → lista de comentarios
 * POST accion=agregar          → nuevo comentario
 * POST accion=eliminar         → borrar comentario (solo el autor)
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

verificarSesionAdmin();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$pdo      = getConnection();
$adminId  = (int) ($_SESSION['admin_id'] ?? 0);
$adminNom = getNombreAdmin();

// ── GET: listar comentarios ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $solId = (int) ($_GET['solicitud_id'] ?? 0);
    if ($solId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, admin_nombre, comentario, privado, created_at
         FROM comentarios_solicitudes
         WHERE solicitud_id = ?
         ORDER BY created_at ASC"
    );
    $stmt->execute([$solId]);
    $rows = $stmt->fetchAll();

    // Formatear fechas
    foreach ($rows as &$r) {
        $r['fecha_fmt'] = (new DateTime($r['created_at']))->format('d/m/Y H:i');
    }
    unset($r);

    echo json_encode(['ok' => true, 'comentarios' => $rows]);
    exit;
}

// ── POST: crear / eliminar ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecibido = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $tokenRecibido)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }

    $accion = trim($_POST['accion'] ?? '');

    // Agregar comentario
    if ($accion === 'agregar') {
        $solId  = (int) ($_POST['solicitud_id'] ?? 0);
        $texto  = trim($_POST['comentario'] ?? '');

        if ($solId <= 0 || $texto === '') {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        if (mb_strlen($texto) > 2000) {
            $texto = mb_substr($texto, 0, 2000);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO comentarios_solicitudes (solicitud_id, admin_id, admin_nombre, comentario)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$solId, $adminId, $adminNom, $texto]);

        echo json_encode([
            'ok'      => true,
            'id'      => (int) $pdo->lastInsertId(),
            'mensaje' => 'Comentario agregado',
        ]);
        exit;
    }

    // Eliminar comentario (solo el autor puede borrar el suyo)
    if ($accion === 'eliminar') {
        $comentId = (int) ($_POST['comentario_id'] ?? 0);
        if ($comentId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        $stmt = $pdo->prepare(
            "DELETE FROM comentarios_solicitudes
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$comentId, $adminId]);

        echo json_encode(['ok' => true, 'borrados' => $stmt->rowCount()]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
