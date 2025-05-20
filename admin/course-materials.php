<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Get course details
try {
    $stmt = $conn->prepare("
        SELECT c.*, u.username as instructor_name, up.full_name as instructor_full_name
        FROM courses c
        LEFT JOIN users u ON c.instructor_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE c.course_id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $_SESSION['error_message'] = "Course not found.";
        header('Location: courses.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching course data.";
    header('Location: courses.php');
    exit();
}

// Handle material deletion
if (isset($_POST['delete_material'])) {
    $material_id = (int)$_POST['material_id'];
    try {
        $conn->beginTransaction();
        
        // Delete user progress first (due to foreign key constraints)
        $stmt = $conn->prepare("DELETE FROM user_progress WHERE material_id = ?");
        $stmt->execute([$material_id]);
        
        // Delete the material
        $stmt = $conn->prepare("DELETE FROM course_materials WHERE material_id = ? AND course_id = ?");
        $stmt->execute([$material_id, $course_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Material deleted successfully.";
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error deleting material.";
    }
    header('Location: course-materials.php?course_id=' . $course_id);
    exit();
}

// Get course materials
try {
    $stmt = $conn->prepare("
        SELECT m.*,
               (SELECT COUNT(*) FROM user_progress up WHERE up.material_id = m.material_id) as student_progress_count
        FROM course_materials m
        WHERE m.course_id = ?
        ORDER BY m.order_number
    ");
    $stmt->execute([$course_id]);
    $materials = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching materials.";
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
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="/js/lib/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-2">Course Materials</h1>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($course['title']); ?> 
                            <span class="badge bg-info ms-2"><?php echo $course['level']; ?></span>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Courses
                        </a>
                        <a href="material-form.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Material
                        </a>
                    </div>
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
                        <!-- Materials Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="materialsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">Order</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Student Progress</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="sortable">
                                    <?php foreach ($materials as $material): ?>
                                    <tr data-material-id="<?php echo $material['material_id']; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="drag-handle me-2"><i class="fas fa-grip-vertical text-muted"></i></span>
                                                <span class="order-number"><?php echo $material['order_number']; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($material['title']); ?></div>
                                        </td>
                                        <td><?php echo mb_strimwidth(htmlspecialchars($material['description']), 0, 100, "..."); ?></td>
                                        <td>
                                            <?php if ($material['student_progress_count'] > 0): ?>
                                                <span class="badge bg-info">
                                                    <?php echo $material['student_progress_count']; ?> students started
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No progress yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($material['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="material-form.php?course_id=<?php echo $course_id; ?>&id=<?php echo $material['material_id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $material['material_id']; ?>, '<?php echo htmlspecialchars($material['title']); ?>')">
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
                    <p>Are you sure you want to delete the material "<span id="deleteMaterialName" class="fw-bold"></span>"?</p>
                    <p class="text-danger mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This will also delete all student progress associated with this material.
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="material_id" id="deleteMaterialId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_material" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/lib/jquery-3.7.1.min.js"></script>
    <script src="/js/lib/jquery.dataTables.min.js"></script>
    <script src="/js/lib/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#materialsTable').DataTable({
                pageLength: 25,
                order: [[0, 'asc']], // Sort by order number by default
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rtip'
            });

            // Initialize Sortable
            var sortable = new Sortable(document.querySelector('.sortable'), {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function(evt) {
                    updateOrder();
                }
            });
        });

        // Delete confirmation
        function confirmDelete(materialId, materialTitle) {
            document.getElementById('deleteMaterialId').value = materialId;
            document.getElementById('deleteMaterialName').textContent = materialTitle;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Update material order
        function updateOrder() {
            const materials = [];
            document.querySelectorAll('tr[data-material-id]').forEach((row, index) => {
                materials.push({
                    id: row.dataset.materialId,
                    order: index + 1
                });
                row.querySelector('.order-number').textContent = index + 1;
            });

            // Send order update to server
            fetch('api/update-material-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    course_id: <?php echo $course_id; ?>,
                    materials: materials
                })
            });
        }
    </script>
</body>
</html> 