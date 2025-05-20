<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle course deletion
if (isset($_POST['delete_course'])) {
    $course_id = (int)$_POST['course_id'];
    try {
        $conn->beginTransaction();
        
        // Delete course materials first (due to foreign key constraints)
        $stmt = $conn->prepare("DELETE FROM course_materials WHERE course_id = ?");
        $stmt->execute([$course_id]);
        
        // Delete course enrollments
        $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE course_id = ?");
        $stmt->execute([$course_id]);
        
        // Delete the course
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Course deleted successfully.";
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error deleting course.";
    }
    header('Location: courses.php');
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$level_filter = isset($_GET['level']) ? sanitize_input($_GET['level']) : '';
$instructor_filter = isset($_GET['instructor']) ? (int)sanitize_input($_GET['instructor']) : '';

// Prepare base query
$query = "
    SELECT c.*, 
           u.username as instructor_name,
           up.full_name as instructor_full_name,
           (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.course_id) as enrollment_count
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE 1=1
";
$params = [];

// Add search condition
if ($search) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

// Add level filter
if ($level_filter) {
    $query .= " AND c.level = ?";
    $params[] = $level_filter;
}

// Add instructor filter
if ($instructor_filter) {
    $query .= " AND c.instructor_id = ?";
    $params[] = $instructor_filter;
}

// Add sorting
$query .= " ORDER BY c.created_at DESC";

// Execute query
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching courses.";
    $courses = [];
}

// Get available levels
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

// Get instructors for filter
try {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, up.full_name
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE u.role = 2
        ORDER BY up.full_name
    ");
    $stmt->execute();
    $instructors = $stmt->fetchAll();
} catch(PDOException $e) {
    $instructors = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses Management - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="../js/lib/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Courses Management</h1>
                    <a href="course-form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Course
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

                <div class="card">
                    <div class="card-body">
                        <!-- Filters -->
                        <form class="row g-3 mb-4" method="GET">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="instructor">
                                    <option value="">All Instructors</option>
                                    <?php foreach ($instructors as $instructor): ?>
                                        <option value="<?php echo $instructor['user_id']; ?>" 
                                            <?php echo $instructor_filter === $instructor['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($instructor['full_name'] ?: $instructor['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="level">
                                    <option value="">All Levels</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level; ?>" <?php echo $level_filter === $level ? 'selected' : ''; ?>>
                                            <?php echo $level; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>

                        <!-- Courses Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="coursesTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Instructor</th>
                                        <th>Level</th>
                                        <th>Enrollments</th>
                                        <th>Created Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($course['thumbnail_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" 
                                                         class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-secondary rounded me-2" style="width: 40px; height: 40px;"></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($course['title']); ?></div>
                                                    <small class="text-muted"><?php echo mb_strimwidth(htmlspecialchars($course['description']), 0, 50, "..."); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($course['instructor_full_name'] ?: $course['instructor_name']); ?></td>
                                        <td><span class="badge bg-info"><?php echo $course['level']; ?></span></td>
                                        <td><?php echo $course['enrollment_count']; ?> students</td>
                                        <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $course['is_featured'] ? 'success' : 'warning'; ?>">
                                                <?php echo $course['is_featured'] ? 'Featured' : 'Regular'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="course-form.php?id=<?php echo $course['course_id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="course-materials.php?course_id=<?php echo $course['course_id']; ?>" 
                                                   class="btn btn-outline-info" title="Materials">
                                                    <i class="fas fa-book"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>')">
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the course "<span id="deleteCourseName" class="fw-bold"></span>"?</p>
                    <p class="text-danger mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This will also delete all materials and enrollments associated with this course.
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="course_id" id="deleteCourseId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_course" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/lib/jquery-3.7.1.min.js"></script>
    <script src="../js/lib/jquery.dataTables.min.js"></script>
    <script src="../js/lib/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#coursesTable').DataTable({
                pageLength: 25,
                order: [[4, 'desc']], // Sort by created date by default
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rtip'
            });
        });

        // Delete confirmation
        function confirmDelete(courseId, courseTitle) {
            document.getElementById('deleteCourseId').value = courseId;
            document.getElementById('deleteCourseName').textContent = courseTitle;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html> 