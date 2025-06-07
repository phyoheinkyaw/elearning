<?php
session_start();
require_once '../includes/db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Get the query parameter
$query = isset($_GET['query']) ? sanitize_input($_GET['query']) : '';

// Validate input
if (empty($query) || strlen($query) < 2) {
    echo json_encode(['suggestions' => []]);
    exit();
}

// Use DataMuse API to get word suggestions
// This API provides word suggestions based on the query and doesn't require authentication
$url = "https://api.datamuse.com/sug?s=" . urlencode($query) . "&max=10";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    // If there's an error with the API call, return empty suggestions
    echo json_encode(['suggestions' => []]);
    curl_close($ch);
    exit();
}

curl_close($ch);

// Parse response
$result = json_decode($response, true);

// Extract just the words from the response
$suggestions = [];
if (is_array($result)) {
    foreach ($result as $item) {
        if (isset($item['word'])) {
            $suggestions[] = $item['word'];
        }
    }
}

// Return JSON response
echo json_encode(['suggestions' => $suggestions]); 