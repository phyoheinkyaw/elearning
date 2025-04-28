<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate input
if (!isset($_POST['question']) || !isset($_POST['answer']) || !isset($_POST['quiz_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$quiz_id = (int)$_POST['quiz_id'];
$question_id = (int)$_POST['question'];
$answer = $_POST['answer'];

// Find in-progress attempt
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND status = 0");
$stmt->execute([$user_id, $quiz_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    // Create a new attempt if none exists (user resumed quiz after session expired)
    $stmt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, score, status) VALUES (?, ?, 0, 0)");
    $stmt->execute([$quiz_id, $user_id]);
    $attempt_id = $conn->lastInsertId();
} else {
    $attempt_id = $attempt['attempt_id'];
}

// Upsert the answer
$stmt = $conn->prepare("INSERT INTO quiz_answers (attempt_id, user_id, quiz_id, question_id, answer) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer = VALUES(answer)");
$stmt->execute([$attempt_id, $user_id, $quiz_id, $question_id, $answer]);

echo json_encode(['success' => true]);