<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle resource deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $resource_id = (int)$_GET['delete'];
    
    try {
        // First get the file path to delete the physical file
        $stmt = $conn->prepare("SELECT file_path FROM resource_library WHERE resource_id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();
        
        if ($resource) {
            // Delete the physical file if it exists
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $resource['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete the database record
            $stmt = $conn->prepare("DELETE FROM resource_library WHERE resource_id = ?");
            $stmt->execute([$resource_id]);
            
            $_SESSION['success_message'] = "Resource deleted successfully.";
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error deleting resource.";
    }
    
    header('Location: resources.php');
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$level = isset($_GET['level']) ? sanitize_input($_GET['level']) : '';
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : '';

// Build query
$query = "
    SELECT r.*, c.title as course_title 
    FROM resource_library r 
    LEFT JOIN courses c ON r.course_id = c.course_id 
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($level) {
    $query .= " AND r.proficiency_level = ?";
    $params[] = $level;
}

if ($type !== '') {
    $query .= " AND r.file_type = ?";
    $params[] = $type;
}

if ($course_id) {
    $query .= " AND r.course_id = ?";
    $params[] = $course_id;
}

$query .= " ORDER BY r.upload_date DESC";

// Get resources
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching resources.";
    $resources = [];
}

// Get courses for filter
try {
    $stmt = $conn->query("SELECT course_id, title FROM courses ORDER BY title");
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $courses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Library - ELearning Admin</title>
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
                    <h1 class="h3 mb-0">Resource Library</h1>
                    <a href="resource-form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Resource
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
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Search resources..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="level">
                                    <option value="">All Levels</option>
                                    <option value="A1" <?php echo $level === 'A1' ? 'selected' : ''; ?>>A1</option>
                                    <option value="A2" <?php echo $level === 'A2' ? 'selected' : ''; ?>>A2</option>
                                    <option value="B1" <?php echo $level === 'B1' ? 'selected' : ''; ?>>B1</option>
                                    <option value="B2" <?php echo $level === 'B2' ? 'selected' : ''; ?>>B2</option>
                                    <option value="C1" <?php echo $level === 'C1' ? 'selected' : ''; ?>>C1</option>
                                    <option value="C2" <?php echo $level === 'C2' ? 'selected' : ''; ?>>C2</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="type">
                                    <option value="">All Types</option>
                                    <option value="0" <?php echo $type === '0' ? 'selected' : ''; ?>>PDF</option>
                                    <option value="1" <?php echo $type === '1' ? 'selected' : ''; ?>>E-book</option>
                                    <option value="2" <?php echo $type === '2' ? 'selected' : ''; ?>>Worksheet</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="course_id">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" 
                                                <?php echo $course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover" id="resourcesTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Level</th>
                                        <th>Course</th>
                                        <th>Upload Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $icon = 'fa-file';
                                                    $color = 'text-primary';
                                                    switch($resource['file_type']) {
                                                        case 0:
                                                            $icon = 'fa-file-pdf';
                                                            $color = 'text-danger';
                                                            break;
                                                        case 1:
                                                            $icon = 'fa-book';
                                                            $color = 'text-success';
                                                            break;
                                                        case 2:
                                                            $icon = 'fa-file-alt';
                                                            $color = 'text-warning';
                                                            break;
                                                    }
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?> <?php echo $color; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($resource['title']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                switch($resource['file_type']) {
                                                    case 0:
                                                        echo 'PDF';
                                                        break;
                                                    case 1:
                                                        echo 'E-book';
                                                        break;
                                                    case 2:
                                                        echo 'Worksheet';
                                                        break;
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($resource['proficiency_level']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $resource['course_title'] ? htmlspecialchars($resource['course_title']) : 'General Resource'; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="<?php echo $resource['file_path']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <a href="resource-form.php?id=<?php echo $resource['resource_id']; ?>" 
                                                       class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $resource['resource_id']; ?>)">
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
                    Are you sure you want to delete this resource? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteButton" class="btn btn-danger">Delete</a>
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
            $('#resourcesTable').DataTable({
                order: [[4, 'desc']], // Sort by upload date by default
                pageLength: 25
            });
        });

        // Delete confirmation
        function confirmDelete(resourceId) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            document.getElementById('deleteButton').href = `resources.php?delete=${resourceId}`;
            modal.show();
        }
    </script>
</body>
</html> 