<?php
/**
 * Configuración y conector para la IA local (Ollama)
 * Permite integrar Qwen2.5-Coder o DeepSeek en el Intranet.
 */

// Usamos host.docker.internal para conectar desde el contenedor app al host (Ollama)
define('OLLAMA_URL', 'http://host.docker.internal:11434/api/generate');
define('OLLAMA_MODEL', 'qwen2.5-coder:1.5b');

function predecirTexto($prompt) {
    $data = [
        'model' => OLLAMA_MODEL,
        'prompt' => $prompt,
        'stream' => false
    ];

    $ch = curl_init(OLLAMA_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return "Error: " . curl_error($ch);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['response'] ?? 'Sin respuesta de la IA';
}
