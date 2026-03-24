<?php
/**
 * COMECyT Control de Solicitudes
 * API Admin Gestión de Solicitudes de Calendario — v1.0
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/auth.php';

verificarSesionAdmin();
$pdo = getConnection();

$accion = $_REQUEST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($accion === 'listar_pendientes') {
        // Listar solicitudes pendientes con nombre del solicitante (User o Admin)
        $sql = "SELECT s.*, 
                COALESCE(u.nombre, a.nombre, 'Usuario Externo') as solicitante_nombre
                FROM sb_calendario_solicitudes s
                LEFT JOIN cat_personal u ON s.usuario_id = u.cve_personal
                LEFT JOIN administradores a ON s.usuario_id = a.id
                WHERE s.estatus = 'pendiente'
                ORDER BY s.creado_en DESC";
        $stmt = $pdo->query($sql);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'solicitudes' => $solicitudes]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();

    if ($accion === 'gestionar') {
        $id      = (int) postParam('id');
        $estatus = postParam('estatus'); // 'aceptado' o 'rechazado'
        $motivo  = trim(postParam('motivo'));
        $adminId = $_SESSION['admin_id'];

        if ($id <= 0 || !in_array($estatus, ['aceptado', 'rechazado'])) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // 1. Actualizar estatus de la solicitud
            $stmt = $pdo->prepare("UPDATE sb_calendario_solicitudes 
                                   SET estatus = ?, motivo_rechazo = ?, admin_id = ? 
                                   WHERE id = ?");
            $stmt->execute([$estatus, $motivo, $adminId, $id]);

            // 2. Si se acepta, crear el evento real
            if ($estatus === 'aceptado') {
                $stmtSel = $pdo->prepare("SELECT titulo, descripcion, fecha_inicio, fecha_fin, color FROM sb_calendario_solicitudes WHERE id = ?");
                $stmtSel->execute([$id]);
                $s = $stmtSel->fetch();

                if ($s) {
                    $ins = $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) 
                                         VALUES (?, ?, ?, ?, ?, ?, TRUE)");
                    $ins->execute([$s['titulo'], $s['descripcion'], $s['fecha_inicio'], $s['fecha_fin'], $s['color'], $adminId]);
                }
            }

            $pdo->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Solicitud ' . $estatus . ' correctamente']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Error al procesar: ' . $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
