<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Validate input
if (!isset($_POST['question']) || !isset($_POST['answer'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$questionIndex = (int)$_POST['question'];
$answer = $_POST['answer'];

// Validate question index
if (!isset($_SESSION['quiz_questions']) || $questionIndex < 0 || $questionIndex >= count($_SESSION['quiz_questions'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid question index']);
    exit();
}

// Store answer in session
$_SESSION['quiz_answers'][$questionIndex] = $answer;

echo json_encode(['success' => true]); 