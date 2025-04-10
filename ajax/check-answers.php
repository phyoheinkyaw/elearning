<?php
session_start();
require_once '../includes/db.php';

if (!is_logged_in()) {
    echo json_encode(['complete' => false, 'message' => 'Not logged in']);
    exit();
}

if (!isset($_SESSION['test_answers']) || !isset($_SESSION['test_questions'])) {
    echo json_encode(['complete' => false, 'message' => 'No test in progress']);
    exit();
}

// Check if all questions have an answer
$complete = true;
foreach ($_SESSION['test_answers'] as $answer) {
    if (empty($answer)) {
        $complete = false;
        break;
    }
}

echo json_encode(['complete' => $complete]); 