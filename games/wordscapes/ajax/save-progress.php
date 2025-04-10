<?php
header('Content-Type: application/json');

require_once '../../../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Initialize error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['user_id']) || !isset($data['level_id']) || !isset($data['word'])) {
        throw new Exception('Invalid request data');
    }

    $user_id = (int)$data['user_id'];
    $level_id = (int)$data['level_id'];
    $word = strtolower($data['word']);

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('User not found');
    }

    // Check if word exists in this level
    $stmt = $conn->prepare("SELECT word FROM wordscapes_words WHERE level_id = ? AND LOWER(word) = ?");
    $stmt->execute([$level_id, $word]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid word for this level');
    }

    // Get existing progress
    $stmt = $conn->prepare("SELECT found_words FROM wordscapes_user_progress WHERE user_id = ? AND level_id = ?");
    $stmt->execute([$user_id, $level_id]);
    $progress = $stmt->fetch();

    // Update or insert progress
    $foundWords = $progress ? json_decode($progress['found_words'], true) : [];
    if (!in_array($word, $foundWords)) {
        $foundWords[] = $word;
        
        $foundWordsJson = json_encode($foundWords);
        
        if ($progress) {
            $stmt = $conn->prepare("UPDATE wordscapes_user_progress SET found_words = ? WHERE user_id = ? AND level_id = ?");
            $stmt->execute([$foundWordsJson, $user_id, $level_id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO wordscapes_user_progress (user_id, level_id, found_words) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $level_id, $foundWordsJson]);
        }
    }

    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'error' => 'An error occurred while saving your progress',
        'details' => $e->getMessage()
    ];
    echo json_encode($response);
    exit;
}
