<?php
/**
 * COMECyT Control de Solicitudes
 * API AJAX — Notificaciones para Solicitantes (v16.0)
 *
 * Devuelve el conteo de pendientes para el usuario loggeado:
 * - Mensajes de Chat (Seguimiento)
 * - Respuestas de Calendario (Aceptado/Rechazado)
 * - Cambios de Estatus en Solicitudes
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

inicializarSesion();
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'No autorizado']));
}

$pdo = getConnection();
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'];
$uid = $_SESSION['admin_id'] ? 'A'.$_SESSION['admin_id'] : 'P'.$_SESSION['user_id'];

$resultado = [
    'ok' => true,
    'pendientes' => [
        'chat'        => 0,
        'calendario'  => 0,
        'solicitudes' => 0,
    ],
    'total' => 0
];

try {
    // 1. Mensajes de Chat no leídos (DMs dirigidos a mi)
    // Usamos sb_chat_lectura para el solicitante (usuario_id)
    $stmtChat = $pdo->prepare("
        SELECT COUNT(*) 
        FROM sb_chat_mensajes 
        WHERE (destinatario_id = :uid OR destinatario_usuario_id = :userid)
        AND id > (
            SELECT COALESCE(MAX(ultimo_id_leido), 0) 
            FROM sb_chat_lectura 
            WHERE (admin_id = :adminid OR usuario_id = :userid2)
        )
        AND (admin_id <> :adminid2 OR admin_id IS NULL)
        AND (usuario_id <> :userid3 OR usuario_id IS NULL)
    ");
    $stmtChat->execute([
        ':uid'      => $uid,
        ':userid'   => $_SESSION['user_id'] ?? null,
        ':adminid'  => $_SESSION['admin_id'] ?? null,
        ':userid2'  => $_SESSION['user_id'] ?? null,
        ':adminid2' => $_SESSION['admin_id'] ?? null,
        ':userid3'  => $_SESSION['user_id'] ?? null
    ]);
    $resultado['pendientes']['chat'] = (int)$stmtChat->fetchColumn();

    // 2. Respuestas de Calendario (Buzón)
    $stmtCal = $pdo->prepare("
        SELECT COUNT(*) 
        FROM sb_calendario_solicitudes 
        WHERE usuario_id = ? AND leido_por_usuario = FALSE AND estatus IN ('aceptado', 'rechazado')
    ");
    $stmtCal->execute([$userId]);
    $resultado['pendientes']['calendario'] = (int)$stmtCal->fetchColumn();

    // 3. Cambios en Solicitudes (Aviso de estatus)
    // Se consideran "notificaciones" si el estatus no es 'pendiente' y se actualizó recientemente
    // (Asumimos que el usuario al entrar a consulta.php "lee" el estado)
    $stmtSol = $pdo->prepare("
        SELECT COUNT(*) 
        FROM solicitudes 
        WHERE (solicitante = ? OR solicitado_por = ?) 
        AND estatus <> 'pendiente' 
        AND fecha_actualizacion > (NOW() - INTERVAL '24 hours')
    ");
    $stmtSol->execute([$_SESSION['user_nombre'] ?? '', $userId]);
    $resultado['pendientes']['solicitudes'] = (int)$stmtSol->fetchColumn();

    $resultado['total'] = array_sum($resultado['pendientes']);

} catch (Throwable $e) {
    $resultado['ok'] = false;
    $resultado['error'] = $e->getMessage();
}

echo json_encode($resultado);
