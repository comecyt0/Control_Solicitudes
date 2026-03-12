#!/usr/bin/env php
<?php
/**
 * COMECyT Control de Solicitudes
 * CRON: Recordatorios de solicitudes sin atender
 *
 * Envía alertas a admins cuando una solicitud lleva más de X días en "pendiente" o "en_proceso".
 *
 * Configurar en crontab (correría cada día a las 8:00am):
 *   0 8 * * * /usr/bin/php /var/www/html/cron/recordatorios.php >> /var/log/comecyt_cron.log 2>&1
 *
 * Con Docker:
 *   make cron
 *
 * Ejecución manual con opciones:
 *   php cron/recordatorios.php
 *   php cron/recordatorios.php --dias=5 --dry-run
 */

define('ROOT', dirname(__DIR__));

// Opciones de línea de comandos (override de .env)
$opts   = getopt('', ['dias:', 'dry-run']);
$dias   = (int) ($opts['dias'] ?? ($_ENV['CRON_DIAS_UMBRAL'] ?? 3));
$dryRun = isset($opts['dry-run']);
if ($dias < 1) $dias = 3;

// Cargar .env manualmente (sin http context)
$envFile = ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

require_once ROOT . '/config/database.php';

// Log con timestamp
function logMsg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

logMsg("=== CRON Recordatorios COMECyT iniciado (días umbral: {$dias}) ===");

try {
    $pdo = getConnection();

    // Solicitudes pendientes o en_proceso con más de $dias días
    // DATE_PART en lugar de DATEDIFF (compatibilidad PostgreSQL)
    $stmt = $pdo->prepare(
        "SELECT s.id, s.folio, s.tipo, s.solicitante, s.area, s.estatus,
                s.fecha_creacion, s.email_solicitante,
                DATE_PART('day', NOW() - s.fecha_creacion)::INT AS dias_espera
         FROM solicitudes s
         WHERE s.estatus IN ('pendiente', 'en_proceso')
           AND DATE_PART('day', NOW() - s.fecha_creacion) >= ?
         ORDER BY dias_espera DESC"
    );
    $stmt->execute([$dias]);
    $pendientes = $stmt->fetchAll();

    logMsg("Solicitudes encontradas con >" . $dias . " días: " . count($pendientes));

    if (empty($pendientes)) {
        logMsg("Nada que alertar. Cron finalizado.");
        exit(0);
    }

    // Obtener admins con notificaciones habilitadas
    $stmtAdm = $pdo->query(
        "SELECT nombre, email FROM administradores WHERE activo = TRUE AND email_notif = TRUE"
    );
    $admins = $stmtAdm->fetchAll();

    if (empty($admins)) {
        logMsg("No hay administradores activos con notificaciones habilitadas.");
        exit(0);
    }

    $mailEnabled = filter_var($_ENV['MAIL_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    $mailFrom    = $_ENV['MAIL_FROM']      ?? 'noreply@comecyt.gob.mx';
    $mailFromName= $_ENV['MAIL_FROM_NAME'] ?? 'COMECyT';

    // Construir tabla de solicitudes para el email
    $filas = '';
    foreach ($pendientes as $s) {
        $filas .= "<tr>
            <td style='padding:6px 10px;border-bottom:1px solid #E5E7EB;font-family:monospace;color:#B19A6D;'>{$s['folio']}</td>
            <td style='padding:6px 10px;border-bottom:1px solid #E5E7EB;'>" . htmlspecialchars($s['solicitante'], ENT_QUOTES) . "</td>
            <td style='padding:6px 10px;border-bottom:1px solid #E5E7EB;'>" . htmlspecialchars($s['area'], ENT_QUOTES) . "</td>
            <td style='padding:6px 10px;border-bottom:1px solid #E5E7EB;font-weight:600;color:#D97706;'>{$s['dias_espera']} días</td>
            <td style='padding:6px 10px;border-bottom:1px solid #E5E7EB;'>" . htmlspecialchars($s['estatus'], ENT_QUOTES) . "</td>
        </tr>";
    }

    $total    = count($pendientes);
    $asunto   = "⏰ Recordatorio: {$total} solicitud" . ($total > 1 ? 'es' : '') . " sin actualizar — COMECyT";
    $cuerpo   = <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>
<body style="font-family:Inter,'Segoe UI',sans-serif;background:#F3F4F6;padding:30px;">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;margin:0 auto;box-shadow:0 4px 20px rgba(102,35,49,0.1);">
  <tr><td style="background:linear-gradient(135deg,#662331,#8b2f42);padding:24px 28px;color:#fff;">
    <h2 style="margin:0;font-size:1.1rem;">⏰ Recordatorio de Solicitudes</h2>
    <p style="margin:4px 0 0;font-size:0.8rem;opacity:0.8;">COMECyT — Control de Solicitudes Internas</p>
  </td></tr>
  <tr><td style="padding:24px 28px;">
    <p style="color:#374151;margin-bottom:16px;">Hola, te informamos que las siguientes solicitudes llevan <strong>más de {$dias} días</strong> sin actualización:</p>
    <div style="overflow-x:auto;">
    <table width="100%" style="border-collapse:collapse;font-size:0.85rem;">
      <thead><tr style="background:#F3F4F6;">
        <th style="padding:8px 10px;text-align:left;font-size:0.7rem;color:#6B7280;text-transform:uppercase;">Folio</th>
        <th style="padding:8px 10px;text-align:left;font-size:0.7rem;color:#6B7280;text-transform:uppercase;">Solicitante</th>
        <th style="padding:8px 10px;text-align:left;font-size:0.7rem;color:#6B7280;text-transform:uppercase;">Área</th>
        <th style="padding:8px 10px;text-align:left;font-size:0.7rem;color:#6B7280;text-transform:uppercase;">Espera</th>
        <th style="padding:8px 10px;text-align:left;font-size:0.7rem;color:#6B7280;text-transform:uppercase;">Estatus</th>
      </tr></thead>
      <tbody>{$filas}</tbody>
    </table>
    </div>
    <p style="color:#6B7280;font-size:0.8rem;margin-top:16px;">Por favor actualiza el estatus de cada solicitud desde el panel de administración.</p>
  </td></tr>
  <tr><td style="background:#F9FAFB;border-top:1px solid #E5E7EB;padding:14px 28px;text-align:center;font-size:0.72rem;color:#9CA3AF;">
    COMECyT © 2026 · Mensaje automático del sistema.
  </td></tr>
</table>
</body></html>
HTML;

    // Enviar a cada admin
    foreach ($admins as $adm) {
        if (!filter_var($adm['email'], FILTER_VALIDATE_EMAIL)) {
            logMsg("Email inválido para admin: {$adm['nombre']} — omitido");
            continue;
        }

        if ($dryRun) {
            logMsg("[DRY-RUN] Se enviaría recordatorio a: {$adm['nombre']} <{$adm['email']}>");
            continue;
        }

        if (!$mailEnabled) {
            logMsg("[MAIL DISABLED] No se envía a {$adm['email']}. Activa MAIL_ENABLED=true en .env");
            continue;
        }

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($mailFromName) . "?= <{$mailFrom}>\r\n";
        $headers .= "X-Mailer: COMECyT-Cron/1.0\r\n";

        $ok = @mail($adm['email'], '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $headers);
        logMsg(($ok ? "✓ Enviado" : "✗ Falló") . " → {$adm['nombre']} <{$adm['email']}>");
    }

} catch (Throwable $e) {
    logMsg("ERROR: " . $e->getMessage());
    exit(1);
}

logMsg("=== Cron finalizado ===");
exit(0);
