<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get question ID if editing
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $question_id > 0;

// Get question data if editing
$question = [];
if ($is_edit) {
    try {
        $stmt = $conn->prepare("SELECT * FROM level_test_questions WHERE question_id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch();
        
        if (!$question) {
            $_SESSION['error_message'] = "Question not found.";
            header('Location: level-test-questions.php');
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error fetching question data.";
        header('Location: level-test-questions.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = sanitize_input($_POST['question_text']);
    $option_a = sanitize_input($_POST['option_a']);
    $option_b = sanitize_input($_POST['option_b']);
    $option_c = sanitize_input($_POST['option_c']);
    $option_d = sanitize_input($_POST['option_d']);
    $correct_answer = strtoupper(sanitize_input($_POST['correct_answer']));
    $difficulty_level = sanitize_input($_POST['difficulty_level']);
    
    // Validate correct answer
    if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
        $_SESSION['error_message'] = "Invalid correct answer. Must be A, B, C, or D.";
    } else {
        try {
            if ($is_edit) {
                // Update question
                $stmt = $conn->prepare("
                    UPDATE level_test_questions 
                    SET question_text = ?, option_a = ?, option_b = ?, 
                        option_c = ?, option_d = ?, correct_answer = ?, 
                        difficulty_level = ?
                    WHERE question_id = ?
                ");
                $stmt->execute([
                    $question_text, $option_a, $option_b, 
                    $option_c, $option_d, $correct_answer, 
                    $difficulty_level, $question_id
                ]);
                
                $_SESSION['success_message'] = "Question updated successfully.";
            } else {
                // Insert new question
                $stmt = $conn->prepare("
                    INSERT INTO level_test_questions (
                        question_text, option_a, option_b, 
                        option_c, option_d, correct_answer, 
                        difficulty_level
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $question_text, $option_a, $option_b, 
                    $option_c, $option_d, $correct_answer, 
                    $difficulty_level
                ]);
                
                $_SESSION['success_message'] = "Question added successfully.";
            }
            
            header('Location: level-test-questions.php');
            exit();
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Error saving question.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Question - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-2"><?php echo $is_edit ? 'Edit' : 'Add'; ?> Question</h1>
                        <p class="text-muted mb-0">Create or modify level test questions</p>
                    </div>
                    <a href="level-test-questions.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Questions
                    </a>
                </div>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="question_text" class="form-label">Question Text</label>
                                    <textarea class="form-control" id="question_text" name="question_text" 
                                              rows="3" required><?php echo htmlspecialchars($question['question_text'] ?? ''); ?></textarea>
                                    <div class="invalid-feedback">Please enter the question text.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="option_a" class="form-label">Option A</label>
                                    <input type="text" class="form-control" id="option_a" name="option_a" 
                                           value="<?php echo htmlspecialchars($question['option_a'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter option A.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="option_b" class="form-label">Option B</label>
                                    <input type="text" class="form-control" id="option_b" name="option_b" 
                                           value="<?php echo htmlspecialchars($question['option_b'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter option B.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="option_c" class="form-label">Option C</label>
                                    <input type="text" class="form-control" id="option_c" name="option_c" 
                                           value="<?php echo htmlspecialchars($question['option_c'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter option C.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="option_d" class="form-label">Option D</label>
                                    <input type="text" class="form-control" id="option_d" name="option_d" 
                                           value="<?php echo htmlspecialchars($question['option_d'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter option D.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="correct_answer" class="form-label">Correct Answer</label>
                                    <select class="form-select" id="correct_answer" name="correct_answer" required>
                                        <option value="">Select Answer</option>
                                        <option value="A" <?php echo ($question['correct_answer'] ?? '') === 'A' ? 'selected' : ''; ?>>A</option>
                                        <option value="B" <?php echo ($question['correct_answer'] ?? '') === 'B' ? 'selected' : ''; ?>>B</option>
                                        <option value="C" <?php echo ($question['correct_answer'] ?? '') === 'C' ? 'selected' : ''; ?>>C</option>
                                        <option value="D" <?php echo ($question['correct_answer'] ?? '') === 'D' ? 'selected' : ''; ?>>D</option>
                                    </select>
                                    <div class="invalid-feedback">Please select the correct answer.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="difficulty_level" class="form-label">Difficulty Level</label>
                                    <select class="form-select" id="difficulty_level" name="difficulty_level" required>
                                        <option value="">Select Level</option>
                                        <?php
                                        $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
                                        foreach ($levels as $level):
                                        ?>
                                            <option value="<?php echo $level; ?>" 
                                                    <?php echo ($question['difficulty_level'] ?? '') === $level ? 'selected' : ''; ?>>
                                                <?php echo $level; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a difficulty level.</div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Update' : 'Add'; ?> Question
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html> 