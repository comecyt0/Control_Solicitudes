<?php
/**
 * COMECyT Control de Solicitudes
 * Endpoint: Agente IA — Groq (LLaMA 3)
 *
 * POST: { mensaje, historial[], pagina, csrf_token }
 * GET:  { ping } → {"ok":true}
 */

require_once __DIR__ . '/../../../config/database.php'; // carga .env → $_ENV
require_once __DIR__ . '/../../../config/auth.php';

// ── Seguridad: solo admins ──────────────────────────────────────────
verificarSesionAdmin();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Ping de salud ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ping'])) {
    $keyConfigured = !empty($_ENV['GROQ_API_KEY']);
    echo json_encode(['ok' => true, 'api_key_set' => $keyConfigured]);
    exit;
}

// ── Solo POST de aquí en adelante ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// ── CSRF ────────────────────────────────────────────────────────────
$tokenRecibido = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $tokenRecibido)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

// ── Leer parámetros ─────────────────────────────────────────────────
$mensaje   = trim($_POST['mensaje'] ?? '');
$paginaCtx = trim($_POST['pagina'] ?? 'general');
$histJson  = $_POST['historial'] ?? '[]';

if ($mensaje === '') {
    echo json_encode(['ok' => false, 'error' => 'Mensaje vacío']);
    exit;
}

// Sanitizar longitud (configurable desde .env)
$maxChars = (int) ($_ENV['AI_MAX_CHARS'] ?? 800);
if (mb_strlen($mensaje) > $maxChars) {
    $mensaje = mb_substr($mensaje, 0, $maxChars);
}

// ── Groq API Key ─────────────────────────────────────────────────────
$apiKey = $_ENV['GROQ_API_KEY'] ?? '';
if (empty($apiKey)) {
    echo json_encode([
        'ok'       => false,
        'error'    => 'API key de Groq no configurada. Edita el archivo .env y agrega tu GROQ_API_KEY (gratis en https://console.groq.com).',
        'no_key'   => true,
    ]);
    exit;
}

// ── Historial ────────────────────────────────────────────────────────
$histRaw = json_decode($histJson, true);
if (!is_array($histRaw)) $histRaw = [];

// Limitar historial a últimas 10 rondas para no exceder tokens
$histRaw = array_slice($histRaw, -20);

// Construir mensajes en formato Groq
$messages = [];

// ── PROMPT SISTEMA ───────────────────────────────────────────────────
$systemPrompt = <<<PROMPT
Eres el Asistente Oficial del sistema COMECyT Control de Solicitudes, un panel administrativo interno del Colegio Mexiquense (COMECyT) para gestionar solicitudes de personal (becas, licencias, permisos, constancias, etc.).

REGLAS ESTRICTAS:
- Responde SIEMPRE en español, de forma clara, concisa y profesional.
- NO inventes datos numéricos ni estadísticas del sistema (no tienes acceso a la base de datos en tiempo real).
- Si no sabes algo, dilo honestamente y sugiere al admin que revise el módulo correspondiente.
- Mantén un tono amable pero formal.
- No hagas nada fuera del sistema COMECyT (no das recetas, noticias, etc.).

CONTEXTO DEL SISTEMA — LO QUE SABES:

MÓDULOS DISPONIBLES EN EL ADMIN:
1. Dashboard — Resumen general de solicitudes con estadísticas y gráficas. Muestra totales por estatus.
2. Todas las Solicitudes — Listado completo con filtros por estatus, convocatoria, área y búsqueda.
3. Pendientes — Solicitudes con estatus "pendiente" que esperan revisión.
4. En Proceso — Solicitudes activamente siendo tramitadas.
5. Completadas — Solicitudes finalizadas o rechazadas.
6. Administradores — Gestión de cuentas de administradores del sistema (crear, activar/desactivar).
7. Calendario — Agenda con vista mensual/semanal, tareas Kanban integradas con el chat del equipo.
8. Personal — Directorio del personal de COMECyT con datos de contacto y área.
9. Correos Oficiales — Gestión de correos institucionales asignados al personal.
10. Control de Equipos — Inventario de equipos tecnológicos asignados al personal.
11. Vista Pública (enlace externo) — Portal donde usuarios externos consultan y envían solicitudes.

ESTATUS DE SOLICITUDES:
- pendiente → recién recibida, sin revisión
- en_proceso → en trámite por el administrador
- completada → resuelta/aprobada
- rechazada → no procedió

ROLES EN EL SISTEMA:
- Administrador: acceso total al panel admin
- Usuario externo (personal): solo ve sus propias solicitudes en el portal público

CHAT DEL EQUIPO TI:
- Chat grupal visible solo para administradores
- Permite crear tareas Kanban y eventos de calendario directamente desde el chat
- Badge de notificaciones en el header cuando hay mensajes no leídos

ACCIONES COMUNES QUE PUEDES EXPLICAR:
- Cómo cambiar el estatus de una solicitud (ir a detalle y seleccionar nuevo estatus)
- Cómo exportar solicitudes a CSV (botón en el listado de solicitudes)
- Cómo crear un administrador nuevo (módulo Administradores → botón "Nuevo")
- Cómo usar el chat del equipo (ícono de chat en el header)
- Cómo agendar eventos desde el calendario o desde el chat
- Cómo filtrar solicitudes por convocatoria o área

PÁGINA ACTUAL DEL ADMIN: {PAGINA_ACTUAL}

Responde de forma útil según el contexto de la página actual cuando sea relevante.
PROMPT;

$systemPrompt = str_replace('{PAGINA_ACTUAL}', htmlspecialchars($paginaCtx, ENT_QUOTES), $systemPrompt);

$messages[] = ['role' => 'system', 'content' => $systemPrompt];

// Agregar historial anterior
foreach ($histRaw as $turn) {
    $role    = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = mb_substr(trim($turn['content'] ?? ''), 0, 500);
    if ($content !== '') {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}

// Mensaje actual del usuario
$messages[] = ['role' => 'user', 'content' => $mensaje];

// ── Llamada a Groq API ───────────────────────────────────────────────
$groqModel  = $_ENV['GROQ_MODEL']     ?? 'llama-3.1-8b-instant';
$maxTokens  = (int) ($_ENV['AI_MAX_TOKENS'] ?? 600);

$payload = json_encode([
    'model'       => $groqModel,
    'messages'    => $messages,
    'max_tokens'  => $maxTokens,
    'temperature' => 0.5,
    'stream'      => false,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$rawResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('[AGENTE_IA] cURL error: ' . $curlError);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión con el servicio de IA. Verifica tu conexión.']);
    exit;
}

$groqData = json_decode($rawResponse, true);

if ($httpCode !== 200 || !isset($groqData['choices'][0]['message']['content'])) {
    $groqError = $groqData['error']['message'] ?? 'Respuesta inesperada del servicio de IA.';
    error_log('[AGENTE_IA] Groq HTTP ' . $httpCode . ': ' . $groqError);

    // Error específico de API key inválida
    if ($httpCode === 401) {
        echo json_encode(['ok' => false, 'error' => 'API key inválida. Verifica tu GROQ_API_KEY en el archivo .env.', 'no_key' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'El servicio de IA no está disponible en este momento. Intenta de nuevo.']);
    }
    exit;
}

$respuesta = trim($groqData['choices'][0]['message']['content']);

echo json_encode([
    'ok'        => true,
    'respuesta' => $respuesta,
    'modelo'    => $groqData['model'] ?? 'llama-3.1-8b-instant',
]);
