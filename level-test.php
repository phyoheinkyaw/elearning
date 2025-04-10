<?php
session_start();
require_once 'includes/db.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get user's current level and last test date
try {
    $stmt = $conn->prepare("
        SELECT up.proficiency_level,
               (SELECT test_date 
                FROM level_test_results 
                WHERE user_id = ? 
                ORDER BY test_date DESC 
                LIMIT 1) as last_test_date
        FROM user_profiles up 
        WHERE up.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $profile = $stmt->fetch();
} catch(PDOException $e) {
    die('An error occurred.');
}

// Get test questions from all levels
try {
    // Get questions from each level
    $stmt = $conn->prepare("
        (SELECT * FROM level_test_questions WHERE difficulty_level = 'A1' ORDER BY RAND() LIMIT 4)
        UNION ALL
        (SELECT * FROM level_test_questions WHERE difficulty_level = 'A2' ORDER BY RAND() LIMIT 4)
        UNION ALL
        (SELECT * FROM level_test_questions WHERE difficulty_level = 'B1' ORDER BY RAND() LIMIT 5)
        UNION ALL
        (SELECT * FROM level_test_questions WHERE difficulty_level = 'B2' ORDER BY RAND() LIMIT 5)
        UNION ALL
        (SELECT * FROM level_test_questions WHERE difficulty_level = 'C1' ORDER BY RAND() LIMIT 4)
        UNION ALL
        (SELECT * FROM level_test_questions WHERE difficulty_level = 'C2' ORDER BY RAND() LIMIT 3)
        ORDER BY RAND()
    ");
    $stmt->execute();
    $questions = $stmt->fetchAll();
} catch(PDOException $e) {
    die('An error occurred.');
}

// Store questions in session if not already stored
if (!isset($_SESSION['test_questions'])) {
    $_SESSION['test_questions'] = $questions;
    $_SESSION['current_question'] = 0;
    $_SESSION['test_answers'] = array_fill(0, count($questions), '');
}

$current = isset($_GET['q']) ? (int)$_GET['q'] : $_SESSION['current_question'];
$current = max(0, min($current, count($_SESSION['test_questions']) - 1));
$_SESSION['current_question'] = $current;

$question = $_SESSION['test_questions'][$current];
$progress = ($current + 1) * 4; // 4% per question for 25 questions
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level Test - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <style>
        .question-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--white);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
            z-index: 1000;
        }
        .option-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .option-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }
        .option-card.selected {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            border-color: #0d6efd !important;
        }
        .option-card.selected .badge {
            background-color: #ffffff !important;
            color: #0d6efd !important;
        }
        .progress {
            height: 0.5rem;
        }
    </style>
</head>

<body class="pb-5">
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (isset($_SESSION['registration_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        Registration successful! Please complete the level test to determine your proficiency level.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['registration_success']); ?>
                <?php endif; ?>

                <?php if ($profile && !empty($profile['proficiency_level'])): ?>
                    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                        Your current level is <strong><?php echo htmlspecialchars($profile['proficiency_level']); ?></strong>
                        <?php if (!empty($profile['last_test_date'])): ?>
                            (Last test taken: <?php echo date('F j, Y h:i A', strtotime($profile['last_test_date'])); ?>)
                        <?php endif; ?>
                        <br>
                        Taking this test again will update your proficiency level.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title">English Level Test</h4>
                            <span class="badge bg-primary">Question <?php echo $current + 1; ?>/25</span>
                        </div>

                        <div class="progress mb-4">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?php echo $progress; ?>%" 
                                 aria-valuenow="<?php echo $progress; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>

                        <div class="question-container mb-4">
                            <p class="h5 mb-4"><?php echo htmlspecialchars($question['question_text']); ?></p>
                            
                            <div class="options">
                                <?php
                                $options = ['A' => $question['option_a'], 
                                          'B' => $question['option_b'], 
                                          'C' => $question['option_c'], 
                                          'D' => $question['option_d']];
                                foreach ($options as $key => $value):
                                    $isSelected = isset($_SESSION['test_answers'][$current]) && $_SESSION['test_answers'][$current] === $key;
                                ?>
                                    <div class="card option-card mb-3 <?php echo $isSelected ? 'selected' : ''; ?>" 
                                         data-answer="<?php echo $key; ?>"
                                         onclick="selectAnswer('<?php echo $key; ?>')">
                                        <div class="card-body py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <span class="badge rounded-pill bg-light text-dark"><?php echo $key; ?></span>
                                                </div>
                                                <div><?php echo htmlspecialchars($value); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="question-nav">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <?php if ($current > 0): ?>
                        <a href="?q=<?php echo $current - 1; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col text-center">
                    <span class="text-muted">Question <?php echo $current + 1; ?> of 25</span>
                </div>
                <div class="col text-end">
                    <?php if ($current < 24): ?>
                        <a href="?q=<?php echo $current + 1; ?>" class="btn btn-primary" id="nextBtn">
                            Next<i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-success" id="submitBtn" onclick="submitTest()">
                            Submit Test<i class="fas fa-check ms-2"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function selectAnswer(answer) {
            // Update UI immediately
            const cards = document.querySelectorAll('.option-card');
            cards.forEach(card => {
                if (card.dataset.answer === answer) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });

            // Save answer to server
            fetch('ajax/save-answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `question=${<?php echo $current; ?>}&answer=${answer}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to save answer');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function submitTest() {
            // Check if current page has an answer
            const hasCurrentAnswer = document.querySelector('.option-card.selected') !== null;
            if (!hasCurrentAnswer) {
                alert('Please select an answer for the current question before submitting.');
                return;
            }

            // Check if all questions are answered
            fetch('ajax/check-answers.php')
            .then(response => response.json())
            .then(data => {
                if (data.complete) {
                    window.location.href = 'level-test-result.php';
                } else {
                    alert('Please answer all questions before submitting the test.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Initialize selected answers when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const selectedAnswer = '<?php echo isset($_SESSION['test_answers'][$current]) ? $_SESSION['test_answers'][$current] : ''; ?>';
            if (selectedAnswer) {
                const selectedCard = document.querySelector(`.option-card[data-answer="${selectedAnswer}"]`);
                if (selectedCard) {
                    selectedCard.classList.add('selected');
                }
            }
        });
    </script>
</body>

</html> 