<?php
session_start();
require_once 'includes/db.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character';
    } else {
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Begin transaction
                $conn->beginTransaction();

                // Insert user with last_login timestamp
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, last_login) VALUES (?, ?, ?, 1, NOW())");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $email, $hashed_password]);
                $user_id = $conn->lastInsertId();

                // Create user profile without proficiency level
                $stmt = $conn->prepare("INSERT INTO user_profiles (user_id) VALUES (?)");
                $stmt->execute([$user_id]);

                // Commit transaction
                $conn->commit();

                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'student';

                // Redirect to level test with success message
                $_SESSION['registration_success'] = true;
                header('Location: level-test.php');
                exit();
            }
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Floating Chatbot CSS -->
    <link href="css/floating-chatbot.css" rel="stylesheet">
    <!-- Search Autocomplete CSS -->
    <link href="css/search-autocomplete.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h1 class="h3 mb-3">Create Account</h1>
                            <p class="text-muted">Join our learning community today</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form action="register.php" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="invalid-feedback">Please choose a username.</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="8" 
                                           pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,}$">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <div class="password-strength-meter">
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" style="width: 0%;" id="passwordStrength"></div>
                                        </div>
                                        <small class="mt-1 d-block" id="strength-text"></small>
                                    </div>
                                    <div class="password-requirements mt-2">
                                        <small class="d-block mb-1">Password must contain:</small>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge bg-secondary" id="length-check">8+ characters</span>
                                            <span class="badge bg-secondary" id="uppercase-check">Uppercase</span>
                                            <span class="badge bg-secondary" id="lowercase-check">Lowercase</span>
                                            <span class="badge bg-secondary" id="number-check">Number</span>
                                            <span class="badge bg-secondary" id="special-check">Special char</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Passwords must match.</div>
                            </div>

                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                After registration, you'll need to complete a level test to determine your English proficiency.
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                            </div>

                            <div class="text-center mt-4">
                                <p class="text-muted">
                                    Already have an account? 
                                    <a href="login.php" class="text-decoration-none">Sign in here</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Form Validation -->
    <script>
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        // Check if passwords match
                        const password = document.getElementById('password');
                        const confirmPassword = document.getElementById('confirm_password');
                        
                        if (password.value !== confirmPassword.value) {
                            confirmPassword.setCustomValidity('Passwords must match');
                            event.preventDefault();
                            event.stopPropagation();
                        } else {
                            confirmPassword.setCustomValidity('');
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
                
            // Password strength meter
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('passwordStrength');
            const lengthCheck = document.getElementById('length-check');
            const uppercaseCheck = document.getElementById('uppercase-check');
            const lowercaseCheck = document.getElementById('lowercase-check');
            const numberCheck = document.getElementById('number-check');
            const specialCheck = document.getElementById('special-check');
            
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            toggleConfirmPassword.addEventListener('click', function() {
                const confirmPasswordInput = document.getElementById('confirm_password');
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            // Check password strength
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Reset all checks
                [lengthCheck, uppercaseCheck, lowercaseCheck, numberCheck, specialCheck].forEach(check => {
                    check.classList.remove('bg-success');
                    check.classList.add('bg-secondary');
                });
                
                // Check length
                if (password.length >= 8) {
                    strength += 20;
                    lengthCheck.classList.remove('bg-secondary');
                    lengthCheck.classList.add('bg-success');
                }
                
                // Check for uppercase letters
                if (/[A-Z]/.test(password)) {
                    strength += 20;
                    uppercaseCheck.classList.remove('bg-secondary');
                    uppercaseCheck.classList.add('bg-success');
                }
                
                // Check for lowercase letters
                if (/[a-z]/.test(password)) {
                    strength += 20;
                    lowercaseCheck.classList.remove('bg-secondary');
                    lowercaseCheck.classList.add('bg-success');
                }
                
                // Check for numbers
                if (/[0-9]/.test(password)) {
                    strength += 20;
                    numberCheck.classList.remove('bg-secondary');
                    numberCheck.classList.add('bg-success');
                }
                
                // Check for special characters
                if (/[^A-Za-z0-9]/.test(password)) {
                    strength += 20;
                    specialCheck.classList.remove('bg-secondary');
                    specialCheck.classList.add('bg-success');
                }
                
                // Update strength bar
                strengthBar.style.width = strength + '%';
                
                // Change color based on strength
                if (strength < 40) {
                    strengthBar.className = 'progress-bar bg-danger';
                    document.getElementById('strength-text').textContent = 'Weak';
                    document.getElementById('strength-text').className = 'mt-1 d-block text-danger';
                } else if (strength < 80) {
                    strengthBar.className = 'progress-bar bg-warning';
                    document.getElementById('strength-text').textContent = 'Medium';
                    document.getElementById('strength-text').className = 'mt-1 d-block text-warning';
                } else {
                    strengthBar.className = 'progress-bar bg-success';
                    document.getElementById('strength-text').textContent = 'Strong';
                    document.getElementById('strength-text').className = 'mt-1 d-block text-success';
                }
            });
        })()
    </script>
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="js/lib/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html> 