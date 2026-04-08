<?php
/**
 * COMECyT Control de Solicitudes
 * API Dirección General — Gestión Editorial de Solicitudes
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
        $sql = "SELECT s.*, 
                COALESCE(u.nombre, a.nombre, 'Usuario Externo') as solicitante_nombre,
                COALESCE(s.persona_solicitante, u.nombre, a.nombre, 'N/A') as persona_solicitante_display,
                COALESCE(s.area_solicitante, 'General') as area_solicitante_display
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

    if ($accion === 'editar') {
        $id      = (int) postParam('id');
        $titulo  = trim(postParam('titulo'));
        $desc    = trim(postParam('descripcion'));
        $color   = postParam('color');
        $inicio  = postParam('fecha_inicio');
        $fin     = postParam('fecha_fin');

        if ($id <= 0 || empty($titulo)) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE sb_calendario_solicitudes 
                                   SET titulo = ?, descripcion = ?, color = ?, fecha_inicio = ?, fecha_fin = ? 
                                   WHERE id = ? AND estatus = 'pendiente'");
            $stmt->execute([$titulo, $desc, $color, $inicio, $fin, $id]);
            echo json_encode(['ok' => true, 'mensaje' => 'Solicitud actualizada correctamente']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($accion === 'gestionar') {
        $id      = (int) postParam('id');
        $estatus = postParam('estatus');
        $motivo  = trim(postParam('motivo'));
        $adminId = $_SESSION['admin_id'] ?? 0;

        if ($id <= 0 || !in_array($estatus, ['aceptado', 'rechazado'])) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE sb_calendario_solicitudes 
                                   SET estatus = ?, motivo_rechazo = ?, admin_id = ? 
                                   WHERE id = ?");
            $stmt->execute([$estatus, $motivo, $adminId, $id]);

            if ($estatus === 'aceptado') {
                $stmtSel = $pdo->prepare("SELECT titulo, descripcion, fecha_inicio, fecha_fin, color, requiere_sala, area_solicitante, persona_solicitante FROM sb_calendario_solicitudes WHERE id = ?");
                $stmtSel->execute([$id]);
                $s = $stmtSel->fetch();

                if ($s) {
                    $ins = $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, requiere_sala, area_solicitante, persona_solicitante) 
                                         VALUES (?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?)");
                    $ins->execute([$s['titulo'], $s['descripcion'], $s['fecha_inicio'], $s['fecha_fin'], $s['color'], $adminId, $s['requiere_sala'], $s['area_solicitante'], $s['persona_solicitante']]);
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
