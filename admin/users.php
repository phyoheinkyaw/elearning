<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = "User deleted successfully.";
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error deleting user.";
    }
    header('Location: users.php');
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';
$level_filter = isset($_GET['level']) ? sanitize_input($_GET['level']) : '';

// Prepare base query
$query = "
    SELECT u.*, up.full_name, up.proficiency_level 
    FROM users u 
    LEFT JOIN user_profiles up ON u.user_id = up.user_id 
    WHERE 1=1
";
$params = [];

// Add search condition
if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR up.full_name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

// Add role filter
if ($role_filter !== '') {
    $query .= " AND u.role = ?";
    $params[] = (int)$role_filter;
}

// Add level filter
if ($level_filter) {
    $query .= " AND up.proficiency_level = ?";
    $params[] = $level_filter;
}

// Add sorting
$query .= " ORDER BY u.created_at DESC";

// Execute query
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching users.";
    $users = [];
}

// Get available levels for filter
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - ELearning Admin</title>
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
                    <h1 class="h3 mb-0">Users Management</h1>
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
                            <div class="col-md-6">
                                <select class="form-select" name="role">
                                    <option value="">All Roles</option>
                                    <option value="0" <?php echo $role_filter === '0' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="1" <?php echo $role_filter === '1' ? 'selected' : ''; ?>>Student</option>
                                    <option value="2" <?php echo $role_filter === '2' ? 'selected' : ''; ?>>Instructor</option>
                                </select>
                            </div>
                            <div class="col-md-4">
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

                        <!-- Users Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Level</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo (int)$user['role'] === 0 ? 'danger' : 
                                                    ((int)$user['role'] === 1 ? 'primary' : 'success'); 
                                            ?>">
                                                <?php 
                                                    echo (int)$user['role'] === 0 ? 'Admin' : 
                                                        ((int)$user['role'] === 1 ? 'Student' : 'Instructor'); 
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['proficiency_level']): ?>
                                                <span class="badge bg-success"><?php echo $user['proficiency_level']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not tested</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="user-form.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($user['role'] !== 0): ?>
                                                <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
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
                    Are you sure you want to delete user <span id="deleteUserName" class="fw-bold"></span>?
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
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
            $('#usersTable').DataTable({
                pageLength: 25,
                order: [[5, 'desc']], // Sort by joined date by default
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rtip'
            });
        });

        // Delete confirmation
        function confirmDelete(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
