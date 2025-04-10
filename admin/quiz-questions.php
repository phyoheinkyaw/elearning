<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

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

// Handle question deletion
if (isset($_POST['delete_question'])) {
    $question_id = (int)$_POST['question_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE question_id = ? AND quiz_id = ?");
        $stmt->execute([$question_id, $quiz_id]);
        $_SESSION['success'] = "Question deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting question: " . $e->getMessage();
    }
    
    header("Location: quiz-questions.php?quiz_id=" . $quiz_id);
    exit();
}

// Get questions for this quiz
$stmt = $conn->prepare("
    SELECT q.*, 
           COUNT(DISTINCT qa.attempt_id) as total_attempts,
           AVG(CASE WHEN qa.score = 100 THEN 1 ELSE 0 END) * 100 as success_rate
    FROM quiz_questions q
    LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
    WHERE q.quiz_id = ?
    GROUP BY q.question_id
    ORDER BY q.question_id
");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quiz Questions - Admin Dashboard</title>
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
                        <h1>Manage Questions</h1>
                        <p class="text-muted mb-0">Quiz: <?php echo htmlspecialchars($quiz['title']); ?></p>
                    </div>
                    <div>
                        <a href="quizzes.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Quizzes
                        </a>
                        <a href="quiz-question-form.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Question
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

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
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Question</th>
                                        <th>Type</th>
                                        <th>Attempts</th>
                                        <th>Success Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($questions as $question): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                                        <td>
                                            <?php
                                            switch ($question['question_type']) {
                                                case 0:
                                                    echo '<span class="badge bg-primary">Multiple Choice</span>';
                                                    break;
                                                case 1:
                                                    echo '<span class="badge bg-info">Matching</span>';
                                                    break;
                                                case 2:
                                                    echo '<span class="badge bg-warning">Grammar</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $question['total_attempts']; ?></td>
                                        <td>
                                            <?php 
                                            if ($question['success_rate']) {
                                                echo number_format($question['success_rate'], 1) . '%';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="quiz-question-form.php?quiz_id=<?php echo $quiz_id; ?>&id=<?php echo $question['question_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $question['question_id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $question['question_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Delete Question</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete this question? This action cannot be undone.
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="question_id" value="<?php echo $question['question_id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="delete_question" class="btn btn-danger">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 