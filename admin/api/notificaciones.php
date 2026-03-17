<?php
/**
 * COMECyT Control de Solicitudes
 * API AJAX — Notificaciones en Tiempo Real
 *
 * Consulta en una sola petición:
 *   · Nuevos mensajes de chat (grupal) desde un ID conocido
 *   · Nuevas solicitudes desde un ID conocido
 *
 * GET /admin/api/notificaciones.php
 *   ?ultimo_chat=N       → ID del último mensaje de chat conocido
 *   ?ultima_solicitud=N  → ID de la última solicitud conocida
 *
 * Responde JSON:
 * {
 *   ok: true,
 *   chat: { count: N, ultimo_id: N, preview: "Texto..." },
 *   solicitudes: { count: N, ultimo_id: N, preview: "FOLIO · Tipo" }
 * }
 *
 * Solo accesible para administradores autenticados.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Iniciar sesión y verificar autenticación
inicializarSesion();
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'No autorizado']));
}

$pdo     = getConnection();
$adminId = (int) $_SESSION['admin_id'];

$ultimoChat       = (int) ($_GET['ultimo_chat']       ?? 0);
$ultimaSolicitud  = (int) ($_GET['ultima_solicitud']  ?? 0);

$resultado = [
    'ok'          => true,
    'chat'        => ['count' => 0, 'ultimo_id' => $ultimoChat,      'preview' => ''],
    'solicitudes' => ['count' => 0, 'ultimo_id' => $ultimaSolicitud, 'preview' => ''],
];

// ─────────────────────────────────────────────────────────────────────────────
// 1. Nuevos mensajes de chat (solo canal grupal, excluyendo los propios)
// ─────────────────────────────────────────────────────────────────────────────
try {
    $stmtChat = $pdo->prepare(
        "SELECT m.id,
                m.mensaje,
                m.tipo,
                a.nombre AS admin_nombre
         FROM sb_chat_mensajes m
         INNER JOIN administradores a ON a.id = m.admin_id
         WHERE m.id > :desde
           AND m.destinatario_id IS NULL
           AND m.admin_id <> :admin_id
         ORDER BY m.id ASC
         LIMIT 50"
    );
    $stmtChat->execute([':desde' => $ultimoChat, ':admin_id' => $adminId]);
    $msgChat = $stmtChat->fetchAll();

    if (!empty($msgChat)) {
        $ultimo   = end($msgChat);
        $count    = count($msgChat);
        $nombre   = mb_substr($ultimo['admin_nombre'], 0, 15);
        $texto    = $ultimo['tipo'] === 'texto'
                    ? mb_substr($ultimo['mensaje'], 0, 50)
                    : ($ultimo['tipo'] === 'tarea' ? '📋 Nueva tarea' : '📅 Nuevo evento');

        $resultado['chat'] = [
            'count'    => $count,
            'ultimo_id' => (int) $ultimo['id'],
            'preview'  => "{$nombre}: {$texto}",
        ];
    }
} catch (Throwable $e) {
    // No bloquear si falla chat
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Nuevas solicitudes
// ─────────────────────────────────────────────────────────────────────────────
try {
    $stmtSol = $pdo->prepare(
        "SELECT id, folio, tipo, solicitante, area
         FROM solicitudes
         WHERE id > :desde
         ORDER BY id ASC
         LIMIT 20"
    );
    $stmtSol->execute([':desde' => $ultimaSolicitud]);
    $solicitudes = $stmtSol->fetchAll();

    if (!empty($solicitudes)) {
        $ultima = end($solicitudes);
        $count  = count($solicitudes);
        $folio  = $ultima['folio'] ?? '—';
        $tipo   = mb_substr($ultima['tipo'] ?? 'solicitud', 0, 20);
        $who    = mb_substr($ultima['solicitante'] ?? '', 0, 20);

        $resultado['solicitudes'] = [
            'count'    => $count,
            'ultimo_id' => (int) $ultima['id'],
            'preview'  => "{$folio} · {$who}",
        ];
    }
} catch (Throwable $e) {
    // No bloquear si falla solicitudes
}

echo json_encode($resultado);
