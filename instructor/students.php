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

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Check if a course filter is applied
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : null;

// Get instructor's courses for the filter dropdown
$stmt = $conn->prepare("
    SELECT course_id, title
    FROM courses 
    WHERE instructor_id = ?
    ORDER BY title
");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total number of students
$count_sql = "
    SELECT COUNT(DISTINCT u.user_id) as total
    FROM users u
    JOIN course_enrollments ce ON u.user_id = ce.user_id
    JOIN courses c ON ce.course_id = c.course_id
    WHERE c.instructor_id = ?
";
$count_params = [$instructor_id];

if ($course_filter) {
    $count_sql .= " AND c.course_id = ?";
    $count_params[] = $course_filter;
}

$stmt = $conn->prepare($count_sql);
$stmt->execute($count_params);
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$total_pages = ceil($total_students / $per_page);

// Get students with pagination
$students_sql = "
    SELECT DISTINCT 
        u.user_id,
        u.username,
        u.email,
        COALESCE(up.full_name, u.username) as full_name,
        up.proficiency_level,
        (
            SELECT COUNT(DISTINCT ce2.course_id) 
            FROM course_enrollments ce2 
            JOIN courses c2 ON ce2.course_id = c2.course_id 
            WHERE ce2.user_id = u.user_id AND c2.instructor_id = ?
        ) as enrolled_courses,
        (
            SELECT MAX(ce3.enrolled_at) 
            FROM course_enrollments ce3 
            JOIN courses c3 ON ce3.course_id = c3.course_id 
            WHERE ce3.user_id = u.user_id AND c3.instructor_id = ?
        ) as last_enrolled,
        (
            SELECT ROUND(AVG(up2.progress)) 
            FROM user_progress up2 
            JOIN course_materials cm ON up2.material_id = cm.material_id 
            JOIN courses c4 ON cm.course_id = c4.course_id 
            WHERE up2.user_id = u.user_id AND c4.instructor_id = ?
        ) as avg_progress
    FROM users u
    JOIN course_enrollments ce ON u.user_id = ce.user_id
    JOIN courses c ON ce.course_id = c.course_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE c.instructor_id = ?
";

$students_params = [$instructor_id, $instructor_id, $instructor_id, $instructor_id];

if ($course_filter) {
    $students_sql .= " AND c.course_id = ?";
    $students_params[] = $course_filter;
}

$students_sql .= " 
    ORDER BY last_enrolled DESC
    LIMIT ? OFFSET ?
";
$students_params[] = $per_page;
$students_params[] = $offset;

$stmt = $conn->prepare($students_sql);
$stmt->execute($students_params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$page_title = "Students";
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
                <div class="container-fluid">
                    <h1 class="mb-4"><?php echo $page_title; ?></h1>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="students.php" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="course" class="form-label">Filter by Course</label>
                                    <select name="course" id="course" class="form-select">
                                        <option value="">All Courses</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['course_id']; ?>" 
                                                    <?php echo $course_filter == $course['course_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                                    <?php if ($course_filter): ?>
                                        <a href="students.php" class="btn btn-outline-secondary ms-2">Clear Filter</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Students Table -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Student List</h5>
                                <span class="badge bg-primary"><?php echo $total_students; ?> Students</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($students)): ?>
                                <p class="text-center text-muted my-4">No students enrolled in your courses.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Email</th>
                                                <th>Level</th>
                                                <th>Courses</th>
                                                <th>Progress</th>
                                                <th>Last Enrolled</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($student['username']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                    <td>
                                                        <?php if ($student['proficiency_level']): ?>
                                                            <span class="badge bg-<?php echo get_level_color($student['proficiency_level']); ?>">
                                                                <?php echo htmlspecialchars($student['proficiency_level']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Not Set</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $student['enrolled_courses']; ?></td>
                                                    <td>
                                                        <?php if ($student['avg_progress'] !== null): ?>
                                                            <div class="d-flex align-items-center">
                                                                <div class="progress" style="width: 80px; height: 8px;">
                                                                    <div class="progress-bar" role="progressbar" 
                                                                         style="width: <?php echo $student['avg_progress']; ?>%;" 
                                                                         aria-valuenow="<?php echo $student['avg_progress']; ?>" 
                                                                         aria-valuemin="0" 
                                                                         aria-valuemax="100">
                                                                    </div>
                                                                </div>
                                                                <span class="ms-2"><?php echo $student['avg_progress']; ?>%</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">No data</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($student['last_enrolled']): ?>
                                                            <?php echo date('M d, Y', strtotime($student['last_enrolled'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="student-details.php?id=<?php echo $student['user_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Student list pagination" class="mt-4">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $course_filter ? '&course=' . $course_filter : ''; ?>">
                                                        <i class="fas fa-chevron-left"></i> Previous
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                            // Calculate range of page numbers to display
                                            $range = 2; // Show 2 pages before and after current page
                                            $start_page = max(1, $page - $range);
                                            $end_page = min($total_pages, $page + $range);
                                            
                                            // Always show first page
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?page=1' . ($course_filter ? '&course=' . $course_filter : '') . '">1</a></li>';
                                                if ($start_page > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            // Show page numbers
                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                                                echo '<a class="page-link" href="?page=' . $i . ($course_filter ? '&course=' . $course_filter : '') . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            
                                            // Always show last page
                                            if ($end_page < $total_pages) {
                                                if ($end_page < $total_pages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . ($course_filter ? '&course=' . $course_filter : '') . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $course_filter ? '&course=' . $course_filter : ''; ?>">
                                                        Next <i class="fas fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Next <i class="fas fa-chevron-right"></i></span>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
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