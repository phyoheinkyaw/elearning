<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get user's level
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT proficiency_level FROM user_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_level = $stmt->fetchColumn();

// Convert proficiency level to difficulty level (A1=0, A2=0, B1=1, B2=1, C1=2, C2=2)
$difficulty_level = 0;
if (strpos($user_level, 'B') !== false) {
    $difficulty_level = 1;
} elseif (strpos($user_level, 'C') !== false) {
    $difficulty_level = 2;
}

// Get available quizzes for user's level
$query = "SELECT q.*, 
          COUNT(DISTINCT qa.attempt_id) as total_attempts,
          COUNT(DISTINCT qr.result_id) as total_results,
          AVG(qr.score) as average_score
          FROM quizzes q
          LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id AND qa.user_id = ?
          LEFT JOIN quiz_results qr ON q.quiz_id = qr.quiz_id AND qr.user_id = ?
          WHERE q.difficulty_level = ?
          GROUP BY q.quiz_id
          ORDER BY q.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute([$user_id, $user_id, $difficulty_level]);
$quizzes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Quizzes - English Learning Platform</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h1>Available Quizzes</h1>
                <p class="text-muted">Test your knowledge with these quizzes matching your current level.</p>
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

        <div class="row">
            <?php foreach ($quizzes as $quiz): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($quiz['description']); ?></p>
                            <div class="mb-3">
                                <span class="badge bg-<?php 
                                    echo $quiz['difficulty_level'] == 0 ? 'success' : 
                                        ($quiz['difficulty_level'] == 1 ? 'warning' : 'danger'); 
                                ?>">
                                    <?php 
                                    echo $quiz['difficulty_level'] == 0 ? 'Beginner' : 
                                        ($quiz['difficulty_level'] == 1 ? 'Intermediate' : 'Advanced'); 
                                    ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <?php echo $quiz['total_attempts']; ?> attempts
                                    </small>
                                    <?php if ($quiz['average_score']): ?>
                                        <br>
                                        <small class="text-muted">
                                            Avg Score: <?php echo number_format($quiz['average_score'], 1); ?>%
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <a href="take-quiz.php?id=<?php echo $quiz['quiz_id']; ?>" 
                                   class="btn btn-primary">
                                    Take Quiz
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($quizzes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No quizzes available for your current level. Keep practicing to unlock more quizzes!
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</body>
</html> 