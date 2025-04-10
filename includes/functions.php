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
?>