<?php
/**
 * COMECyT Control de Solicitudes
 * API AJAX — Notificaciones Universales (v16.0)
 *
 * Devuelve el conteo de pendientes de todos los módulos:
 * - Chat (DMs y Grupal)
 * - Solicitudes Nuevas
 * - Personal (Cambios y Fotos)
 * - Equipos (Altas pendientes)
 * - Calendario (Espacios)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

inicializarSesion();
if (empty($_SESSION['admin_id']) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'No autorizado']));
}

$pdo = getConnection();
$adminId = $_SESSION['admin_id'] ?? null;
$userId  = $_SESSION['user_id'] ?? null;
$cveArea = $_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0;

// Identificador único de usuario (A for Admin, P for Personal)
$uid = $adminId ? 'A'.$adminId : ($userId ? 'P'.$userId : null);

$resultado = [
    'ok' => true,
    'pendientes' => [
        'chat'        => 0,
        'solicitudes' => 0,
        'personal'    => 0,
        'equipos'     => 0,
        'calendario'  => 0,
    ],
    'total' => 0
];

try {
    // 1. Mensajes de Chat (DMs dirigidos a mi O canal grupal de mi área)
    // Se cuentan mensajes con ID mayor al último leído registrado en sb_chat_lectura
    $stmtChat = $pdo->prepare("
        SELECT COUNT(*) 
        FROM sb_chat_mensajes 
        WHERE (
            (destinatario_id = :adminid AND :adminid IS NOT NULL)
            OR (destinatario_usuario_id = :userid AND :userid IS NOT NULL)
            OR (destinatario_id IS NULL AND destinatario_usuario_id IS NULL AND cve_area = :area)
        )
        AND id > (
            SELECT COALESCE(MAX(ultimo_id_leido), 0) 
            FROM sb_chat_lectura 
            WHERE (admin_id = :adminid2 OR usuario_id = :userid2)
        )
        AND (admin_id <> :adminid3 OR admin_id IS NULL)
        AND (usuario_id <> :userid3 OR usuario_id IS NULL)
    ");
    $stmtChat->execute([
        ':adminid'  => $adminId,
        ':userid'   => $userId,
        ':area'     => $cveArea,
        ':adminid2' => $adminId,
        ':userid2'  => $userId,
        ':adminid3' => $adminId,
        ':userid3'  => $userId
    ]);
    $resultado['pendientes']['chat'] = (int)$stmtChat->fetchColumn();

    // 2. Solicitudes Nuevas (Solo Sistemas o si tiene permisos)
    // Para simplificar en v16.0, se muestran a todos los administradores
    if ($adminId) {
       $resultado['pendientes']['solicitudes'] = (int)$pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estatus = 'pendiente'")->fetchColumn();
    }

    // 3. Personal (Solo Sistemas Area 1)
    if ($cveArea == 1) {
        $resultado['pendientes']['personal'] = (int)$pdo->query("
            SELECT (
                (SELECT COUNT(*) FROM solicitudes_actualizacion_personal WHERE estatus = 'pendiente') + 
                (SELECT COUNT(*) FROM cat_personal WHERE perfil_en_revision = true)
            )
        ")->fetchColumn();
    }

    // 4. Equipos (Solo Sistemas Area 1)
    if ($cveArea == 1) {
        $resultado['pendientes']['equipos'] = (int)$pdo->query("SELECT COUNT(*) FROM sb_bienes WHERE estatus_alta = 'pendiente'")->fetchColumn();
    }

    // 5. Calendario (Solicitudes de espacio)
    // Por ahora se agrupan en solicitudes, pero se podría separar por tipo='agenda/espacio'
    
    // Cálculo de total
    $resultado['total'] = array_sum($resultado['pendientes']);

} catch (Throwable $e) {
    $resultado['ok'] = false;
    $resultado['error'] = $e->getMessage();
}

echo json_encode($resultado);
