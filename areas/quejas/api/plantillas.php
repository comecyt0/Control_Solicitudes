<?php
/**
 * COMECyT Control de Solicitudes
 * API: Plantillas de Respuesta
 *
 * GET  (sin params)            → lista todas las plantillas
 * POST accion=crear            → nueva plantilla
 * POST accion=editar           → actualizar plantilla
 * POST accion=eliminar         → borrar (solo autor o si global)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$pdo     = getConnection();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

// ── GET: listar ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT id, titulo, contenido, admin_id
         FROM plantillas_respuesta
         ORDER BY admin_id ASC, titulo ASC"
    );
    echo json_encode(['ok' => true, 'plantillas' => $stmt->fetchAll()]);
    exit;
}

// ── POST: mutaciones ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecibido = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $tokenRecibido)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }

    $accion = trim($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $titulo    = mb_substr(trim($_POST['titulo']    ?? ''), 0, 120);
        $contenido = mb_substr(trim($_POST['contenido'] ?? ''), 0, 3000);
        $global    = isset($_POST['global']) && $_POST['global'] === '1';

        if (!$titulo || !$contenido) {
            echo json_encode(['ok' => false, 'error' => 'Título y contenido son obligatorios']);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO plantillas_respuesta (titulo, contenido, admin_id) VALUES (?, ?, ?)"
        );
        $stmt->execute([$titulo, $contenido, $global ? null : $adminId]);

        echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        exit;
    }

    if ($accion === 'editar') {
        $id        = (int) ($_POST['id'] ?? 0);
        $titulo    = mb_substr(trim($_POST['titulo']    ?? ''), 0, 120);
        $contenido = mb_substr(trim($_POST['contenido'] ?? ''), 0, 3000);

        if ($id <= 0 || !$titulo || !$contenido) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
            exit;
        }

        // Verificar que sea el autor (o sea global=null solo superadmin podría)
        $stmt = $pdo->prepare("SELECT admin_id FROM plantillas_respuesta WHERE id = ?");
        $stmt->execute([$id]);
        $pRow = $stmt->fetch();

        if (!$pRow) {
            echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada']);
            exit;
        }
        if ($pRow['admin_id'] !== null && (int) $pRow['admin_id'] !== $adminId) {
            echo json_encode(['ok' => false, 'error' => 'Sin permisos para editar esta plantilla']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE plantillas_respuesta SET titulo = ?, contenido = ? WHERE id = ?");
        $stmt->execute([$titulo, $contenido, $id]);

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT admin_id FROM plantillas_respuesta WHERE id = ?");
        $stmt->execute([$id]);
        $pRow = $stmt->fetch();

        if (!$pRow) {
            echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada']);
            exit;
        }
        if ($pRow['admin_id'] !== null && (int) $pRow['admin_id'] !== $adminId) {
            echo json_encode(['ok' => false, 'error' => 'Sin permisos']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM plantillas_respuesta WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['ok' => true]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
