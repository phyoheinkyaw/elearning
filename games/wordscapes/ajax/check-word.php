<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $word = isset($_POST['word']) ? trim($_POST['word']) : '';
    $level_id = isset($_POST['level_id']) ? (int)$_POST['level_id'] : 0;

    if (empty($word) || $level_id <= 0) {
        echo json_encode(['valid' => false, 'message' => 'Invalid word or level']);
        exit;
    }

    // Get level data
    $stmt = $conn->prepare("SELECT given_letters FROM wordscapes_levels WHERE level_id = ?");
    $stmt->execute([$level_id]);
    $level = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$level) {
        echo json_encode(['valid' => false, 'message' => 'Level not found']);
        exit;
    }

    // Get valid words for this level
    $stmt = $conn->prepare("SELECT word FROM wordscapes_words WHERE level_id = ?");
    $stmt->execute([$level_id]);
    $valid_words = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Log level data
    error_log("Level data: level_id={$level_id}, given_letters={$level['given_letters']}");
    error_log("Valid words: " . implode(', ', $valid_words));
    error_log("Submitted word: {$word}");

    // Check if word is in the list of valid words (case-insensitive)
    $valid = false;
    foreach ($valid_words as $valid_word) {
        if (strtolower(trim($word)) === strtolower(trim($valid_word))) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        error_log("Word validation failed: Word '{$word}' not found in valid words");
        echo json_encode([
            'valid' => false,
            'message' => 'Not a valid word for this level'
        ]);
        exit;
    }

    // Get the given letters from the level
    $given_letters = str_split($level['given_letters']);
    $available_letters = array_unique($given_letters);

    // Log available letters
    error_log("Available letters: " . implode(', ', $available_letters));

    // Check if all letters in the word are available
    $word_letters = str_split($word);
    $unique_word_letters = array_unique($word_letters);

    foreach ($unique_word_letters as $letter) {
        if (!in_array($letter, $available_letters)) {
            error_log("Word validation failed: Letter '{$letter}' not available");
            echo json_encode([
                'valid' => false,
                'message' => 'Not all letters are available'
            ]);
            exit;
        }
    }

    // Word is valid
    error_log("Word validation success: Word '{$word}' is valid");
    echo json_encode([
        'valid' => true,
        'message' => 'Valid word'
    ]);

} catch (Exception $e) {
    error_log("Error checking word: " . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'message' => 'An error occurred while checking the word'
    ]);
}
?>
