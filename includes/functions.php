<?php
// Helper functions for the application

// Calculate progress percentage
function calculate_progress($completed, $total) {
    if ($total <= 0) return 0;
    return round(($completed / $total) * 100);
}

// Sanitize output for HTML display
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Check if a user is an instructor
function is_instructor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'instructor';
}

// Check if the current user can access instructor panel
function require_instructor() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
    
    if (!is_instructor()) {
        header('Location: index.php');
        exit();
    }
}
?>