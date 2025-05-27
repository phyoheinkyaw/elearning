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

// Get material ID from URL
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($material_id <= 0) {
    $_SESSION['error_message'] = "Invalid material ID.";
    header('Location: materials.php');
    exit();
}

// Get material details and verify it belongs to a course owned by this instructor
try {
    $stmt = $conn->prepare("
        SELECT m.*, c.title as course_title, c.course_id, c.instructor_id,
        (SELECT COUNT(*) FROM course_materials WHERE course_id = c.course_id) as total_materials
        FROM course_materials m
        JOIN courses c ON m.course_id = c.course_id
        WHERE m.material_id = ? AND c.instructor_id = ?
    ");
    $stmt->execute([$material_id, $instructor_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        $_SESSION['error_message'] = "Material not found or you don't have access to it.";
        header('Location: materials.php');
        exit();
    }

    // Get previous and next materials
    $stmt = $conn->prepare("
        SELECT material_id, title, order_number 
        FROM course_materials 
        WHERE course_id = ? AND order_number < ? 
        ORDER BY order_number DESC 
        LIMIT 1
    ");
    $stmt->execute([$material['course_id'], $material['order_number']]);
    $prev_material = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT material_id, title, order_number 
        FROM course_materials 
        WHERE course_id = ? AND order_number > ? 
        ORDER BY order_number ASC 
        LIMIT 1
    ");
    $stmt->execute([$material['course_id'], $material['order_number']]);
    $next_material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get material statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT up.user_id) as viewed_count,
            ROUND(AVG(up.progress)) as avg_progress,
            COUNT(CASE WHEN up.progress = 100 THEN 1 END) as completed_count
        FROM user_progress up
        WHERE up.material_id = ?
    ");
    $stmt->execute([$material_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: materials.php');
    exit();
}

// Page title
$page_title = $material['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Instructor Panel</title>
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
                            <a href="courses.php" class="nav-link">
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
                            <a href="materials.php" class="nav-link active">
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
                <div class="container-fluid py-4">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="materials.php">Materials</a></li>
                            <li class="breadcrumb-item"><a href="course-details.php?id=<?php echo $material['course_id']; ?>"><?php echo htmlspecialchars($material['course_title']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($material['title']); ?></li>
                        </ol>
                    </nav>
                    
                    <!-- Action Bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0"><?php echo htmlspecialchars($material['title']); ?></h1>
                        <div class="btn-group">
                            <a href="material-form.php?id=<?php echo $material_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Edit Material
                            </a>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Material Card -->
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Material Content</h5>
                                        <span class="badge bg-primary">Order: <?php echo $material['order_number']; ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($material['description'])): ?>
                                        <div class="mb-4">
                                            <h6 class="fw-bold">Description:</h6>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($material['description'])); ?></p>
                                        </div>
                                        <hr>
                                    <?php endif; ?>
                                    
                                    <!-- Material Content Preview -->
                                    <div class="material-content mb-4">
                                        <?php echo $material['content']; ?>
                                    </div>
                                    
                                    <!-- Material Files -->
                                    <?php if (!empty($material['file_path'])): ?>
                                        <hr>
                                        <div class="mb-4">
                                            <h6 class="fw-bold">Attached Files:</h6>
                                            <div class="d-grid gap-2">
                                                <a href="<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download me-2"></i>Download Material
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white">
                                    <!-- Material Navigation -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if ($prev_material): ?>
                                            <a href="material.php?id=<?php echo $prev_material['material_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-chevron-left me-2"></i>Previous: <?php echo htmlspecialchars(substr($prev_material['title'], 0, 20)) . (strlen($prev_material['title']) > 20 ? '...' : ''); ?>
                                            </a>
                                        <?php else: ?>
                                            <div></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($next_material): ?>
                                            <a href="material.php?id=<?php echo $next_material['material_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                Next: <?php echo htmlspecialchars(substr($next_material['title'], 0, 20)) . (strlen($next_material['title']) > 20 ? '...' : ''); ?><i class="fas fa-chevron-right ms-2"></i>
                                            </a>
                                        <?php else: ?>
                                            <div></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Material Statistics -->
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="fs-4 fw-bold"><?php echo $stats['viewed_count'] ?: 0; ?></div>
                                                <div class="small text-muted">Students Viewed</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="fs-4 fw-bold"><?php echo $stats['completed_count'] ?: 0; ?></div>
                                                <div class="small text-muted">Completed</div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="text-center">
                                                <div class="fs-4 fw-bold"><?php echo $stats['avg_progress'] ?: 0; ?>%</div>
                                                <div class="small text-muted">Average Progress</div>
                                                <div class="progress mt-2" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $stats['avg_progress'] ?: 0; ?>%;" 
                                                         aria-valuenow="<?php echo $stats['avg_progress'] ?: 0; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Course Information -->
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Course Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="fw-bold">Course:</h6>
                                        <p class="mb-0">
                                            <a href="course-details.php?id=<?php echo $material['course_id']; ?>">
                                                <?php echo htmlspecialchars($material['course_title']); ?>
                                            </a>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <h6 class="fw-bold">Total Materials:</h6>
                                        <p class="mb-0"><?php echo $material['total_materials']; ?></p>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold">Created At:</h6>
                                        <p class="mb-0"><?php echo date('F j, Y', strtotime($material['created_at'])); ?></p>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <a href="materials.php?course_id=<?php echo $material['course_id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-list me-2"></i>All Course Materials
                                        </a>
                                        <a href="course-details.php?id=<?php echo $material['course_id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-info-circle me-2"></i>Course Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 