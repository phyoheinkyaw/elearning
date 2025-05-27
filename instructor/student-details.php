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

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id <= 0) {
    $_SESSION['error_message'] = "Invalid student ID.";
    header('Location: students.php');
    exit();
}

// Check if the student is enrolled in any of the instructor's courses
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as is_enrolled
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.course_id
        WHERE ce.user_id = ? AND c.instructor_id = ?
    ");
    $stmt->execute([$student_id, $instructor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || $result['is_enrolled'] == 0) {
        $_SESSION['error_message'] = "This student is not enrolled in any of your courses.";
        header('Location: students.php');
        exit();
    }
    
    // Get student details
    $stmt = $conn->prepare("
        SELECT u.*, up.* 
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $_SESSION['error_message'] = "Student not found.";
        header('Location: students.php');
        exit();
    }
    
    // Get courses the student is enrolled in (limited to this instructor's courses)
    $stmt = $conn->prepare("
        SELECT c.*, 
               ce.enrolled_at,
               (
                   SELECT COUNT(*) 
                   FROM course_materials 
                   WHERE course_id = c.course_id
               ) as total_materials,
               (
                   SELECT COUNT(*) 
                   FROM user_progress up
                   JOIN course_materials cm ON up.material_id = cm.material_id
                   WHERE cm.course_id = c.course_id AND up.user_id = ? AND up.progress = 100
               ) as completed_materials,
               (
                   SELECT ROUND(AVG(up.progress))
                   FROM user_progress up
                   JOIN course_materials cm ON up.material_id = cm.material_id
                   WHERE cm.course_id = c.course_id AND up.user_id = ?
               ) as avg_progress
        FROM courses c
        JOIN course_enrollments ce ON c.course_id = ce.course_id
        WHERE ce.user_id = ? AND c.instructor_id = ?
        ORDER BY ce.enrolled_at DESC
    ");
    $stmt->execute([$student_id, $student_id, $student_id, $instructor_id]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activity
    $stmt = $conn->prepare("
        SELECT up.*, cm.title as material_title, c.title as course_title, c.course_id
        FROM user_progress up
        JOIN course_materials cm ON up.material_id = cm.material_id
        JOIN courses c ON cm.course_id = c.course_id
        WHERE up.user_id = ? AND c.instructor_id = ?
        ORDER BY up.last_accessed DESC
        LIMIT 10
    ");
    $stmt->execute([$student_id, $instructor_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overall progress stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT cm.material_id) as total_materials,
            COUNT(DISTINCT CASE WHEN up.progress = 100 THEN up.material_id END) as completed_materials,
            ROUND(AVG(up.progress)) as avg_progress,
            MAX(up.last_accessed) as last_activity
        FROM course_materials cm
        JOIN courses c ON cm.course_id = c.course_id
        LEFT JOIN user_progress up ON cm.material_id = up.material_id AND up.user_id = ?
        WHERE c.instructor_id = ?
    ");
    $stmt->execute([$student_id, $instructor_id]);
    $progress_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: students.php');
    exit();
}

// Page title
$page_title = $student['full_name'] ? $student['full_name'] : $student['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student: <?php echo htmlspecialchars($page_title); ?> - Instructor Panel</title>
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
                            <a href="students.php" class="nav-link active">
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
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page_title); ?></li>
                        </ol>
                    </nav>
                    
                    <!-- Action Bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Student Profile</h1>
                        <a href="students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Students
                        </a>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <!-- Student Profile Card -->
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Profile Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <div class="avatar-placeholder mb-3">
                                            <?php if (!empty($student['profile_picture'])): ?>
                                                <img src="<?php echo htmlspecialchars($student['profile_picture']); ?>" class="rounded-circle" width="100" height="100" alt="Profile Picture">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="width: 100px; height: 100px; font-size: 2.5rem; margin: 0 auto;">
                                                    <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="mb-0"><?php echo htmlspecialchars($student['full_name'] ?: $student['username']); ?></h4>
                                        <p class="text-muted">@<?php echo htmlspecialchars($student['username']); ?></p>
                                        
                                        <?php if ($student['proficiency_level']): ?>
                                            <span class="badge bg-<?php echo get_level_color($student['proficiency_level']); ?> fs-6 px-3 py-2">
                                                Level: <?php echo htmlspecialchars($student['proficiency_level']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-envelope me-2"></i>Email:</h6>
                                        <p><?php echo htmlspecialchars($student['email']); ?></p>
                                    </div>
                                    
                                    <?php if (!empty($student['phone'])): ?>
                                    <div class="mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-phone me-2"></i>Phone:</h6>
                                        <p><?php echo htmlspecialchars($student['phone']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-calendar me-2"></i>Joined:</h6>
                                        <p><?php echo date('F j, Y', strtotime($student['created_at'])); ?></p>
                                    </div>
                                    
                                    <?php if (!empty($student['bio'])): ?>
                                    <div class="mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i>Bio:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($student['bio'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Progress Overview Card -->
                            <div class="card mt-4">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Learning Progress</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="fs-4 fw-bold">
                                                    <?php echo count($enrolled_courses); ?>
                                                </div>
                                                <div class="small text-muted">Enrolled Courses</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="fs-4 fw-bold">
                                                    <?php
                                                    if ($progress_stats['total_materials'] > 0) {
                                                        echo round(($progress_stats['completed_materials'] / $progress_stats['total_materials']) * 100) . '%';
                                                    } else {
                                                        echo '0%';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="small text-muted">Completion Rate</div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="text-center">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="fs-4 fw-bold me-2"><?php echo $progress_stats['avg_progress'] ?: 0; ?>%</div>
                                                    <div class="text-muted">(Avg. Progress)</div>
                                                </div>
                                                <div class="progress mt-2" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $progress_stats['avg_progress'] ?: 0; ?>%;" 
                                                         aria-valuenow="<?php echo $progress_stats['avg_progress'] ?: 0; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($progress_stats['last_activity']): ?>
                                        <div class="col-12 text-center mt-3">
                                            <div class="small text-muted">Last Activity:</div>
                                            <div><?php echo date('M d, Y h:i A', strtotime($progress_stats['last_activity'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-8">
                            <!-- Enrolled Courses -->
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Enrolled Courses</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($enrolled_courses)): ?>
                                        <p class="text-center text-muted">No courses enrolled.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Course</th>
                                                        <th>Progress</th>
                                                        <th>Enrolled On</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($enrolled_courses as $course): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($course['title']); ?></div>
                                                                <div class="small text-muted">
                                                                    <?php
                                                                    if ($course['total_materials'] > 0) {
                                                                        echo $course['completed_materials'] . '/' . $course['total_materials'] . ' materials completed';
                                                                    } else {
                                                                        echo 'No materials';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="progress" style="width: 100px; height: 8px;">
                                                                        <div class="progress-bar" role="progressbar" 
                                                                             style="width: <?php echo $course['avg_progress'] ?: 0; ?>%;" 
                                                                             aria-valuenow="<?php echo $course['avg_progress'] ?: 0; ?>" 
                                                                             aria-valuemin="0" 
                                                                             aria-valuemax="100">
                                                                        </div>
                                                                    </div>
                                                                    <span class="ms-2"><?php echo $course['avg_progress'] ?: 0; ?>%</span>
                                                                </div>
                                                            </td>
                                                            <td><?php echo date('M d, Y', strtotime($course['enrolled_at'])); ?></td>
                                                            <td>
                                                                <a href="course-details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-eye"></i> View Course
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Recent Activity -->
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">Recent Activity</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_activity)): ?>
                                        <p class="text-center text-muted">No recent activity recorded.</p>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($recent_activity as $activity): ?>
                                                <div class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['material_title']); ?></h6>
                                                        <small><?php echo date('M d, Y h:i A', strtotime($activity['last_accessed'])); ?></small>
                                                    </div>
                                                    <p class="mb-1">
                                                        <a href="course-details.php?id=<?php echo $activity['course_id']; ?>">
                                                            <?php echo htmlspecialchars($activity['course_title']); ?>
                                                        </a>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            Progress: <?php echo $activity['progress']; ?>%
                                                            <?php if ($activity['progress'] == 100): ?>
                                                                <span class="badge bg-success ms-2">Completed</span>
                                                            <?php endif; ?>
                                                        </small>
                                                        <a href="material.php?id=<?php echo $activity['material_id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    </div>
                                                    <div class="progress mt-2" style="height: 5px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?php echo $activity['progress']; ?>%;" 
                                                             aria-valuenow="<?php echo $activity['progress']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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