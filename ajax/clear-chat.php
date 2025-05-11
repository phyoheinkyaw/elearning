<?php
// Turn off error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$user_id = $_SESSION['user_id'];
require_once '../includes/db.php';
try {
    $stmt = $conn->prepare("DELETE FROM chat_messages WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to clear messages.']);
}
