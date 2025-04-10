<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : '';

// Handle material deletion
if (isset($_POST['delete_material'])) {
    $material_id = (int)$_POST['material_id'];
    
    try {
        // First delete associated user progress due to foreign key constraint
        $stmt = $conn->prepare("DELETE FROM user_progress WHERE material_id = ?");
        $stmt->execute([$material_id]);
        
        // Then delete the material
        $stmt = $conn->prepare("DELETE FROM course_materials WHERE material_id = ?");
        $stmt->execute([$material_id]);
        
        $_SESSION['success_message'] = "Material deleted successfully.";
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error deleting material.";
    }
    
    header('Location: materials.php' . ($search ? "?search=$search" : '') . ($course_filter ? "&course=$course_filter" : ''));
    exit();
}

// Handle order number updates
if (isset($_POST['update_order'])) {
    $material_id = (int)$_POST['material_id'];
    $new_order = (int)$_POST['new_order'];
    $course_id = (int)$_POST['course_id'];
    
    try {
        // Validate order number
        if ($new_order <= 0) {
            throw new Exception("Order number must be greater than zero.");
        }
        
        $conn->beginTransaction();
        
        // Check if the new order number already exists for this course
        $stmt = $conn->prepare("
            SELECT material_id FROM course_materials 
            WHERE course_id = ? AND order_number = ? AND material_id != ?
        ");
        $stmt->execute([$course_id, $new_order, $material_id]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Order number already exists for this course.");
        }
        
        // Update the order number
        $stmt = $conn->prepare("
            UPDATE course_materials 
            SET order_number = ? 
            WHERE material_id = ? AND course_id = ?
        ");
        $stmt->execute([$new_order, $material_id, $course_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Order number updated successfully.";
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: materials.php' . ($search ? "?search=$search" : '') . ($course_filter ? "&course=$course_filter" : ''));
    exit();
}

// Handle course assignment
if (isset($_POST['assign_course'])) {
    $material_id = (int)$_POST['material_id'];
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    
    try {
        $conn->beginTransaction();
        
        // Get the next available order number for the course
        if ($course_id) {
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(order_number), 0) + 1 as next_order 
                FROM course_materials 
                WHERE course_id = ?
            ");
            $stmt->execute([$course_id]);
            $next_order = $stmt->fetch()['next_order'];
        } else {
            $next_order = null;
        }
        
        // Update the course assignment and order number
        $stmt = $conn->prepare("
            UPDATE course_materials 
            SET course_id = ?, order_number = ? 
            WHERE material_id = ?
        ");
        $stmt->execute([$course_id, $next_order, $material_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Course assignment updated successfully.";
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error updating course assignment.";
    }
    
    header('Location: materials.php' . ($search ? "?search=$search" : '') . ($course_filter ? "&course=$course_filter" : ''));
    exit();
}

// Get all courses for dropdown
try {
    $stmt = $conn->query("SELECT course_id, title FROM courses ORDER BY title");
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $courses = [];
}

// Build query for materials with filters
$query = "
    SELECT m.*, c.title as course_title 
    FROM course_materials m 
    LEFT JOIN courses c ON m.course_id = c.course_id 
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (m.title LIKE ? OR m.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($course_filter) {
    $query .= " AND m.course_id = ?";
    $params[] = $course_filter;
}

$query .= " ORDER BY m.course_id, m.order_number";

// Get filtered materials
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
} catch(PDOException $e) {
    $materials = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Materials - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
                    <div>
                        <h1 class="h3 mb-2">Course Materials</h1>
                        <p class="text-muted mb-0">Manage all course materials and their assignments</p>
                    </div>
                    <a href="material-form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Material
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

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search materials...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                    <a href="materials.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Clear Filters
                                    </a>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Course Filter</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="?<?php echo $search ? "search=" . urlencode($search) . "&" : ""; ?>" 
                                       class="btn <?php echo !$course_filter ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        All Courses
                                    </a>
                                    <?php foreach ($courses as $course): ?>
                                        <a href="?<?php echo $search ? "search=" . urlencode($search) . "&" : ""; ?>course=<?php echo $course['course_id']; ?>" 
                                           class="btn <?php echo $course_filter == $course['course_id'] ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="materialsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Course</th>
                                        <th>Order</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materials as $material): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($material['title']); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="material_id" value="<?php echo $material['material_id']; ?>">
                                                    <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                                        <!-- <option value="">General Material</option> -->
                                                        <?php foreach ($courses as $course): ?>
                                                            <option value="<?php echo $course['course_id']; ?>" 
                                                                    <?php echo $material['course_id'] == $course['course_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($course['title']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" name="assign_course" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <?php if ($material['course_id']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="material_id" value="<?php echo $material['material_id']; ?>">
                                                        <input type="hidden" name="course_id" value="<?php echo $material['course_id']; ?>">
                                                        <input type="number" name="new_order" class="form-control form-control-sm d-inline-block" 
                                                               style="width: 80px;" value="<?php echo $material['order_number']; ?>"
                                                               min="1" onchange="this.form.submit()">
                                                        <input type="hidden" name="update_order" value="1">
                                                    </form>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($material['description'], 0, 100)) . '...'; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="material-form.php?id=<?php echo $material['material_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $material['material_id']; ?>">
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

    <!-- Delete Modals -->
    <?php foreach ($materials as $material): ?>
        <div class="modal fade" id="deleteModal<?php echo $material['material_id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this material?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="material_id" value="<?php echo $material['material_id']; ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_material" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#materialsTable').DataTable({
                order: [[2, 'asc']], // Sort by order number by default
                pageLength: 25,
                language: {
                    search: "Search materials:"
                }
            });
        });
    </script>
</body>
</html> 