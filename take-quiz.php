<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Check if quiz ID is provided
if (!isset($_GET['id'])) {
    header('Location: quizzes.php');
    exit();
}

$quiz_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get quiz details
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    $_SESSION['error'] = "Quiz not found.";
    header('Location: quizzes.php');
    exit();
}

// Check if user has already completed this quiz recently
$stmt = $conn->prepare("SELECT * FROM quiz_attempts 
                       WHERE quiz_id = ? AND user_id = ? 
                       AND completion_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute([$quiz_id, $user_id]);
if ($stmt->fetch()) {
    $_SESSION['error'] = "You have already taken this quiz in the last 24 hours.";
    header('Location: quizzes.php');
    exit();
}

// Get quiz questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Store questions in session if not already stored
if (!isset($_SESSION['quiz_questions'])) {
    $_SESSION['quiz_questions'] = $questions;
    $_SESSION['current_question'] = 0;
    $_SESSION['quiz_answers'] = array_fill(0, count($questions), '');
}

$current = isset($_GET['q']) ? (int)$_GET['q'] : $_SESSION['current_question'];
$current = max(0, min($current, count($_SESSION['quiz_questions']) - 1));
$_SESSION['current_question'] = $current;

$question = $_SESSION['quiz_questions'][$current];
$progress = ($current + 1) * (100 / count($questions));

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        $conn->beginTransaction();

        // Create quiz attempt
        $stmt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, score) VALUES (?, ?, 0)");
        $stmt->execute([$quiz_id, $user_id]);
        $attempt_id = $conn->lastInsertId();

        $total_questions = count($_SESSION['quiz_questions']);
        $correct_answers = 0;

        // Process each answer
        foreach ($_SESSION['quiz_questions'] as $index => $question) {
            $answer = $_SESSION['quiz_answers'][$index];
            
            // Skip empty answers
            if (empty($answer)) {
                continue;
            }

            // Record answer
            $stmt = $conn->prepare("INSERT INTO quiz_answers (attempt_id, question_id, answer) VALUES (?, ?, ?)");
            $stmt->execute([$attempt_id, $question['question_id'], $answer]);

            // Check if answer is correct based on question type
            if ($question['question_type'] === 0) { // multiple choice
                if ($answer === $question['correct_answer']) {
                    $correct_answers++;
                }
            } elseif ($question['question_type'] === 1) { // matching
                $matchingPairs = json_decode($question['options'], true);
                $userAnswer = json_decode($answer, true);
                if ($userAnswer && isset($userAnswer['left']) && isset($userAnswer['right'])) {
                    $leftIndex = $userAnswer['left'];
                    $rightIndex = $userAnswer['right'];
                    if ($matchingPairs[$leftIndex]['right'] === $matchingPairs[$rightIndex]['right']) {
                        $correct_answers++;
                    }
                }
            } elseif ($question['question_type'] === 2) { // grammar
                $grammarData = json_decode($question['options'], true);
                if (strtolower(trim($answer)) === strtolower(trim($grammarData['correct']))) {
                    $correct_answers++;
                }
            }
        }

        // Calculate score
        $score = ($correct_answers / $total_questions) * 100;

        // Update quiz attempt score
        $stmt = $conn->prepare("UPDATE quiz_attempts SET score = ? WHERE attempt_id = ?");
        $stmt->execute([$score, $attempt_id]);

        // Record quiz result
        $stmt = $conn->prepare("INSERT INTO quiz_results (quiz_id, user_id, attempt_id, score) VALUES (?, ?, ?, ?)");
        $stmt->execute([$quiz_id, $user_id, $attempt_id, $score]);

        $conn->commit();

        // Clear session data
        unset($_SESSION['quiz_questions']);
        unset($_SESSION['current_question']);
        unset($_SESSION['quiz_answers']);

        // Redirect to results page
        header("Location: quiz-results.php?attempt_id=" . $attempt_id);
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error submitting quiz: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - Quiz</title>
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
        .matching-container {
            position: relative;
        }
        .matching-item {
            cursor: move;
            position: relative;
            z-index: 1;
        }
        .matching-item.dragging {
            opacity: 0.5;
            z-index: 2;
        }
        .matching-item.matched {
            cursor: default;
        }
        .matching-item.matched .card {
            border-color: #198754;
            background-color: #f8fff9;
        }
        .connection-line {
            position: absolute;
            height: 2px;
            background-color: #0d6efd;
            transform-origin: left center;
            pointer-events: none;
            z-index: 0;
        }
        .matching-connections {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
        }
    </style>
