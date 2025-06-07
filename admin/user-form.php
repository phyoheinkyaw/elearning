<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $user_id > 0;

// Redirect to users.php if no user_id is provided (prevents creating new users)
if (!$is_edit) {
    $_SESSION['error_message'] = "User creation has been disabled.";
    header('Location: users.php');
    exit();
}

$user = [];
$profile = [];

// Get user data if editing
if ($is_edit) {
    try {
        $stmt = $conn->prepare("
            SELECT u.*, up.full_name, up.proficiency_level, 
                   CASE WHEN u.password LIKE 'INITIAL:%' THEN SUBSTRING(u.password, 9) ELSE NULL END as initial_password
            FROM users u 
            LEFT JOIN user_profiles up ON u.user_id = up.user_id 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error_message'] = "User not found.";
            header('Location: users.php');
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error fetching user data.";
        header('Location: users.php');
        exit();
    }
}

// Function to generate a secure password
function generate_secure_password($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    // Ensure at least one of each required character type
    $password = [
        $uppercase[random_int(0, strlen($uppercase) - 1)], // 1 uppercase
        $lowercase[random_int(0, strlen($lowercase) - 1)], // 1 lowercase
        $numbers[random_int(0, strlen($numbers) - 1)],     // 1 number
        $special[random_int(0, strlen($special) - 1)],     // 1 special
    ];
    
    // Fill the rest with random characters
    $all_chars = $uppercase . $lowercase . $numbers . $special;
    for ($i = count($password); $i < $length; $i++) {
        $password[] = $all_chars[random_int(0, strlen($all_chars) - 1)];
    }
    
    // Shuffle the password array and convert to string
    shuffle($password);
    return implode('', $password);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $role = (int)sanitize_input($_POST['role']);
    $full_name = sanitize_input($_POST['full_name']);
    $proficiency_level = sanitize_input($_POST['proficiency_level']);
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    
    try {
        $conn->beginTransaction();
        
        // Only allow editing, not creating new users
        if ($is_edit) {
            // Update user basic info
            $update_fields = ["username = ?", "email = ?", "role = ?"];
            $params = [$username, $email, $role];
            
            // Add password update if provided
            if (!empty($new_password)) {
                $update_fields[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            // Add user_id at the end of params
            $params[] = $user_id;
            
            // Update user
            $stmt = $conn->prepare("
                UPDATE users 
                SET " . implode(", ", $update_fields) . "
                WHERE user_id = ?
            ");
            $stmt->execute($params);
            
            // Update or insert profile
            $stmt = $conn->prepare("
                INSERT INTO user_profiles (user_id, full_name, proficiency_level)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                proficiency_level = VALUES(proficiency_level)
            ");
            $stmt->execute([$user_id, $full_name, $proficiency_level]);
            
            $_SESSION['success_message'] = "User updated successfully.";
        } else {
            // This block should never be reached due to the earlier redirect
            $_SESSION['error_message'] = "User creation has been disabled.";
            header('Location: users.php');
            exit();
        }
        
        $conn->commit();
        header('Location: users.php');
        exit();
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Get available levels
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
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
                    <h1 class="h3 mb-0">Edit User</h1>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Users
                    </a>
                </div>

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
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a username.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter the full name.</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="0" <?php echo isset($user['role']) && $user['role'] === 0 ? 'selected' : ''; ?>>Admin</option>
                                        <option value="1" <?php echo isset($user['role']) && $user['role'] === 1 ? 'selected' : ''; ?>>Student</option>
                                        <option value="2" <?php echo isset($user['role']) && $user['role'] === 2 ? 'selected' : ''; ?>>Instructor</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a role.</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="proficiency_level" class="form-label">Proficiency Level</label>
                                    <select class="form-select" id="proficiency_level" name="proficiency_level">
                                        <option value="">Not Tested</option>
                                        <?php foreach ($levels as $level): ?>
                                            <option value="<?php echo $level; ?>" 
                                                <?php echo isset($user['proficiency_level']) && $user['proficiency_level'] === $level ? 'selected' : ''; ?>>
                                                <?php echo $level; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if (isset($user['initial_password'])): ?>
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Initial Password:</strong> <?php echo htmlspecialchars($user['initial_password']); ?>
                                        <br>
                                        <small class="text-muted">This user hasn't changed their password yet. Please inform them to change it for security.</small>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-key"></i>
                                        </span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="8" 
                                               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        Leave blank to keep current password. New password must have:
                                        <ul class="mb-0">
                                            <li>At least 8 characters</li>
                                            <li>1 uppercase letter</li>
                                            <li>1 lowercase letter</li>
                                            <li>1 number</li>
                                            <li>1 special character</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update User
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Password toggle visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const password = document.getElementById('new_password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html> 