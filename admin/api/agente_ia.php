<?php
/**
 * COMECyT Control de Solicitudes
 * Endpoint: Agente IA — Groq (LLaMA 3)
 *
 * POST: { mensaje, historial[], pagina, csrf_token }
 * GET:  { ping } → {"ok":true}
 */

require_once __DIR__ . '/../../config/database.php'; // carga .env → $_ENV
require_once __DIR__ . '/../../config/auth.php';

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

// ── Ollama Local ─────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/ai_config.php';

$ollamaUrl   = 'http://host.docker.internal:11434/api/chat'; // Cambiado a /chat para mejor manejo de mensajes
$ollamaModel = defined('OLLAMA_MODEL') ? OLLAMA_MODEL : 'qwen2.5-coder:1.5b';

// ── Historial ────────────────────────────────────────────────────────
$histRaw = json_decode($histJson, true);
if (!is_array($histRaw)) $histRaw = [];
$histRaw = array_slice($histRaw, -10);

$messages = [];
// Prompt Sistema
$systemPrompt = "Eres el Asistente Oficial del sistema COMECyT Control de Solicitudes. Responde en español, de forma concisa. Pagina actual: " . $paginaCtx;
$messages[] = ['role' => 'system', 'content' => $systemPrompt];

foreach ($histRaw as $turn) {
    $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $messages[] = ['role' => $role, 'content' => $turn['content'] ?? ''];
}
$messages[] = ['role' => 'user', 'content' => $mensaje];

$payload = json_encode([
    'model'    => $ollamaModel,
    'messages' => $messages,
    'stream'   => false,
    'options'  => [
        'temperature' => 0.6,
        'num_predict' => 800
    ]
]);

$ch = curl_init($ollamaUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);

$rawResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo conectar con Ollama Local. Asegúrate de que el contenedor ai_ollama esté corriendo.']);
    exit;
}

$data = json_decode($rawResponse, true);
$respuesta = $data['message']['content'] ?? 'Error al procesar respuesta local.';

echo json_encode([
    'ok'        => true,
    'respuesta' => $respuesta,
    'modelo'    => $ollamaModel,
    'source'    => 'local'
]);
