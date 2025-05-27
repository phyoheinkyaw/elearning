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

// Get course ID
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no course ID, redirect to courses page
if (!$course_id) {
    header('Location: courses.php');
    exit();
}

// Check if course exists and belongs to this instructor
try {
    $stmt = $conn->prepare("
        SELECT * FROM courses WHERE course_id = ? AND instructor_id = ?
    ");
    $stmt->execute([$course_id, $instructor_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        $_SESSION['error_message'] = "Course not found or you don't have permission to view it.";
        header('Location: courses.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching course data.";
    header('Location: courses.php');
    exit();
}

// Get course materials
try {
    $stmt = $conn->prepare("
        SELECT 
            cm.*, 
            (SELECT COUNT(DISTINCT up.user_id) FROM user_progress up WHERE up.material_id = cm.material_id) as viewed_count,
            (SELECT ROUND(AVG(up.progress)) FROM user_progress up WHERE up.material_id = cm.material_id) as avg_progress
        FROM course_materials cm
        WHERE cm.course_id = ?
        ORDER BY cm.order_number
    ");
    $stmt->execute([$course_id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $materials = [];
}

// Get enrollment statistics
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM course_enrollments WHERE course_id = ?
    ");
    $stmt->execute([$course_id]);
    $enrollment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch(PDOException $e) {
    $enrollment_count = 0;
}

// Handle material reordering via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    $material_id = (int)$_POST['material_id'];
    $new_order = (int)$_POST['new_order'];
    
    try {
        $stmt = $conn->prepare("
            UPDATE course_materials SET order_number = ? 
            WHERE material_id = ? AND course_id = ?
        ");
        $stmt->execute([$new_order, $material_id, $course_id]);
        
        // Return success response for AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } catch(PDOException $e) {
        // Return error response for AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Page title
$page_title = "Course Details: " . $course['title'];
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
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0"><?php echo htmlspecialchars($course['title']); ?></h1>
                            <div class="text-muted">
                                <span class="badge bg-info me-2"><?php echo $course['level']; ?></span>
                                <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($course['difficulty_level']); ?></span>
                                <span class="me-3"><?php echo htmlspecialchars($course['duration']); ?></span>
                                <span><i class="fas fa-users me-1"></i> <?php echo $enrollment_count; ?> students enrolled</span>
                            </div>
                        </div>
                        <div class="d-flex">
                            <a href="courses.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                            <a href="course-form.php?id=<?php echo $course_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-1"></i> Edit Course
                            </a>
                        </div>
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
                    
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <!-- Course Description -->
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Course Description</h5>
                                </div>
                                <div class="card-body">
                                    <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                                </div>
                            </div>
                            
                            <!-- Course Materials -->
                            <div class="card">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Course Materials</h5>
                                    <a href="material-form.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Add Material
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($materials)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                            <p class="mb-0">No materials have been added to this course yet.</p>
                                            <a href="material-form.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary mt-3">
                                                <i class="fas fa-plus me-1"></i> Add First Material
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th width="70">#</th>
                                                        <th>Title</th>
                                                        <th>Views</th>
                                                        <th>Avg. Progress</th>
                                                        <th width="150">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="materialsList">
                                                    <?php foreach ($materials as $material): ?>
                                                        <tr data-material-id="<?php echo $material['material_id']; ?>">
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <span class="me-2 drag-handle cursor-grab"><i class="fas fa-grip-vertical text-muted"></i></span>
                                                                    <span class="order-number"><?php echo $material['order_number']; ?></span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($material['title']); ?></div>
                                                                <div class="small text-muted"><?php echo mb_strimwidth(htmlspecialchars($material['description']), 0, 60, '...'); ?></div>
                                                            </td>
                                                            <td>
                                                                <?php echo $material['viewed_count'] ?? 0; ?>
                                                                <?php if (!empty($material['viewed_count'])): ?>
                                                                    <div class="progress mt-1" style="height: 4px; width: 60px;">
                                                                        <div class="progress-bar" style="width: <?php echo min(100, $material['viewed_count'] * 5); ?>%"></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                    echo !empty($material['avg_progress']) ? $material['avg_progress'] . '%' : 'N/A';
                                                                    
                                                                    if (!empty($material['avg_progress'])):
                                                                ?>
                                                                    <div class="progress mt-1" style="height: 4px; width: 60px;">
                                                                        <div class="progress-bar bg-success" style="width: <?php echo $material['avg_progress']; ?>%"></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <a href="material.php?id=<?php echo $material['material_id']; ?>" class="btn btn-outline-primary" target="_blank">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <a href="material-form.php?id=<?php echo $material['material_id']; ?>" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Course Information -->
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Course Information</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span>Created</span>
                                            <span><?php echo date('M d, Y', strtotime($course['created_at'])); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span>Last Updated</span>
                                            <span><?php echo date('M d, Y', strtotime($course['updated_at'] ?? $course['created_at'])); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span>Duration</span>
                                            <span><?php echo htmlspecialchars($course['duration'] ?? 'Not specified'); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span>Level</span>
                                            <span><?php echo htmlspecialchars($course['level']); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span>Difficulty</span>
                                            <span><?php echo htmlspecialchars($course['difficulty_level']); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span>Materials</span>
                                            <span><?php echo count($materials); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span>Students Enrolled</span>
                                            <span><?php echo $enrollment_count; ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Thumbnail -->
                            <?php if (!empty($course['thumbnail_url'])): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-white">
                                        <h5 class="card-title mb-0">Course Thumbnail</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="img-fluid rounded">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Sortable.js -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Sortable
            if (document.getElementById('materialsList')) {
                new Sortable(document.getElementById('materialsList'), {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function(evt) {
                        // Update order numbers visually
                        $('#materialsList tr').each(function(index) {
                            $(this).find('.order-number').text(index + 1);
                        });
                        
                        // Save the new order
                        const materialId = $(evt.item).data('material-id');
                        const newPosition = evt.newIndex + 1;
                        
                        $.post('course-details.php?id=<?php echo $course_id; ?>', {
                            action: 'reorder',
                            material_id: materialId,
                            new_order: newPosition
                        }).done(function(response) {
                            if (!response.success) {
                                console.error('Error reordering materials');
                            }
                        }).fail(function() {
                            console.error('AJAX request failed');
                        });
                    }
                });
            }
        });
    </script>
    <style>
        .cursor-grab {
            cursor: grab;
        }
    </style>
</body>
</html> 