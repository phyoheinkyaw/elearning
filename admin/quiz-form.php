<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz = null;
$is_edit = false;

if ($quiz_id) {
    $is_edit = true;
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch();
    
    if (!$quiz) {
        $_SESSION['error'] = "Quiz not found.";
        header('Location: quizzes.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $difficulty_level = (int)$_POST['difficulty_level'];
    
    try {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE quizzes SET title = ?, description = ?, difficulty_level = ? WHERE quiz_id = ?");
            $stmt->execute([$title, $description, $difficulty_level, $quiz_id]);
            $_SESSION['success'] = "Quiz updated successfully.";
        } else {
            $stmt = $conn->prepare("INSERT INTO quizzes (title, description, difficulty_level) VALUES (?, ?, ?)");
            $stmt->execute([$title, $description, $difficulty_level]);
            $quiz_id = $conn->lastInsertId();
            $_SESSION['success'] = "Quiz created successfully.";
        }
        
        header("Location: quiz-questions.php?quiz_id=" . $quiz_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error saving quiz: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Quiz - Admin Dashboard</title>
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
                    <h1><?php echo $is_edit ? 'Edit' : 'Add'; ?> Quiz</h1>
                    <a href="quizzes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Quizzes
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
                                <label for="title" class="form-label">Quiz Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($quiz['title'] ?? ''); ?>" required>
                                <div class="invalid-feedback">
                                    Please provide a quiz title.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="difficulty_level" class="form-label">Difficulty Level</label>
                                <select class="form-select" id="difficulty_level" name="difficulty_level" required>
                                    <option value="">Select difficulty level</option>
                                    <option value="0" <?php echo ($quiz['difficulty_level'] ?? '') === 0 ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="1" <?php echo ($quiz['difficulty_level'] ?? '') === 1 ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="2" <?php echo ($quiz['difficulty_level'] ?? '') === 2 ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a difficulty level.
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $is_edit ? 'Update Quiz' : 'Create Quiz'; ?>
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
    </script>
</body>
</html> 