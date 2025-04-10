<?php
session_start();
require_once '../includes/db.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if (!isset($_POST['question']) || !isset($_POST['answer'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$question = (int)$_POST['question'];
$answer = $_POST['answer'];

// Validate answer format
if (!in_array($answer, ['A', 'B', 'C', 'D'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid answer']);
    exit();
}

// Save answer in session
if (isset($_SESSION['test_answers']) && isset($_SESSION['test_questions'])) {
    if ($question >= 0 && $question < count($_SESSION['test_questions'])) {
        $_SESSION['test_answers'][$question] = $answer;
        echo json_encode(['success' => true]);
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid question']); 