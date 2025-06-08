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
$unanswered = [];

// Loop through all questions and track unanswered ones
for ($i = 0; $i < count($_SESSION['test_questions']); $i++) {
    if (!isset($_SESSION['test_answers'][$i]) || empty($_SESSION['test_answers'][$i])) {
        $complete = false;
        $unanswered[] = $i;
    }
}

echo json_encode([
    'complete' => $complete, 
    'unanswered' => $unanswered
]); 