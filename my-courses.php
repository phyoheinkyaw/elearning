<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

try {
    // First, check if the user has any enrollments
    $stmt = $conn->prepare("
        SELECT COUNT(*) as enrollment_count
        FROM course_enrollments
        WHERE user_id = :user_id
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $enrollment_count = $stmt->fetch()['enrollment_count'];

    if ($enrollment_count > 0) {
        // Get user's enrolled courses with progress
        $stmt = $conn->prepare("
            SELECT 
                c.course_id,
                ANY_VALUE(c.title) as title,
                ANY_VALUE(c.description) as description,
                ANY_VALUE(c.level) as level,
                ANY_VALUE(c.difficulty_level) as difficulty_level,
                ANY_VALUE(c.thumbnail_url) as thumbnail_url,
                ANY_VALUE(u.username) as instructor_name,
                MAX(ce.enrolled_at) as enrolled_at,
                COUNT(DISTINCT cm.material_id) as total_materials,
                COUNT(DISTINCT CASE WHEN up.progress = 100 THEN up.material_id END) as completed_materials
            FROM course_enrollments ce
            INNER JOIN courses c ON ce.course_id = c.course_id
            LEFT JOIN users u ON c.instructor_id = u.user_id
            LEFT JOIN course_materials cm ON c.course_id = cm.course_id
            LEFT JOIN user_progress up ON cm.material_id = up.material_id AND up.user_id = :user_id1
            WHERE ce.user_id = :user_id2
            GROUP BY c.course_id
            ORDER BY enrolled_at DESC
        ");
        $stmt->execute(['user_id1' => $_SESSION['user_id'], 'user_id2' => $_SESSION['user_id']]);
        $courses = $stmt->fetchAll();
    } else {
        $courses = [];
    }

    // Get recent activity
    $stmt = $conn->prepare("
        SELECT 
            m.title as material_title,
            m.material_id,
            c.title as course_title,
            c.course_id,
            up.progress,
            up.last_accessed
        FROM user_progress up
        INNER JOIN course_materials m ON up.material_id = m.material_id
        INNER JOIN courses c ON m.course_id = c.course_id
        WHERE up.user_id = :user_id
        AND up.last_accessed IS NOT NULL
        ORDER BY up.last_accessed DESC
        LIMIT 5
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $recent_activity = $stmt->fetchAll();

} catch(PDOException $e) {
    error_log('Course Load Error: ' . $e->getMessage() . ' User ID: ' . $_SESSION['user_id']);
    error_log('SQL Error: ' . $e->getMessage() . ' | Query: ' . $stmt->queryString);
    $_SESSION['error_message'] = 'Error loading courses: ' . htmlspecialchars($e->getMessage());
    $courses = [];
    $recent_activity = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Floating Chatbot CSS -->
    <link href="css/floating-chatbot.css" rel="stylesheet">
    <!-- Search Autocomplete CSS -->
    <link href="css/search-autocomplete.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <h1 class="mb-4">My Courses</h1>

                <?php if (empty($courses)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You haven't enrolled in any courses yet.
                    <a href="courses.php" class="alert-link">Browse our courses</a> to get started!
                </div>
                <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($courses as $course): ?>
                    <?php 
                            $progress = $course['total_materials'] > 0 
                                ? ($course['completed_materials'] / $course['total_materials']) * 100 
                                : 0;
                            ?>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm hover-shadow">
                            <div class="position-relative">
                                <img src="<?php if($course['thumbnail_url']): ?>uploads/<?php echo $course['thumbnail_url']; ?><?php else: ?>https://placehold.co/300x200/415a77/f2f2f2<?php endif ; ?>"
                                    class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>"
                                    style="height: 300px; object-fit: cover;">
                                <div class="position-absolute bottom-0 start-0 w-100 p-3 bg-gradient-dark">
                                    <div class="progress" style="height: 0.5rem;">
                                        <div class="progress-bar bg-success" role="progressbar"
                                            style="width: <?php echo $progress; ?>%"
                                            aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0"
                                            aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    <a href="course-details.php?id=<?php echo $course['course_id']; ?>"
                                        class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </a>
                                </h5>
                                <p class="card-text text-muted mb-3">
                                    <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="small text-muted">
                                        <i class="fas fa-graduation-cap me-1"></i>
                                        <?php echo $course['completed_materials']; ?>/<?php echo $course['total_materials']; ?>
                                        completed
                                    </div>
                                    <span class="badge bg-<?php echo $progress == 100 ? 'success' : 'primary'; ?>">
                                        <?php echo number_format($progress, 0); ?>% Complete
                                    </span>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($course['instructor_name']); ?>
                                    </small>
                                    <a href="course-details.php?id=<?php echo $course['course_id']; ?>"
                                        class="btn btn-sm btn-primary">
                                        Continue Learning
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm position-sticky" style="top: 9rem; z-index: 1;">
                    <!-- Changed sticky-top to position-sticky, adjusted top value, added z-index -->
                    <div class="card-body">
                        <h4 class="card-title mb-4">Recent Activity</h4>
                        <?php if (empty($recent_activity)): ?>
                        <p class="text-muted">No recent activity</p>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_activity as $activity): ?>
                            <a href="material-details.php?id=<?php echo $activity['material_id']; ?>"
                                class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['material_title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('M j', strtotime($activity['last_accessed'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1 small text-muted">
                                    <?php echo htmlspecialchars($activity['course_title']); ?>
                                </p>
                                <div class="progress mt-2" style="height: 0.25rem;">
                                    <div class="progress-bar" role="progressbar"
                                        style="width: <?php echo $activity['progress']; ?>%"
                                        aria-valuenow="<?php echo $activity['progress']; ?>" aria-valuemin="0"
                                        aria-valuemax="100">
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html>