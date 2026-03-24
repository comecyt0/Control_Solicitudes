<?php
/**
 * COMECyT Control de Solicitudes
 * API AJAX — Notificaciones en Tiempo Real
 *
 * Modos de operación:
 *
 * 1. ?init=1
 *    → Devuelve los IDs MÁXIMOS actuales de chat y solicitudes
 *      para establecer el baseline sin generar notificaciones.
 *      Llamar SOLO UNA VEZ al cargar la página.
 *
 * 2. ?ultimo_chat=N&ultima_solicitud=N  (polling normal)
 *    → Devuelve SOLO los registros que llegaron DESPUÉS de esos IDs.
 *      Si count > 0 → hay elementos nuevos que mostrar al admin.
 *
 * Responde JSON:
 * {
 *   ok: true,
 *   init: bool,
 *   chat:        { count, ultimo_id, preview },
 *   solicitudes: { count, ultimo_id, preview }
 * }
 *
 * Solo accesible para administradores autenticados.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

inicializarSesion();
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'No autorizado']));
}

$pdo     = getConnection();
$adminId = (int) $_SESSION['admin_id'];

$resultado = [
    'ok'          => true,
    'init'        => false,
    'chat'        => ['count' => 0, 'ultimo_id' => 0, 'preview' => ''],
    'solicitudes' => ['count' => 0, 'ultimo_id' => 0, 'preview' => ''],
];

// ─────────────────────────────────────────────────────────────────────────────
// MODO INIT: devuelve solo los IDs máximos actuales (sin contar como 'nuevos')
// Esto establece el baseline para el primer poll.
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['init'])) {
    $resultado['init'] = true;

    try {
        $maxChat = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM sb_chat_mensajes WHERE destinatario_id IS NULL")->fetchColumn();
        $resultado['chat']['ultimo_id'] = (int) $maxChat;
    } catch (Throwable $e) {}

    try {
        $maxSol = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM solicitudes")->fetchColumn();
        $resultado['solicitudes']['ultimo_id'] = (int) $maxSol;
    } catch (Throwable $e) {}

    echo json_encode($resultado);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// MODO POLLING: detectar solo registros nuevos desde los IDs conocidos
// ─────────────────────────────────────────────────────────────────────────────
$ultimoChat      = (int) ($_GET['ultimo_chat']      ?? 0);
$ultimaSolicitud = (int) ($_GET['ultima_solicitud'] ?? 0);

// Actualizar valores base en resultado
$resultado['chat']['ultimo_id']        = $ultimoChat;
$resultado['solicitudes']['ultimo_id'] = $ultimaSolicitud;

// ── 1. Nuevos mensajes de chat (canal grupal, excluyendo propios) ─────────────
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
         LIMIT 20"
    );
    $stmtChat->execute([':desde' => $ultimoChat, ':admin_id' => $adminId]);
    $msgChat = $stmtChat->fetchAll();

    if (!empty($msgChat)) {
        $ultimo  = end($msgChat);
        $count   = count($msgChat);
        $nombre  = mb_substr($ultimo['admin_nombre'], 0, 15);

        if ($ultimo['tipo'] === 'texto') {
            $texto = mb_substr($ultimo['mensaje'], 0, 50);
        } elseif ($ultimo['tipo'] === 'tarea') {
            $texto = '📋 ' . mb_substr($ultimo['ref_titulo'] ?? $ultimo['mensaje'], 0, 40);
        } elseif ($ultimo['tipo'] === 'evento') {
            $texto = '📅 ' . mb_substr($ultimo['ref_titulo'] ?? $ultimo['mensaje'], 0, 40);
        } else {
            $texto = mb_substr($ultimo['mensaje'], 0, 50);
        }

        $resultado['chat'] = [
            'count'     => $count,
            'ultimo_id' => (int) $ultimo['id'],
            'preview'   => "{$nombre}: {$texto}",
        ];
    }
} catch (Throwable $e) {
    error_log('[COMECyT Notif] Error chat: ' . $e->getMessage());
}

// ── 2. Nuevas solicitudes ──────────────────────────────────────────────────────
try {
    $stmtSol = $pdo->prepare(
        "SELECT id, folio, tipo, solicitante, area, fecha_creacion
         FROM solicitudes
         WHERE id > :desde
         ORDER BY id ASC
         LIMIT 10"
    );
    $stmtSol->execute([':desde' => $ultimaSolicitud]);
    $solicitudes = $stmtSol->fetchAll();

    if (!empty($solicitudes)) {
        $ultima = end($solicitudes);
        $count  = count($solicitudes);
        $folio  = $ultima['folio'] ?? '—';
        $who    = mb_substr($ultima['solicitante'] ?? '', 0, 25);

        $resultado['solicitudes'] = [
            'count'     => $count,
            'ultimo_id' => (int) $ultima['id'],
            'preview'   => "{$folio} · {$who}",
        ];
    }
} catch (Throwable $e) {
    error_log('[COMECyT Notif] Error solicitudes: ' . $e->getMessage());
}

echo json_encode($resultado);
