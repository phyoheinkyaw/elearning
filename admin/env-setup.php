<?php
session_start();
require_once '../includes/db.php';
require_once 'includes/auth.php';

// Check if the user is an admin, redirect if not
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit;
}

$success_message = '';
$error_message = '';
$current_key = '';
$env_exists = false;

// Path to .env file
$env_path = realpath(__DIR__ . '/../.env');

// Check if .env file exists
if ($env_path && file_exists($env_path)) {
    $env_exists = true;
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), 'OPENROUTER_API_KEY=') === 0) {
            $current_key = trim(substr($line, strlen('OPENROUTER_API_KEY=')));
            if ($current_key === 'your_api_key_here') {
                $current_key = '';
            }
            break;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $new_key = trim($_POST['api_key']);
    
    if (empty($new_key)) {
        $error_message = 'API key cannot be empty.';
    } else {
        try {
            if ($env_exists) {
                // Read existing .env file
                $env_content = file_get_contents($env_path);
                
                // Replace or add API key
                if (strpos($env_content, 'OPENROUTER_API_KEY=') !== false) {
                    $env_content = preg_replace(
                        '/OPENROUTER_API_KEY=.*/', 
                        'OPENROUTER_API_KEY=' . $new_key, 
                        $env_content
                    );
                } else {
                    $env_content .= "\nOPENROUTER_API_KEY=" . $new_key;
                }
            } else {
                // Create new .env file
                $env_content = "# eLearning Platform - Environment Configuration\n\n";
                $env_content .= "# OpenRouter API Key for AI features\n";
                $env_content .= "OPENROUTER_API_KEY=" . $new_key . "\n\n";
                $env_content .= "# Database Configuration\n";
                $env_content .= "DB_HOST=localhost\n";
                $env_content .= "DB_NAME=elearning\n";
                $env_content .= "DB_USER=root\n";
                $env_content .= "DB_PASS=\n\n";
                $env_content .= "# Debug Mode (true or false)\n";
                $env_content .= "DEBUG=false\n";
            }
            
            // Save to .env file
            if (file_put_contents(__DIR__ . '/../.env', $env_content)) {
                $success_message = 'API key saved successfully.';
                $current_key = $new_key;
                $env_exists = true;
            } else {
                $error_message = 'Could not write to .env file. Check file permissions.';
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}

// Test the API key
$test_result = '';
if (isset($_POST['test']) && !empty($current_key)) {
    $ch = curl_init('https://openrouter.ai/api/v1/auth/key');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $current_key,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $test_result = '<div class="alert alert-success">API key is valid!</div>';
    } else {
        $test_result = '<div class="alert alert-danger">API key validation failed. HTTP code: ' . $http_code . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Environment Configuration - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Environment Configuration</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>About Environment Configuration</h5>
                            <p>This page helps you configure the OpenRouter API key needed for AI features. The key will be stored in the <code>.env</code> file in your website's root directory.</p>
                        </div>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="api_key" class="form-label">OpenRouter API Key</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="api_key" name="api_key" value="<?php echo htmlspecialchars($current_key); ?>" placeholder="Enter your OpenRouter API key">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleKey">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Get your API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter.ai</a>
                                </div>
                            </div>
                            
                            <?php echo $test_result; ?>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="save" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save API Key
                                </button>
                                <?php if ($env_exists && !empty($current_key)): ?>
                                    <button type="submit" name="test" class="btn btn-secondary">
                                        <i class="fas fa-vial me-1"></i> Test API Key
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if ($env_exists): ?>
                            <hr>
                            <div class="mt-4">
                                <h5><i class="fas fa-file-alt me-2"></i>.env File Status</h5>
                                <p><span class="badge bg-success">Found</span> The .env file exists at: <code><?php echo htmlspecialchars($env_path); ?></code></p>
                            </div>
                        <?php else: ?>
                            <hr>
                            <div class="mt-4">
                                <h5><i class="fas fa-file-alt me-2"></i>.env File Status</h5>
                                <p><span class="badge bg-warning">Not Found</span> The .env file will be created when you save an API key.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle API key visibility
        document.getElementById('toggleKey').addEventListener('click', function() {
            const apiKeyInput = document.getElementById('api_key');
            const eyeIcon = this.querySelector('i');
            
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                apiKeyInput.type = 'password';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
        
        // Set input type to password initially
        document.addEventListener('DOMContentLoaded', function() {
            const apiKeyInput = document.getElementById('api_key');
            if (apiKeyInput.value) {
                apiKeyInput.type = 'password';
            }
        });
    </script>
</body>
</html> 