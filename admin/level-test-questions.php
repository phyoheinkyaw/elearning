<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$level_filter = isset($_GET['level']) ? sanitize_input($_GET['level']) : '';

// Handle question deletion
if (isset($_POST['delete_question'])) {
    $question_id = (int)$_POST['question_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM level_test_questions WHERE question_id = ?");
        $stmt->execute([$question_id]);
        $_SESSION['success_message'] = "Question deleted successfully.";
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error deleting question.";
    }
    
    // Redirect with filters
    $redirect_url = 'level-test-questions.php';
    if ($search) $redirect_url .= '?search=' . urlencode($search);
    if ($level_filter) $redirect_url .= ($search ? '&' : '?') . 'level=' . urlencode($level_filter);
    header('Location: ' . $redirect_url);
    exit();
}

// Build query for questions with filters
$query = "SELECT * FROM level_test_questions WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (question_text LIKE ? OR option_a LIKE ? OR option_b LIKE ? OR option_c LIKE ? OR option_d LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($level_filter) {
    $query .= " AND difficulty_level = ?";
    $params[] = $level_filter;
}

$query .= " ORDER BY difficulty_level, question_id";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $questions = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching questions.";
    $questions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level Test Questions - ELearning Admin</title>
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
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-2">Level Test Questions</h1>
                        <p class="text-muted mb-0">Manage questions for the English level test</p>
                    </div>
                    <a href="level-test-question-form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Question
                    </a>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Level Filter</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="level-test-questions.php" 
                                       class="btn <?php echo !$level_filter ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        All Levels
                                    </a>
                                    <?php
                                    $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
                                    foreach ($levels as $level):
                                    ?>
                                        <a href="?level=<?php echo $level; ?>" 
                                           class="btn <?php echo $level_filter === $level ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                            <?php echo $level; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="questionsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Question</th>
                                        <th>Options</th>
                                        <th>Correct Answer</th>
                                        <th>Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($questions as $question): ?>
                                        <tr>
                                            <td><?php echo $question['question_id']; ?></td>
                                            <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                                            <td>
                                                <div class="small">
                                                    A: <?php echo htmlspecialchars($question['option_a']); ?><br>
                                                    B: <?php echo htmlspecialchars($question['option_b']); ?><br>
                                                    C: <?php echo htmlspecialchars($question['option_c']); ?><br>
                                                    D: <?php echo htmlspecialchars($question['option_d']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo $question['correct_answer']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($question['difficulty_level']) {
                                                        'A1' => 'info',
                                                        'A2' => 'primary',
                                                        'B1' => 'success',
                                                        'B2' => 'warning',
                                                        'C1' => 'danger',
                                                        'C2' => 'dark',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo $question['difficulty_level']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="level-test-question-form.php?id=<?php echo $question['question_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $question['question_id']; ?>">
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
    <?php foreach ($questions as $question): ?>
        <div class="modal fade" id="deleteModal<?php echo $question['question_id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this question?
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/lib/jquery-3.7.1.min.js"></script>
    <script src="../js/lib/jquery.dataTables.min.js"></script>
    <script src="../js/lib/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#questionsTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 25,
                language: {
                    search: "Search questions:"
                }
            });
        });
    </script>
</body>
</html> 