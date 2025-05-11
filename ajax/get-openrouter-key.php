<?php
// Return the OpenRouter API key from the .env file as JSON (for frontend use)
header('Content-Type: application/json');

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Check if user is logged in - only return key to authenticated users
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Load .env
$envPath = realpath(__DIR__ . '/../.env');
$key = null;

if ($envPath && file_exists($envPath)) {
    try {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new Exception("Could not read .env file");
        }
        
        foreach ($lines as $line) {
            if (strpos(trim($line), 'OPENROUTER_API_KEY=') === 0) {
                $key = trim(substr($line, strlen('OPENROUTER_API_KEY=')));
                break;
            }
        }
        
        if ($key) {
            echo json_encode(['key' => $key]);
        } else {
            throw new Exception("API key not found in .env file");
        }
    } catch (Exception $e) {
        error_log("Error retrieving API key: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Could not retrieve API key']);
    }
} else {
    error_log("Error: .env file not found at: " . $envPath);
    http_response_code(500);
    echo json_encode(['error' => '.env file not found']);
}
