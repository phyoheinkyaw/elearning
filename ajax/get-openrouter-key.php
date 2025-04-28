<?php
// Return the OpenRouter API key from the .env file as JSON (for frontend use)
header('Content-Type: application/json');

// Load .env
$envPath = realpath(__DIR__ . '/../.env');
$key = null;
if ($envPath && file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), 'OPENROUTER_API_KEY=') === 0) {
            $key = trim(substr($line, strlen('OPENROUTER_API_KEY=')));
            break;
        }
    }
}
if ($key) {
    echo json_encode(['key' => $key]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'API key not found']);
}
