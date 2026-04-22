<?php
define('OLLAMA_URL', 'http://localhost:11434/api/chat');
define('OLLAMA_MODEL', 'llama3');
define('OLLAMA_TIMEOUT', 60);
function callOllama(string $systemPrompt, string $userMessage): ?string {
    $body = json_encode([
        'model'  => OLLAMA_MODEL,
        'stream' => false,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userMessage]
        ]
    ]);
    $ch = curl_init(OLLAMA_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => OLLAMA_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_FAILONERROR    => false,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $httpCode !== 200) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return $decoded['message']['content'] ?? null;
}
?>
