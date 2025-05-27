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
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Build query conditions
$conditions = ["c.instructor_id = ?"]; 
$params = [$instructor_id];

if (!empty($search)) {
    $conditions[] = "(cm.title LIKE ? OR cm.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($course_filter > 0) {
    $conditions[] = "cm.course_id = ?";
    $params[] = $course_filter;
}

$where_clause = implode(' AND ', $conditions);

// Get total materials count
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM course_materials cm
    JOIN courses c ON cm.course_id = c.course_id
    WHERE $where_clause
");
$stmt->execute($params);
$total_materials = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$total_pages = ceil($total_materials / $per_page);

// Get instructor's materials with course info
$stmt = $conn->prepare("
    SELECT 
        cm.*, 
        c.title as course_title,
        (SELECT COUNT(DISTINCT up.user_id) FROM user_progress up WHERE up.material_id = cm.material_id) as viewed_count,
        (SELECT ROUND(AVG(up.progress)) FROM user_progress up WHERE up.material_id = cm.material_id) as avg_progress
    FROM course_materials cm
    JOIN courses c ON cm.course_id = c.course_id
    WHERE $where_clause
    ORDER BY c.title, cm.order_number
    LIMIT ? OFFSET ?
");

// Add pagination parameters
$query_params = array_merge($params, [$per_page, $offset]);
$stmt->execute($query_params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get instructor's courses for filter dropdown
$stmt = $conn->prepare("
    SELECT course_id, title 
    FROM courses 
    WHERE instructor_id = ? 
    ORDER BY title
");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$page_title = "Materials Management";
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
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><?php echo $page_title; ?></h1>
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
                    
                    <!-- Filter and Search -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search Materials</label>
                                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title or description">
                                </div>
                                <div class="col-md-6">
                                    <label for="course_id" class="form-label">Course</label>
                                    <select class="form-select" id="course_id" name="course_id">
                                        <option value="0">All Courses</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['course_id']; ?>" <?php echo $course_filter === (int)$course['course_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Materials List -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">All Materials</h5>
                            <div class="dropdown">
                                <button class="btn btn-primary dropdown-toggle" type="button" id="newMaterialDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-plus"></i> Add New Material
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="newMaterialDropdown">
                                    <?php foreach ($courses as $course): ?>
                                        <li>
                                            <a class="dropdown-item" href="material-form.php?course_id=<?php echo $course['course_id']; ?>">
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($materials)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-0">No materials found. Try changing your search criteria or add new materials to your courses.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Course</th>
                                                <th>Order</th>
                                                <th>Viewed By</th>
                                                <th>Avg. Progress</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($materials as $material): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($material['title']); ?></div>
                                                        <?php if (!empty($material['description'])): ?>
                                                            <div class="small text-muted"><?php echo htmlspecialchars(substr($material['description'], 0, 60)) . (strlen($material['description']) > 60 ? '...' : ''); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="course-details.php?id=<?php echo $material['course_id']; ?>">
                                                            <?php echo htmlspecialchars($material['course_title']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo $material['order_number']; ?></td>
                                                    <td>
                                                        <?php echo $material['viewed_count']; ?>
                                                        <?php if ($material['viewed_count'] > 0): ?>
                                                            <div class="progress mt-1" style="height: 5px; width: 60px">
                                                                <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, $material['viewed_count'] * 10); ?>%"></div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $material['avg_progress'] ? $material['avg_progress'] . '%' : 'N/A'; ?>
                                                        <?php if ($material['avg_progress']): ?>
                                                            <div class="progress mt-1" style="height: 5px; width: 60px">
                                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $material['avg_progress']; ?>%"></div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="material-form.php?id=<?php echo $material['material_id']; ?>" class="btn btn-outline-secondary">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <?php if (isset($material['type']) && $material['type'] === 'quiz'): ?>
                                                                <a href="quiz-questions.php?material_id=<?php echo $material['material_id']; ?>" class="btn btn-outline-warning">
                                                                    <i class="fas fa-question-circle"></i> Questions
                                                                </a>
                                                            <?php endif; ?>
                                                            <a href="material.php?id=<?php echo $material['material_id']; ?>" class="btn btn-outline-primary">
                                                                <i class="fas fa-eye"></i> View
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
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Material list pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&course_id=<?php echo $course_filter; ?>">
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
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&course_id=' . $course_filter . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                // Show page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&course_id=' . $course_filter . '">' . $i . '</a>';
                                    echo '</li>';
                                }
                                
                                // Always show last page
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&course_id=' . $course_filter . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&course_id=<?php echo $course_filter; ?>">
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 