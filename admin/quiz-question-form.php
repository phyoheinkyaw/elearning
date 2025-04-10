<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$quiz_id) {
    $_SESSION['error'] = "Quiz ID is required.";
    header('Location: quizzes.php');
    exit();
}

// Get quiz details
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    $_SESSION['error'] = "Quiz not found.";
    header('Location: quizzes.php');
    exit();
}

// Get question details if editing
$question = null;
if ($question_id) {
    $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE question_id = ? AND quiz_id = ?");
    $stmt->execute([$question_id, $quiz_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        $_SESSION['error'] = "Question not found.";
        header("Location: quiz-questions.php?quiz_id=" . $quiz_id);
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = trim($_POST['question_text']);
    $question_type = (int)$_POST['question_type'];
    $options = [];
    $correct_answer = '';
    
    // Validate question text
    if (empty($question_text)) {
        $_SESSION['error'] = "Question text is required.";
    } else {
        // Handle different question types
        switch ($question_type) {
            case 0: // Multiple Choice
                $options = [
                    'A' => trim($_POST['option_a']),
                    'B' => trim($_POST['option_b']),
                    'C' => trim($_POST['option_c']),
                    'D' => trim($_POST['option_d'])
                ];
                $correct_answer = $_POST['correct_answer'];
                
                // Validate options
                if (empty($options['A']) || empty($options['B']) || 
                    empty($options['C']) || empty($options['D'])) {
                    $_SESSION['error'] = "All options are required for multiple choice questions.";
                } elseif (!isset($options[$correct_answer])) {
                    $_SESSION['error'] = "Please select a valid correct answer.";
                }
                break;
                
            case 1: // Matching
                $pairs = [];
                for ($i = 0; $i < 4; $i++) {
                    if (!empty($_POST['left_' . $i]) && !empty($_POST['right_' . $i])) {
                        $pairs[] = [
                            'left' => trim($_POST['left_' . $i]),
                            'right' => trim($_POST['right_' . $i])
                        ];
                    }
                }
                
                if (count($pairs) < 2) {
                    $_SESSION['error'] = "At least 2 matching pairs are required.";
                } else {
                    $options = $pairs;
                    $correct_answer = json_encode($pairs);
                }
                break;
                
            case 2: // Grammar
                $options = [
                    'sentence' => trim($_POST['sentence']),
                    'correct' => trim($_POST['correct_sentence'])
                ];
                $correct_answer = $options['correct'];
                
                if (empty($options['sentence']) || empty($options['correct'])) {
                    $_SESSION['error'] = "Both sentence and correct version are required.";
                }
                break;
        }
    }
    
    if (!isset($_SESSION['error'])) {
        try {
            if ($question_id) {
                // Update existing question
                $stmt = $conn->prepare("
                    UPDATE quiz_questions 
                    SET question_text = ?, question_type = ?, options = ?, correct_answer = ?
                    WHERE question_id = ? AND quiz_id = ?
                ");
                $stmt->execute([
                    $question_text,
                    $question_type,
                    json_encode($options),
                    $correct_answer,
                    $question_id,
                    $quiz_id
                ]);
                $_SESSION['success'] = "Question updated successfully.";
            } else {
                // Insert new question
                $stmt = $conn->prepare("
                    INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $quiz_id,
                    $question_text,
                    $question_type,
                    json_encode($options),
                    $correct_answer
                ]);
                $_SESSION['success'] = "Question added successfully.";
            }
            
            header("Location: quiz-questions.php?quiz_id=" . $quiz_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error saving question: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $question_id ? 'Edit' : 'Add'; ?> Question - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
</head>
<body class="admin-dashboard">
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1><?php echo $question_id ? 'Edit' : 'Add'; ?> Question</h1>
                        <p class="text-muted mb-0">Quiz: <?php echo htmlspecialchars($quiz['title']); ?></p>
                    </div>
                    <a href="quiz-questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Questions
                    </a>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="question_type" class="form-label">Question Type</label>
                                <select class="form-select" id="question_type" name="question_type" required>
                                    <option value="">Select type...</option>
                                    <option value="0" <?php echo $question && $question['question_type'] == 0 ? 'selected' : ''; ?>>Multiple Choice</option>
                                    <option value="1" <?php echo $question && $question['question_type'] == 1 ? 'selected' : ''; ?>>Matching</option>
                                    <option value="2" <?php echo $question && $question['question_type'] == 2 ? 'selected' : ''; ?>>Grammar</option>
                                </select>
                                <div class="invalid-feedback">Please select a question type.</div>
                            </div>

                            <div class="mb-3">
                                <label for="question_text" class="form-label">Question Text</label>
                                <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php echo $question ? htmlspecialchars($question['question_text']) : ''; ?></textarea>
                                <div class="invalid-feedback">Please enter the question text.</div>
                            </div>

                            <!-- Multiple Choice Options -->
                            <div id="multiple_choice_options" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Options</label>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">A</span>
                                        <input type="text" class="form-control" name="option_a" value="<?php echo $question && $question['question_type'] == 0 ? htmlspecialchars(json_decode($question['options'])->A) : ''; ?>">
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">B</span>
                                        <input type="text" class="form-control" name="option_b" value="<?php echo $question && $question['question_type'] == 0 ? htmlspecialchars(json_decode($question['options'])->B) : ''; ?>">
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">C</span>
                                        <input type="text" class="form-control" name="option_c" value="<?php echo $question && $question['question_type'] == 0 ? htmlspecialchars(json_decode($question['options'])->C) : ''; ?>">
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">D</span>
                                        <input type="text" class="form-control" name="option_d" value="<?php echo $question && $question['question_type'] == 0 ? htmlspecialchars(json_decode($question['options'])->D) : ''; ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="correct_answer" class="form-label">Correct Answer</label>
                                    <select class="form-select" id="correct_answer" name="correct_answer">
                                        <option value="">Select correct answer...</option>
                                        <option value="A" <?php echo $question && $question['question_type'] == 0 && $question['correct_answer'] == 'A' ? 'selected' : ''; ?>>A</option>
                                        <option value="B" <?php echo $question && $question['question_type'] == 0 && $question['correct_answer'] == 'B' ? 'selected' : ''; ?>>B</option>
                                        <option value="C" <?php echo $question && $question['question_type'] == 0 && $question['correct_answer'] == 'C' ? 'selected' : ''; ?>>C</option>
                                        <option value="D" <?php echo $question && $question['question_type'] == 0 && $question['correct_answer'] == 'D' ? 'selected' : ''; ?>>D</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Matching Options -->
                            <div id="matching_options" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Matching Pairs</label>
                                    <?php
                                    $pairs = [];
                                    if ($question && $question['question_type'] == 1) {
                                        $pairs = json_decode($question['options'], true);
                                    }
                                    for ($i = 0; $i < 4; $i++):
                                        $pair = isset($pairs[$i]) ? $pairs[$i] : ['left' => '', 'right' => ''];
                                    ?>
                                    <div class="row mb-2">
                                        <div class="col">
                                            <input type="text" class="form-control" name="left_<?php echo $i; ?>" 
                                                   placeholder="Left item" value="<?php echo htmlspecialchars($pair['left']); ?>">
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control" name="right_<?php echo $i; ?>" 
                                                   placeholder="Right item" value="<?php echo htmlspecialchars($pair['right']); ?>">
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Grammar Options -->
                            <div id="grammar_options" style="display: none;">
                                <div class="mb-3">
                                    <label for="sentence" class="form-label">Sentence with Error</label>
                                    <textarea class="form-control" id="sentence" name="sentence" rows="2"><?php echo $question && $question['question_type'] == 2 ? htmlspecialchars(json_decode($question['options'])->sentence) : ''; ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="correct_sentence" class="form-label">Correct Sentence</label>
                                    <textarea class="form-control" id="correct_sentence" name="correct_sentence" rows="2"><?php echo $question && $question['question_type'] == 2 ? htmlspecialchars(json_decode($question['options'])->correct) : ''; ?></textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $question_id ? 'Update' : 'Add'; ?> Question
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Show/hide options based on question type
        document.getElementById('question_type').addEventListener('change', function() {
            document.getElementById('multiple_choice_options').style.display = this.value === '0' ? 'block' : 'none';
            document.getElementById('matching_options').style.display = this.value === '1' ? 'block' : 'none';
            document.getElementById('grammar_options').style.display = this.value === '2' ? 'block' : 'none';
        });

        // Trigger change event on page load
        document.getElementById('question_type').dispatchEvent(new Event('change'));
    </script>
</body>
</html> 