<?php
// Database configuration
define('DB_HOST', 'localhost:3308');
define('DB_NAME', 'elearning_db');
define('DB_USER', 'root');
define('DB_PASS', 'root');

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Set timezone
date_default_timezone_set('UTC');

// Helper Functions
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function sanitize_filename($filename, $title = '') {
    // If title is provided, use it for the filename base
    if (!empty($title)) {
        // Convert title to lowercase
        $title = strtolower($title);
        
        // Remove any character that isn't a letter, digit, space, or dash
        $title = preg_replace('/[^\w\s-]/', '', $title);
        
        // Replace spaces and other non-alphanumeric characters with underscores
        $title = preg_replace('/[\s-]+/', '_', $title);
        
        // Remove leading/trailing underscores
        $title = trim($title, '_');
        
        // Get the extension from the original filename
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Create the new filename
        $filename = $title . '.' . $extension;
    } else {
        // Fallback to original behavior if no title is provided
        // Remove any character that isn't a letter, digit, space, underscore, dash, or dot
        $filename = preg_replace('/[^\w\s.-]/', '', $filename);
        
        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);
        
        // Remove multiple dots and ensure there's only one dot before the extension
        $filename = preg_replace('/\.(?![^.]*$)/', '_', $filename);
    }
    
    // Limit the filename length
    if (strlen($filename) > 255) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - strlen($extension) - 1) . '.' . $extension;
    }
    
    return $filename;
}

function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length));
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

function get_user_avatar($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT avatar_url FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['avatar_url'] ?? null;
    } catch(PDOException $e) {
        return null;
    }
}

function get_user_level($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT proficiency_level FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['proficiency_level'] ?? null;
    } catch(PDOException $e) {
        return null;
    }
}

function format_date($date) {
    return date('M d, Y', strtotime($date));
}

function get_error_message() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return null;
}

function get_success_message() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return null;
}

function set_error_message($message) {
    $_SESSION['error_message'] = $message;
}

function set_success_message($message) {
    $_SESSION['success_message'] = $message;
} 