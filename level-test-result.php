<?php
session_start();
require_once 'includes/db.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Check if test was completed
if (!isset($_SESSION['test_answers']) || !isset($_SESSION['test_questions'])) {
    header('Location: level-test.php');
    exit();
}

try {
    // Calculate score
    $total_questions = count($_SESSION['test_questions']);
    $score = 0;
    $level_scores = [
        'A1' => ['correct' => 0, 'total' => 0],
        'A2' => ['correct' => 0, 'total' => 0],
        'B1' => ['correct' => 0, 'total' => 0],
        'B2' => ['correct' => 0, 'total' => 0],
        'C1' => ['correct' => 0, 'total' => 0],
        'C2' => ['correct' => 0, 'total' => 0]
    ];

    foreach ($_SESSION['test_questions'] as $index => $question) {
        $level = $question['difficulty_level'];
        $level_scores[$level]['total']++;
        
        if ($_SESSION['test_answers'][$index] === $question['correct_answer']) {
            $score++;
            $level_scores[$level]['correct']++;
        }
    }

    // Calculate percentages for each level
    $level_percentages = [];
    foreach ($level_scores as $level => $scores) {
        $level_percentages[$level] = $scores['total'] > 0 ? 
            ($scores['correct'] / $scores['total']) * 100 : 0;
    }

    // Determine level based on scores
    // Need at least 60% in current level and 50% in next level to advance
    $assigned_level = 'A1';
    $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
    
    for ($i = 0; $i < count($levels) - 1; $i++) {
        $current_level = $levels[$i];
        $next_level = $levels[$i + 1];
        
        if ($level_percentages[$current_level] >= 60 && $level_percentages[$next_level] >= 50) {
            $assigned_level = $next_level;
        } else {
            break;
        }
    }

    // Calculate total percentage
    $total_percentage = ($score / $total_questions) * 100;

    // Begin transaction
    $conn->beginTransaction();

    // Store result in database
    $stmt = $conn->prepare("INSERT INTO level_test_results (user_id, score, assigned_level) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $score, $assigned_level]);

    // Update user profile with the assigned level
    $stmt = $conn->prepare("UPDATE user_profiles SET proficiency_level = ? WHERE user_id = ?");
    $stmt->execute([$assigned_level, $_SESSION['user_id']]);

    // Commit transaction
    $conn->commit();

    // Clear test session data
    unset($_SESSION['test_questions']);
    unset($_SESSION['test_answers']);
    unset($_SESSION['current_question']);

} catch(PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    die('An error occurred while saving your results.');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results - ELearning</title>
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
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-5">
                            <div class="display-4 mb-3">
                                <i class="fas fa-award text-primary"></i>
                            </div>
                            <h1 class="h2 mb-3">Your Level Test Results</h1>
                            <p class="lead text-muted">Based on your performance, we've determined your English proficiency level.</p>
                        </div>

                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Overall Score</h5>
                                        <div class="display-4 mb-2"><?php echo round($total_percentage); ?>%</div>
                                        <p class="text-muted"><?php echo $score; ?> out of <?php echo $total_questions; ?> correct</p>
                                    </div>
                                </div>
                            </div>
                            <?php foreach ($levels as $level): ?>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <h5 class="card-title"><?php echo $level; ?> Level</h5>
                                        <div class="display-4 mb-2"><?php echo round($level_percentages[$level]); ?>%</div>
                                        <p class="text-muted">
                                            <?php echo $level_scores[$level]['correct']; ?> out of 
                                            <?php echo $level_scores[$level]['total']; ?> correct
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="text-center mb-5">
                            <div class="display-1 mb-3">
                                <span class="badge bg-primary"><?php echo $assigned_level; ?></span>
                            </div>
                            <h3 class="mb-4">Your Assigned Level: <?php echo $assigned_level; ?></h3>
                            <p class="lead">
                                <?php
                                $level_descriptions = [
                                    'A1' => 'You are at the beginner level. You can understand and use familiar everyday expressions and basic phrases.',
                                    'A2' => 'You are at the elementary level. You can communicate in simple and routine tasks requiring a direct exchange of information.',
                                    'B1' => 'You are at the intermediate level. You can deal with most situations likely to arise while travelling in an area where English is spoken.',
                                    'B2' => 'You are at the upper intermediate level. You can interact with a degree of fluency and spontaneity that makes regular interaction with native speakers quite possible.',
                                    'C1' => 'You are at the advanced level. You can express ideas fluently and spontaneously without much obvious searching for expressions.',
                                    'C2' => 'You are at the mastery level. You can understand with ease virtually everything heard or read.'
                                ];
                                echo $level_descriptions[$assigned_level];
                                ?>
                            </p>
                        </div>

                        <div class="text-center">
                            <a href="index.php" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-home me-2"></i>Go to Homepage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="js/lib/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html> 
</html> 