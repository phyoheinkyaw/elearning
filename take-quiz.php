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
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? AND status = 1 AND completion_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute([$quiz_id, $user_id]);
if ($stmt->fetch()) {
    $_SESSION['error'] = "You have already taken this quiz in the last 24 hours.";
    header('Location: quizzes.php');
    exit();
}

// Find or create in-progress attempt
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND status = 0");
$stmt->execute([$user_id, $quiz_id]);
$attempt = $stmt->fetch();

if ($attempt) {
    $attempt_id = $attempt['attempt_id'];
} else {
    $stmt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, score, status) VALUES (?, ?, 0, 0)");
    $stmt->execute([$quiz_id, $user_id]);
    $attempt_id = $conn->lastInsertId();
}

// Load answers from DB
$stmt = $conn->prepare("SELECT question_id, answer FROM quiz_answers WHERE attempt_id = ?");
$stmt->execute([$attempt_id]);
$existing_answers = [];
while ($row = $stmt->fetch()) {
    $existing_answers[$row['question_id']] = $row['answer'];
}

// Get quiz questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Quiz intro/start screen logic
$show_intro = !isset($_GET['start']) && !isset($_GET['q']) && !isset($_POST['submit']);

// Ensure $current is always set
$current = isset($_GET['q']) ? (int)$_GET['q'] : 0;
$current = max(0, min($current, count($questions) - 1));

// Debug: show the whole session answers array
if (isset($existing_answers)) {
    echo '<div class="alert alert-warning"><b>Debug EXISTING_ANSWERS:</b> <pre>';
    var_export($existing_answers);
    echo '</pre></div>';
}

// For matching: robustly decode as array for restoration
function get_matching_saved_answers($question_id, $leftCount) {
    global $existing_answers;
    $rawSaved = isset($existing_answers[$question_id]) ? $existing_answers[$question_id] : '';
    // Always decode if string and not empty
    if (is_string($rawSaved) && $rawSaved !== '') {
        $decoded = json_decode($rawSaved, true);
        if (is_array($decoded)) {
            $savedAnswers = $decoded;
        } else {
            $savedAnswers = array_fill(0, $leftCount, '');
        }
    } elseif (is_array($rawSaved)) {
        $savedAnswers = $rawSaved;
    } else {
        $savedAnswers = array_fill(0, $leftCount, '');
    }
    if (count($savedAnswers) < $leftCount) {
        $savedAnswers = array_pad($savedAnswers, $leftCount, '');
    }
    return $savedAnswers;
}

if (!$show_intro) {
    $question = $questions[$current];
    $progress = ($current + 1) * (100 / count($questions));
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        $conn->beginTransaction();

        $total_questions = count($questions);
        $correct_answers = 0;
        $incomplete_index = -1;

        // Process each answer
        foreach ($questions as $index => $question) {
            $answer = isset($existing_answers[$question['question_id']]) ? $existing_answers[$question['question_id']] : '';
            // Check for incomplete answers
            if ($incomplete_index === -1) {
                if ($question['question_type'] === 0 || $question['question_type'] === 2) {
                    if (empty($answer)) {
                        $incomplete_index = $index;
                    }
                } elseif ($question['question_type'] === 1) {
                    $matchingPairs = json_decode($question['options'], true);
                    $leftCount = count($matchingPairs);
                    $savedAnswers = get_matching_saved_answers($question['question_id'], $leftCount);
                    if (in_array('', $savedAnswers, true)) {
                        $incomplete_index = $index;
                    }
                }
            }
            // Scoring logic (improved)
            if (!empty($answer)) {
                if ($question['question_type'] === 0) { // multiple choice
                    if ($answer === $question['correct_answer']) {
                        $correct_answers++;
                    }
                } elseif ($question['question_type'] === 1) { // matching
                    $matchingPairs = json_decode($question['options'], true);
                    $correctPairs = $question['correct_answer'];
                    if (is_string($correctPairs)) {
                        $correctPairs = json_decode($correctPairs, true);
                    }
                    $userAnswer = json_decode($answer, true);
                    $isAllCorrect = true;
                    if (is_array($userAnswer) && is_array($correctPairs) && count($userAnswer) === count($correctPairs)) {
                        foreach ($correctPairs as $leftIdx => $pair) {
                            $correctRight = $pair['right'];
                            $userRight = isset($userAnswer[$leftIdx]) ? $userAnswer[$leftIdx] : null;
                            if ($userRight !== $correctRight) {
                                $isAllCorrect = false;
                                break;
                            }
                        }
                        if ($isAllCorrect) {
                            $correct_answers++;
                        }
                    }
                } elseif ($question['question_type'] === 2) { // grammar/text input
                    $grammarData = json_decode($question['options'], true);
                    $correct = isset($grammarData['correct']) ? trim($grammarData['correct']) : trim($question['correct_answer']);
                    if (strtolower(trim($answer)) === strtolower($correct)) {
                        $correct_answers++;
                    }
                }
            }
        }

        // If incomplete, show error and redirect
        if ($incomplete_index !== -1) {
            $_SESSION['form_error'] = 'Please answer all questions before submitting.';
            header('Location: take-quiz.php?id=' . $quiz_id . '&q=' . $incomplete_index);
            exit();
        }

        // Calculate score
        $score = ($correct_answers / $total_questions) * 100;

        // Update quiz attempt score and status
        $stmt = $conn->prepare("UPDATE quiz_attempts SET score = ?, status = 1 WHERE attempt_id = ?");
        $stmt->execute([$score, $attempt_id]);

        // Record quiz result
        $stmt = $conn->prepare("INSERT INTO quiz_results (quiz_id, user_id, attempt_id, score) VALUES (?, ?, ?, ?)");
        $stmt->execute([$quiz_id, $user_id, $attempt_id, $score]);

        $conn->commit();

        // Redirect to results page
        header("Location: quiz-results.php?attempt_id=$attempt_id");
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['form_error'] = "Error submitting quiz: " . $e->getMessage();
    }
}

