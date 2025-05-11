<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Check if attempt ID is provided
if (!isset($_GET['attempt_id'])) {
    header('Location: quizzes.php');
    exit();
}

$attempt_id = (int)$_GET['attempt_id'];
$user_id = $_SESSION['user_id'];

// Get quiz attempt details
$stmt = $conn->prepare("
    SELECT qa.*, q.title, q.description, qr.score, qr.taken_at as completed_at
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.quiz_id
    JOIN quiz_results qr ON qa.attempt_id = qr.attempt_id
    WHERE qa.attempt_id = ? AND qa.user_id = ?
");
$stmt->execute([$attempt_id, $user_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $_SESSION['error'] = "Quiz attempt not found.";
    header('Location: quizzes.php');
    exit();
}

// Get questions and answers
$stmt = $conn->prepare("
    SELECT qq.*, qa.answer
    FROM quiz_questions qq
    LEFT JOIN quiz_answers qa ON qq.question_id = qa.question_id AND qa.attempt_id = ?
    WHERE qq.quiz_id = ?
");
$stmt->execute([$attempt_id, $attempt['quiz_id']]);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo htmlspecialchars($attempt['title']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Floating Chatbot CSS -->
    <link href="css/floating-chatbot.css" rel="stylesheet">
    <!-- Search Autocomplete CSS -->
    <link href="css/search-autocomplete.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Quiz Results</h4>
                        <p class="mb-0"><?php echo htmlspecialchars($attempt['title']); ?></p>
                    </div>
                    <div class="card-body">
                        <!-- Score Summary -->
                        <div class="text-center mb-4">
                            <div class="display-4 mb-2">
                                <?php echo number_format($attempt['score'], 1); ?>%
                            </div>
                            <p class="text-muted">
                                Completed on <?php echo date('F j, Y g:i A', strtotime($attempt['completed_at'])); ?>
                            </p>
                        </div>

                        <!-- Questions Review -->
                        <h5 class="mb-3">Questions Review</h5>
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Question <?php echo $index + 1; ?></h6>
                                    <p class="card-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                    <?php if ($question['question_type'] === 0): // multiple_choice ?>
                                        <?php 
                                        $options = json_decode($question['options'], true);
                                        foreach ($options as $key => $option): 
                                            $isCorrect = $key === $question['correct_answer'];
                                            $isSelected = $key === $question['answer'];
                                            $bgClass = $isCorrect ? 'bg-success' : ($isSelected ? 'bg-danger' : '');
                                        ?>
                                            <div class="form-check mb-2 <?php echo $bgClass; ?> p-2 rounded">
                                                <input class="form-check-input" type="radio" 
                                                       <?php echo $isSelected ? 'checked' : ''; ?> disabled>
                                                <label class="form-check-label <?php echo $isCorrect || $isSelected ? 'text-white' : ''; ?>">
                                                    <?php echo htmlspecialchars($option); ?>
                                                    <?php if ($isCorrect): ?>
                                                        <i class="fas fa-check ms-2"></i>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php elseif ($question['question_type'] === 1): // matching ?>
                                        <?php 
                                        $pairs = json_decode($question['options'], true);
                                        $userAnswer = is_string($question['answer']) ? json_decode($question['answer'], true) : $question['answer'];
                                        $correctPairs = is_string($question['correct_answer']) ? json_decode($question['correct_answer'], true) : $question['correct_answer'];
                                        ?>
                                        <table class="table table-bordered">
                                            <thead><tr><th>Left</th><th>Your Match</th><th>Correct Match</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($pairs as $i => $pair): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($pair['left']); ?></td>
                                                    <td>
                                                        <?php
                                                        $userRight = isset($userAnswer[$i]) ? $userAnswer[$i] : null;
                                                        echo $userRight !== null && $userRight !== '' ? htmlspecialchars($userRight) : '<span class="text-danger">No answer</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php $correctRight = isset($correctPairs[$i]['right']) ? $correctPairs[$i]['right'] : null; echo $correctRight !== null ? htmlspecialchars($correctRight) : '-'; ?>
                                                        <?php if ($userRight !== null && $userRight !== '' && $correctRight !== null && $userRight === $correctRight): ?>
                                                            <i class="fas fa-check text-success ms-2"></i>
                                                        <?php elseif ($userRight !== null && $userRight !== ''): ?>
                                                            <i class="fas fa-times text-danger ms-2"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php elseif ($question['question_type'] === 2): // grammar/text input ?>
                                        <div class="mb-2">
                                            <strong>Your Answer:</strong><br>
                                            <?php echo isset($question['answer']) && $question['answer'] !== '' ? htmlspecialchars($question['answer']) : '<span class="text-danger">No answer</span>'; ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Correct Answer:</strong><br>
                                            <?php 
                                            $grammarData = json_decode($question['options'], true);
                                            $correct = isset($grammarData['correct']) ? $grammarData['correct'] : $question['correct_answer'];
                                            echo htmlspecialchars($correct);
                                            ?>
                                            <?php if (isset($question['answer']) && strtolower(trim($question['answer'])) === strtolower(trim($correct))): ?>
                                                <i class="fas fa-check text-success ms-2"></i>
                                            <?php elseif (isset($question['answer']) && $question['answer'] !== ''): ?>
                                                <i class="fas fa-times text-danger ms-2"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <a href="quizzes.php" class="btn btn-primary">
                                Back to Quizzes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>
</html>