<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/instructor_functions.php';

// Check if user is logged in and is an instructor
if (!is_logged_in() || $_SESSION['role'] !== 'instructor') {
    header('Location: ../login.php');
    exit();
}

// Get instructor ID
$instructor_id = $_SESSION['user_id'];

// Get all courses for this instructor
try {
    $stmt = $conn->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.course_id) as enrollment_count,
               (SELECT COUNT(*) FROM course_materials cm WHERE cm.course_id = c.course_id) as materials_count
        FROM courses c 
        WHERE c.instructor_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching courses: " . $e->getMessage();
    $courses = [];
}

// Page title
$page_title = "My Courses";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Instructor Panel</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <link href="css/instructor-style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 instructor-sidebar">
                <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-white min-vh-100">
                    <a href="../index.php" class="d-flex align-items-center pb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <span class="fs-5 d-none d-sm-inline">ELearning</span>
                    </a>
                    <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start w-100" id="menu">
                        <li class="nav-item w-100">
                            <a href="index.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i>
                                <span class="d-none d-sm-inline">Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="courses.php" class="nav-link active">
                                <i class="fas fa-book"></i>
                                <span class="d-none d-sm-inline">My Courses</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="students.php" class="nav-link">
                                <i class="fas fa-users"></i>
                                <span class="d-none d-sm-inline">Students</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="analytics.php" class="nav-link">
                                <i class="fas fa-chart-bar"></i>
                                <span class="d-none d-sm-inline">Analytics</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="materials.php" class="nav-link">
                                <i class="fas fa-file-alt"></i>
                                <span class="d-none d-sm-inline">Materials</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="../profile.php" class="nav-link">
                                <i class="fas fa-user"></i>
                                <span class="d-none d-sm-inline">Profile</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="../logout.php" class="nav-link text-danger">
                                <i class="fas fa-sign-out-alt"></i>
                                <span class="d-none d-sm-inline">Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 instructor-main">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><?php echo $page_title; ?></h1>
                        <a href="course-form.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create New Course
                        </a>
                    </div>
                    
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($courses)): ?>
                        <div class="row">
                            <?php foreach ($courses as $course): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-img-top bg-light" style="height: 160px; overflow: hidden;">
                                            <?php if (!empty($course['thumbnail_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="img-fluid w-100 h-100" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100">
                                                    <i class="fas fa-book fa-3x text-secondary"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                            <div class="mb-2">
                                                <span class="badge bg-info"><?php echo $course['level']; ?></span>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($course['difficulty_level']); ?></span>
                                            </div>
                                            <p class="card-text text-muted small">
                                                <?php echo mb_strimwidth(htmlspecialchars($course['description']), 0, 100, '...'); ?>
                                            </p>
                                            <div class="d-flex justify-content-between">
                                                <div><i class="fas fa-users me-1"></i> <?php echo $course['enrollment_count']; ?> students</div>
                                                <div><i class="fas fa-file-alt me-1"></i> <?php echo $course['materials_count']; ?> materials</div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-white border-0">
                                            <div class="d-flex justify-content-between">
                                                <a href="course-details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                                <a href="course-form.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-book fa-4x text-muted mb-3"></i>
                            <h3>No Courses Yet</h3>
                            <p>You haven't created any courses yet. Click the button below to create your first course.</p>
                            <a href="course-form.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Create New Course
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * Helper function to get the appropriate Bootstrap color class for a proficiency level
 */
function get_level_color($level) {
    switch (strtolower($level)) {
        case 'beginner':
        case 'a1':
        case 'a2':
            return 'success';
        case 'intermediate':
        case 'b1':
        case 'b2':
            return 'warning';
        case 'advanced':
        case 'c1':
        case 'c2':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Helper function to get a readable label for difficulty level
 */
function get_difficulty_label($difficulty) {
    switch ($difficulty) {
        case 1:
            return 'Easy';
        case 2:
            return 'Medium';
        case 3:
            return 'Hard';
        default:
            return 'N/A';
    }
} 