</head>
<body class="pb-5">
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h4>
                            <span class="badge bg-primary">Question <?php echo $current + 1; ?>/<?php echo count($questions); ?></span>
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
                            
                            <?php if ($question['question_type'] === 0): // multiple_choice ?>
                                <div class="options">
                                    <?php 
                                    $options = json_decode($question['options'], true);
                                    foreach ($options as $key => $option): 
                                        $isSelected = isset($_SESSION['quiz_answers'][$current]) && $_SESSION['quiz_answers'][$current] === $key;
                                    ?>
                                        <div class="card option-card mb-3 <?php echo $isSelected ? 'selected' : ''; ?>" 
                                             data-answer="<?php echo $key; ?>"
                                             onclick="selectAnswer('<?php echo $key; ?>')">
                                            <div class="card-body py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <span class="badge rounded-pill bg-light text-dark"><?php echo $key; ?></span>
                                                    </div>
                                                    <div><?php echo htmlspecialchars($option); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($question['question_type'] === 1): // matching ?>
                                <div class="matching-container">
                                    <?php 
                                    $matchingPairs = json_decode($question['options'], true);
                                    $leftItems = array_column($matchingPairs, 'left');
                                    $rightItems = array_column($matchingPairs, 'right');
                                    shuffle($rightItems); // Randomize right column
                                    ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="matching-left">
                                                <?php foreach ($leftItems as $index => $left): ?>
                                                    <div class="matching-item" 
                                                         draggable="true"
                                                         data-index="<?php echo $index; ?>"
                                                         data-side="left">
                                                        <div class="card mb-3">
                                                            <div class="card-body">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="me-3">
                                                                        <span class="badge rounded-pill bg-primary"><?php echo $index + 1; ?></span>
                                                                    </div>
                                                                    <div><?php echo htmlspecialchars($left); ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="matching-right">
                                                <?php foreach ($rightItems as $index => $right): ?>
                                                    <div class="matching-item" 
                                                         draggable="true"
                                                         data-index="<?php echo $index; ?>"
                                                         data-side="right">
                                                        <div class="card mb-3">
                                                            <div class="card-body">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="me-3">
                                                                        <span class="badge rounded-pill bg-secondary"><?php echo $index + 1; ?></span>
                                                                    </div>
                                                                    <div><?php echo htmlspecialchars($right); ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="matching-connections mt-4">
                                        <?php for ($i = 0; $i < count($leftItems); $i++): ?>
                                            <div class="connection-line" data-left="<?php echo $i; ?>"></div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php elseif ($question['question_type'] === 2): // grammar ?>
                                <div class="grammar-container">
                                    <?php 
                                    $grammarData = json_decode($question['options'], true);
                                    $sentence = $grammarData['sentence'];
                                    $correct = $grammarData['correct'];
                                    ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <p class="mb-3"><?php echo htmlspecialchars($sentence); ?></p>
                                            <div class="form-group">
                                                <textarea class="form-control" 
                                                          rows="3" 
                                                          placeholder="Type your corrected sentence here..."
                                                          onchange="updateGrammarAnswer(this.value)"><?php echo isset($_SESSION['quiz_answers'][$current]) ? htmlspecialchars($_SESSION['quiz_answers'][$current]) : ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
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
                        <a href="?id=<?php echo $quiz_id; ?>&q=<?php echo $current - 1; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col text-center">
                    <span class="text-muted">Question <?php echo $current + 1; ?> of <?php echo count($questions); ?></span>
                </div>
                <div class="col text-end">
                    <?php if ($current < count($questions) - 1): ?>
                        <a href="?id=<?php echo $quiz_id; ?>&q=<?php echo $current + 1; ?>" class="btn btn-primary" id="nextBtn">
                            Next<i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="submit" class="btn btn-success" id="submitBtn">
                                Submit Quiz<i class="fas fa-check ms-2"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
            fetch('ajax/save-quiz-answer.php', {
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

        function updateMatchingAnswer(leftIndex, rightIndex) {
            const answer = {
                left: leftIndex,
                right: rightIndex
            };

            // Save answer to server
            fetch('ajax/save-quiz-answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `question=${<?php echo $current; ?>}&answer=${JSON.stringify(answer)}`
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

        function updateGrammarAnswer(answer) {
            // Save answer to server
            fetch('ajax/save-quiz-answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `question=${<?php echo $current; ?>}&answer=${encodeURIComponent(answer)}`
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

        // Initialize selected answers when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const selectedAnswer = <?php echo isset($_SESSION['quiz_answers'][$current]) ? json_encode($_SESSION['quiz_answers'][$current]) : 'null'; ?>;
            if (selectedAnswer) {
                if (<?php echo $question['question_type']; ?> === 0) { // multiple_choice
                    const selectedCard = document.querySelector(`.option-card[data-answer="${selectedAnswer}"]`);
                    if (selectedCard) {
                        selectedCard.classList.add('selected');
                    }
                } else if (<?php echo $question['question_type']; ?> === 1) { // matching
                    if (typeof selectedAnswer === 'object' && selectedAnswer.left !== undefined) {
                        const select = document.querySelector(`.matching-select[data-left="${selectedAnswer.left}"]`);
                        if (select) {
                            select.value = selectedAnswer.right;
                        }
                    }
                } else if (<?php echo $question['question_type']; ?> === 2) { // grammar
                    const textarea = document.querySelector('.grammar-container textarea');
                    if (textarea) {
                        textarea.value = selectedAnswer;
                    }
                }
            }
        });

        // Add matching drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const matchingContainer = document.querySelector('.matching-container');
            if (matchingContainer) {
                const items = document.querySelectorAll('.matching-item');
                const connections = document.querySelectorAll('.connection-line');
                let draggedItem = null;
                let draggedConnection = null;

                items.forEach(item => {
                    item.addEventListener('dragstart', handleDragStart);
                    item.addEventListener('dragend', handleDragEnd);
                    item.addEventListener('dragover', handleDragOver);
                    item.addEventListener('drop', handleDrop);
                });

                function handleDragStart(e) {
                    draggedItem = this;
                    this.classList.add('dragging');
                    e.dataTransfer.setData('text/plain', this.dataset.index);
                    e.dataTransfer.setData('side', this.dataset.side);
                }

                function handleDragEnd(e) {
                    this.classList.remove('dragging');
                    draggedItem = null;
                }

                function handleDragOver(e) {
                    e.preventDefault();
                    if (this.dataset.side !== draggedItem.dataset.side) {
                        this.classList.add('drag-over');
                    }
                }

                function handleDrop(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');

                    if (this.dataset.side === draggedItem.dataset.side) {
                        return;
                    }

                    const leftIndex = draggedItem.dataset.side === 'left' ? draggedItem.dataset.index : this.dataset.index;
                    const rightIndex = draggedItem.dataset.side === 'right' ? draggedItem.dataset.index : this.dataset.index;

                    // Update connection line
                    const connection = document.querySelector(`.connection-line[data-left="${leftIndex}"]`);
                    if (connection) {
                        const leftItem = document.querySelector(`.matching-item[data-side="left"][data-index="${leftIndex}"]`);
                        const rightItem = document.querySelector(`.matching-item[data-side="right"][data-index="${rightIndex}"]`);
                        
                        if (leftItem && rightItem) {
                            const leftRect = leftItem.getBoundingClientRect();
                            const rightRect = rightItem.getBoundingClientRect();
                            const containerRect = matchingContainer.getBoundingClientRect();

                            const leftX = leftRect.right - containerRect.left;
                            const leftY = leftRect.top + leftRect.height / 2 - containerRect.top;
                            const rightX = rightRect.left - containerRect.left;
                            const rightY = rightRect.top + rightRect.height / 2 - containerRect.top;

                            const length = Math.sqrt(Math.pow(rightX - leftX, 2) + Math.pow(rightY - leftY, 2));
                            const angle = Math.atan2(rightY - leftY, rightX - leftX) * 180 / Math.PI;

                            connection.style.width = `${length}px`;
                            connection.style.left = `${leftX}px`;
                            connection.style.top = `${leftY}px`;
                            connection.style.transform = `rotate(${angle}deg)`;
                            connection.style.display = 'block';

                            // Mark items as matched
                            leftItem.classList.add('matched');
                            rightItem.classList.add('matched');

                            // Save answer
                            const answer = {
                                left: parseInt(leftIndex),
                                right: parseInt(rightIndex)
                            };

                            fetch('ajax/save-quiz-answer.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `question=${<?php echo $current; ?>}&answer=${JSON.stringify(answer)}`
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
                    }
                }

                // Initialize existing matches
                const selectedAnswer = <?php echo isset($_SESSION['quiz_answers'][$current]) ? json_encode($_SESSION['quiz_answers'][$current]) : 'null'; ?>;
                if (selectedAnswer && typeof selectedAnswer === 'object' && selectedAnswer.left !== undefined) {
                    const leftItem = document.querySelector(`.matching-item[data-side="left"][data-index="${selectedAnswer.left}"]`);
                    const rightItem = document.querySelector(`.matching-item[data-side="right"][data-index="${selectedAnswer.right}"]`);
                    
                    if (leftItem && rightItem) {
                        leftItem.classList.add('matched');
                        rightItem.classList.add('matched');
                        
                        const connection = document.querySelector(`.connection-line[data-left="${selectedAnswer.left}"]`);
                        if (connection) {
                            const leftRect = leftItem.getBoundingClientRect();
                            const rightRect = rightItem.getBoundingClientRect();
                            const containerRect = matchingContainer.getBoundingClientRect();

                            const leftX = leftRect.right - containerRect.left;
                            const leftY = leftRect.top + leftRect.height / 2 - containerRect.top;
                            const rightX = rightRect.left - containerRect.left;
                            const rightY = rightRect.top + rightRect.height / 2 - containerRect.top;

                            const length = Math.sqrt(Math.pow(rightX - leftX, 2) + Math.pow(rightY - leftY, 2));
                            const angle = Math.atan2(rightY - leftY, rightX - leftX) * 180 / Math.PI;

                            connection.style.width = `${length}px`;
                            connection.style.left = `${leftX}px`;
                            connection.style.top = `${leftY}px`;
                            connection.style.transform = `rotate(${angle}deg)`;
                            connection.style.display = 'block';
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 