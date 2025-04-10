<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_level':
        $level_id = (int)$_GET['level_id'];
        
        // Get level data
        $stmt = $conn->prepare("SELECT * FROM wordscapes_levels WHERE level_id = ?");
        $stmt->execute([$level_id]);
        $level = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$level) {
            http_response_code(404);
            echo json_encode(['error' => 'Level not found']);
            exit();
        }
        
        // Get words for the level
        $stmt = $conn->prepare("SELECT word FROM wordscapes_words WHERE level_id = ? ORDER BY word");
        $stmt->execute([$level_id]);
        $level['words'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode($level);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
