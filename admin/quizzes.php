<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$difficulty = isset($_GET['difficulty']) ? trim($_GET['difficulty']) : '';

// Handle quiz deletion
if (isset($_POST['delete_quiz'])) {
    $quiz_id = (int)$_POST['quiz_id'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Delete quiz questions first (due to foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        
        // Delete quiz attempts
        $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        
        // Delete quiz results
        $stmt = $conn->prepare("DELETE FROM quiz_results WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        
        // Finally delete the quiz
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Quiz deleted successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting quiz: " . $e->getMessage();
    }
    
    // Redirect to maintain filter state
    header("Location: quizzes.php?search=" . urlencode($search) . "&difficulty=" . urlencode($difficulty));
    exit();
}

// Build the query
$query = "SELECT q.*, 
          COUNT(DISTINCT qa.attempt_id) as total_attempts,
          COUNT(DISTINCT qr.result_id) as total_results,
          AVG(qr.score) as average_score
          FROM quizzes q
          LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
          LEFT JOIN quiz_results qr ON q.quiz_id = qr.quiz_id
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (q.title LIKE ? OR q.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($difficulty !== '') {
    $query .= " AND q.difficulty_level = ?";
    $params[] = (int)$difficulty;
}

$query .= " GROUP BY q.quiz_id ORDER BY q.created_at DESC";

// Execute the query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$quizzes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quizzes - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="../js/lib/dataTables.bootstrap5.min.css" rel="stylesheet">
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
                    <h1>Manage Quizzes</h1>
                    <a href="quiz-form.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Quiz
                    </a>
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

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Difficulty Filter</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="quizzes.php" 
                                       class="btn <?php echo $difficulty === '' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        All Difficulties
                                    </a>
                                    <a href="?difficulty=0" 
                                       class="btn <?php echo $difficulty === '0' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        Beginner
                                    </a>
                                    <a href="?difficulty=1" 
                                       class="btn <?php echo $difficulty === '1' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        Intermediate
                                    </a>
                                    <a href="?difficulty=2" 
                                       class="btn <?php echo $difficulty === '2' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        Advanced
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quizzes Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="quizzesTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Difficulty</th>
                                        <th>Avg Score</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quizzes as $quiz): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                        <td>
                                            <?php
                                            switch ($quiz['difficulty_level']) {
                                                case 0:
                                                    echo '<span class="badge bg-success">Beginner</span>';
                                                    break;
                                                case 1:
                                                    echo '<span class="badge bg-warning">Intermediate</span>';
                                                    break;
                                                case 2:
                                                    echo '<span class="badge bg-danger">Advanced</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $quiz['average_score'] ? number_format($quiz['average_score'], 1) . '%' : 'N/A'; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($quiz['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="quiz-form.php?id=<?php echo $quiz['quiz_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="quiz-questions.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-list"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $quiz['quiz_id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Modals -->
    <?php foreach ($quizzes as $quiz): ?>
        <div class="modal fade" id="deleteModal<?php echo $quiz['quiz_id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Quiz</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this quiz? This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['quiz_id']; ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_quiz" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="../js/lib/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="../js/lib/jquery.dataTables.min.js"></script>
    <script src="../js/lib/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#quizzesTable').DataTable({
                "order": [[3, "desc"]], // Sort by Created date by default
                "pageLength": 10,
                "language": {
                    "search": "Search quizzes:",
                    "lengthMenu": "Show _MENU_ quizzes per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ quizzes",
                    "infoEmpty": "No quizzes found",
                    "infoFiltered": "(filtered from _MAX_ total quizzes)"
                }
            });
        });
    </script>
</body>
</html>