// Handle saving an answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_answer'])) {
    $question_id = $_POST['question_id'];
    $answer = $_POST['answer'];
    $stmt = $conn->prepare("INSERT INTO quiz_answers (attempt_id, user_id, quiz_id, question_id, answer) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer = VALUES(answer)");
    $stmt->execute([$attempt_id, $user_id, $quiz_id, $question_id, $answer]);
    exit;
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
                        <?php if ($show_intro): ?>
                            <h2 class="mb-3"><?php echo htmlspecialchars($quiz['title']); ?></h2>
                            <p class="mb-2 text-muted"><?php echo htmlspecialchars($quiz['description']); ?></p>
                            <ul class="list-group list-group-flush mb-4">
                                <li class="list-group-item"><strong>Questions:</strong> <?php echo count($questions); ?></li>
                                <li class="list-group-item"><strong>Difficulty:</strong> <?php echo ['Beginner','Intermediate','Advanced'][$quiz['difficulty_level']]; ?></li>
                            </ul>
                            <form method="get" action="">
                                <input type="hidden" name="id" value="<?php echo $quiz_id; ?>">
                                <input type="hidden" name="start" value="1">
                                <button type="submit" class="btn btn-primary btn-lg w-100">Start Quiz</button>
                            </form>
                        <?php else: ?>
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
                        <form method="post" action="" id="quizForm">
                            <?php if (isset($_SESSION['form_error'])): ?>
                                <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($_SESSION['form_error']); ?></div>
                                <?php unset($_SESSION['form_error']); ?>
                            <?php endif; ?>
                            <div class="question-container mb-4">
                                <p class="h5 mb-4"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                <?php if ($question['question_type'] === 0): // multiple_choice ?>
                                    <div class="options">
                                        <?php 
                                        $options = json_decode($question['options'], true);
                                        foreach ($options as $key => $option): 
                                            $isSelected = isset($existing_answers[$question['question_id']]) && $existing_answers[$question['question_id']] === $key;
                                        ?>
                                            <div class="card option-card mb-3 <?php echo $isSelected ? 'selected' : ''; ?>" 
                                                 data-answer="<?php echo $key; ?>"
                                                 onclick="window.selectAnswer('<?php echo $key; ?>')">
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
                                    <?php 
                                    $matchingPairs = json_decode($question['options'], true);
                                    $leftItems = array_column($matchingPairs, 'left');
                                    $rightItems = array_column($matchingPairs, 'right');
                                    shuffle($rightItems); // Randomize right column
                                    // If savedAnswers is a JSON string, decode it
                                    $savedAnswers = isset($existing_answers[$question['question_id']]) ? json_decode($existing_answers[$question['question_id']], true) : [];
                                    ?>
                                    <div class="matching-container">
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="table-responsive">
                                                    <table class="table align-middle mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Match</th>
                                                                <th>With</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($leftItems as $index => $left): ?>
                                                            <tr>
                                                                <td>
                                                                    <span class="badge rounded-pill bg-primary me-2"><?php echo $index + 1; ?></span>
                                                                    <?php echo htmlspecialchars($left); ?>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select matching-select" data-left="<?php echo $index; ?>" onchange="window.updateMatchingAnswer(<?php echo $index; ?>, this.value)">
                                                                        <option value="" selected>Select...</option>
                                                                        <?php foreach ($rightItems as $right): ?>
                                                                            <option value="<?php echo htmlspecialchars($right); ?>" <?php echo (isset($savedAnswers[$index]) && $savedAnswers[$index] !== '' && $savedAnswers[$index] == $right) ? 'selected' : ''; ?>><?php echo htmlspecialchars($right); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
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
                                                              onchange="window.updateGrammarAnswer(this.value)"><?php echo isset($existing_answers[$question['question_id']]) ? htmlspecialchars($existing_answers[$question['question_id']]) : ''; ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-secondary" onclick="window.goToQuestion(<?php echo $current - 1; ?>)" <?php if ($current == 0) echo 'disabled'; ?>>Previous</button>
                                <?php if ($current < count($questions) - 1): ?>
                                    <button type="button" class="btn btn-primary" onclick="window.goToQuestion(<?php echo $current + 1; ?>)">Next</button>
                                <?php else: ?>
                                    <button type="submit" name="submit" class="btn btn-success">Submit Quiz</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    // Always define all global quiz JS functions
    window.goToQuestion = function(q) {
        const url = new URL(window.location.href);
        url.searchParams.set('q', q);
        window.location.href = url.toString();
    };
    window.selectAnswer = function(answer) {
        const cards = document.querySelectorAll('.option-card');
        cards.forEach(card => {
            if (card.dataset.answer === answer) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
        fetch('ajax/save-quiz-answer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: "question=<?php echo $question['question_id']; ?>&answer=" + encodeURIComponent(answer) + "&quiz_id=<?php echo $quiz_id; ?>"
        });
    };
    window.matchingAnswers = <?php echo json_encode($question['question_type'] === 1 ? $savedAnswers : []); ?>;
    window.updateMatchingAnswer = function(leftIndex, rightValue) {
        if (!window.matchingAnswers) window.matchingAnswers = [];
        window.matchingAnswers[leftIndex] = rightValue;
        fetch('ajax/save-quiz-answer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: "question=<?php echo $question['question_id']; ?>&answer=" + encodeURIComponent(JSON.stringify(window.matchingAnswers)) + "&quiz_id=<?php echo $quiz_id; ?>"
        });
    };
    window.updateGrammarAnswer = function(answer) {
        fetch('ajax/save-quiz-answer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: "question=<?php echo $question['question_id']; ?>&answer=" + encodeURIComponent(answer) + "&quiz_id=<?php echo $quiz_id; ?>"
        });
    };

    function isAnswerComplete(answer, type) {
        if (type === 0 || type === 2) {
            // Multiple choice or grammar: must not be empty
            return !!answer && answer !== '';
        } else if (type === 1) {
            // Matching: must be a full array, all not empty
            if (!answer) return false;
            if (typeof answer === 'string') {
                try { answer = JSON.parse(answer); } catch (e) { return false; }
            }
            if (!Array.isArray(answer)) return false;
            return answer.every(a => a !== '');
        }
        return false;
    }

    document.querySelector('form').addEventListener('submit', function(e) {
        // Gather all answers and question types from PHP
        const allAnswers = <?php echo json_encode($existing_answers); ?>;
        const allTypes = <?php echo json_encode(array_column($questions, 'question_type')); ?>;
        let firstIncomplete = -1;
        for (let i = 0; i < allTypes.length; i++) {
            if (!isAnswerComplete(allAnswers[$questions[$i]['question_id']], allTypes[$i])) {
                firstIncomplete = i;
                break;
            }
        }
        if (firstIncomplete !== -1) {
            e.preventDefault();
            // Store error in sessionStorage for after reload
            sessionStorage.setItem('quizError', 'Please answer all questions before submitting. Redirecting to the first incomplete question.');
            window.goToQuestion(firstIncomplete);
            return false;
        }
        // Hide alert if all complete
        document.getElementById('quiz-error-alert').classList.add('d-none');
    });

    // On page load, show error from sessionStorage if present
    window.addEventListener('DOMContentLoaded', function() {
        const errorMsg = sessionStorage.getItem('quizError');
        if (errorMsg) {
            const alertBox = document.getElementById('quiz-error-alert');
            alertBox.textContent = errorMsg;
            alertBox.classList.remove('d-none');
            sessionStorage.removeItem('quizError');
        }
    });

    // Initialize selected answers when page loads
    document.addEventListener('DOMContentLoaded', function() {
        const selectedAnswer = <?php echo isset($existing_answers[$question['question_id']]) ? json_encode($existing_answers[$question['question_id']]) : 'null'; ?>;
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
                            body: "question=<?php echo $question['question_id']; ?>&answer=" + encodeURIComponent(JSON.stringify(answer)) + "&quiz_id=<?php echo $quiz_id; ?>"
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
            const selectedAnswer = <?php echo isset($existing_answers[$question['question_id']]) ? json_encode($existing_answers[$question['question_id']]) : 'null'; ?>;
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

    // Matching select logic to disable already chosen right options in other dropdowns
    function updateMatchingDropdowns() {
        const selects = document.querySelectorAll('.matching-select');
        // Gather all selected values
        let selected = Array.from(selects).map(sel => sel.value).filter(v => v !== '');
        selects.forEach(sel => {
            let current = sel.value;
            Array.from(sel.options).forEach(opt => {
                if (opt.value === '') return;
                // Disable if selected elsewhere and not the current selection
                if (selected.includes(opt.value) && opt.value !== current) {
                    opt.disabled = true;
                } else {
                    opt.disabled = false;
                }
            });
        });
    }
    // Attach event listeners
    window.addEventListener('DOMContentLoaded', function() {
        updateMatchingDropdowns();
        document.querySelectorAll('.matching-select').forEach(sel => {
            sel.addEventListener('change', updateMatchingDropdowns);
        });
    });
    </script>
</body>
</html>