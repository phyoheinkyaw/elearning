<?php
session_start();
require_once '../includes/db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Get the word parameter
$word = isset($_GET['word']) ? sanitize_input($_GET['word']) : '';

// Validate input
if (empty($word)) {
    echo json_encode(['success' => false, 'message' => 'No word provided']);
    exit();
}

// URL to fetch from Dictionary API
$url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . urlencode($word);

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Execute the request
$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check for success (status code 200)
if ($status_code === 200 && $response) {
    $data = json_decode($response, true);
    
    // Validate the response data
    if (is_array($data) && !empty($data)) {
        echo json_encode([
            'success' => true, 
            'entries' => $data
        ]);
        exit();
    }
}

// If we get here, the word wasn't found or there was an error
echo json_encode([
    'success' => false, 
    'message' => 'Word not found or API error',
    'status_code' => $status_code
]); 