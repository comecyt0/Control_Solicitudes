<?php
/**
 * COMECyT Control de Solicitudes
 * Servicio de Notificaciones por Email
 * Uso interno: incluir desde detalle.php/solicitudes.php al cambiar estatus
 *
 * Función principal: enviarNotificacionEstatus()
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Enviar email de notificación al solicitante cuando cambia el estatus de su solicitud.
 *
 * @param array  $solicitud   Fila completa de la tabla solicitudes
 * @param string $estatusNuevo
 * @param string $comentario  Comentario opcional del admin
 * @param string $adminNombre Nombre del admin que realizó el cambio
 * @return bool  true si se envió o está deshabilitado, false si falló
 */
function enviarNotificacionEstatus(array $solicitud, string $estatusNuevo, string $comentario, string $adminNombre): bool
{
    // Verificar si el mail está habilitado en .env
    $mailEnabled = filter_var($_ENV['MAIL_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    if (!$mailEnabled) {
        return true; // No falla, simplemente no envía
    }

    $emailDest = trim($solicitud['email_solicitante'] ?? '');
    if (empty($emailDest) || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
        return true; // Sin email válido, continuar sin notificar
    }

    $mailFrom     = $_ENV['MAIL_FROM']      ?? 'noreply@comecyt.gob.mx';
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'COMECyT Control de Solicitudes';

    // Mapeo de estatus a mensajes amigables
    $mensajesEstatus = [
        'pendiente'  => ['🕐 Solicitud Recibida',   'Su solicitud ha sido registrada y está en revisión.'],
        'en_proceso' => ['⚡ Solicitud En Proceso',  'Su solicitud está siendo atendida por nuestro equipo.'],
        'completada' => ['✅ Solicitud Completada',  'Su solicitud ha sido resuelta satisfactoriamente.'],
        'cancelada'  => ['❌ Solicitud Cancelada',   'Su solicitud ha sido cancelada. Por favor contacte al área de TI para más información.'],
    ];

    [$asunto, $textoEstatus] = $mensajesEstatus[$estatusNuevo] ?? ["Actualización de Solicitud", "Su solicitud ha sido actualizada."];

    $folio      = htmlspecialchars($solicitud['folio'] ?? '', ENT_QUOTES);
    $solicitante = htmlspecialchars($solicitud['solicitante'] ?? 'Estimado usuario', ENT_QUOTES);
    $comentHtml = $comentario ? '<p style="...' . htmlspecialchars($comentario, ENT_QUOTES) . '...</p>' : '';

    if (!empty($comentario)) {
        $comentHtml = '
        <div style="background:#fdf8f5; border-left:4px solid #B19A6D; padding:14px 18px; border-radius:0 8px 8px 0; margin:16px 0;">
            <p style="margin:0; font-size:0.85rem; color:#7d6535; font-weight:600; margin-bottom:4px;">Nota del equipo de TI:</p>
            <p style="margin:0; color:#374151; font-size:0.9rem;">' . htmlspecialchars($comentario, ENT_QUOTES) . '</p>
        </div>';
    }

    // Colores por estatus
    $coloresEstatus = [
        'pendiente'  => ['#D97706', '#FEF3C7'],
        'en_proceso' => ['#2563EB', '#EFF6FF'],
        'completada' => ['#16A34A', '#F0FDF4'],
        'cancelada'  => ['#DC2626', '#FEF2F2'],
    ];
    [$colorBorde, $colorFondo] = $coloresEstatus[$estatusNuevo] ?? ['#662331', '#fdf8f5'];

    $cuerpoHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Actualización de Solicitud — COMECyT</title>
</head>
<body style="margin:0;padding:0;background:#F3F4F6;font-family:Inter,'Segoe UI',system-ui,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F6;padding:40px 20px;">
    <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(102,35,49,0.12);">
            <!-- Header -->
            <tr><td style="background:linear-gradient(135deg,#662331,#8b2f42);padding:28px 32px;text-align:center;">
                <p style="color:rgba(255,255,255,0.8);margin:0;font-size:0.8rem;letter-spacing:0.1em;text-transform:uppercase;">Sistema de Control de Solicitudes</p>
                <h1 style="color:#B19A6D;margin:8px 0 0;font-size:1.5rem;font-weight:700;">COMECyT</h1>
            </td></tr>
            <!-- Estatus Badge -->
            <tr><td style="padding:28px 32px 0;">
                <div style="background:{$colorFondo};border:1px solid {$colorBorde};border-radius:10px;padding:16px 20px;text-align:center;margin-bottom:20px;">
                    <span style="font-size:1.1rem;font-weight:700;color:{$colorBorde};">{$asunto}</span>
                </div>
            </td></tr>
            <!-- Contenido -->
            <tr><td style="padding:0 32px 28px;">
                <p style="color:#374151;font-size:0.95rem;margin-bottom:16px;">Estimado/a <strong>{$solicitante}</strong>,</p>
                <p style="color:#374151;font-size:0.95rem;margin-bottom:16px;">{$textoEstatus}</p>
                <div style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:10px;padding:16px 20px;margin:16px 0;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr><td style="font-size:0.8rem;color:#6B7280;padding-bottom:4px;">Folio de Solicitud</td></tr>
                        <tr><td style="font-size:1.1rem;font-weight:700;color:#B19A6D;font-family:monospace;">{$folio}</td></tr>
                    </table>
                </div>
                {$comentHtml}
                <p style="color:#6B7280;font-size:0.82rem;margin-top:20px;">Atendido por: <strong style="color:#374151;">{$adminNombre}</strong></p>
            </td></tr>
            <!-- Footer -->
            <tr><td style="background:#F9FAFB;border-top:1px solid #E5E7EB;padding:18px 32px;text-align:center;">
                <p style="color:#9CA3AF;font-size:0.75rem;margin:0;">© 2026 COMECyT — Colegio Mexiquense, A.C. · Este es un mensaje automático, no responder.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
HTML;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($mailFromName) . "?= <{$mailFrom}>\r\n";
    $headers .= "X-Mailer: COMECyT-PHP/1.0\r\n";

    $enviado = @mail($emailDest, '=?UTF-8?B?' . base64_encode($asunto . ' — Folio ' . $solicitud['folio']) . '?=', $cuerpoHtml, $headers);

    // Registrar en log si la BD ya tiene la tabla
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO log_notificaciones (solicitud_id, destinatario, asunto, estatus_nuevo, enviado_ok)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$solicitud['id'], $emailDest, $asunto, $estatusNuevo, $enviado ? 1 : 0]);
    } catch (Throwable $e) {
        // Tabla aun no existe o error de BD — no romper el flujo
        error_log('[NOTIF_EMAIL] No se pudo registrar en log: ' . $e->getMessage());
    }

    if (!$enviado) {
        error_log("[NOTIF_EMAIL] Fallo al enviar a {$emailDest} para solicitud {$solicitud['folio']}");
    }

    return $enviado;
}
