<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get POST data
$material_id = sanitize_input($_POST['material_id'] ?? '');
$section_id = sanitize_input($_POST['section_id'] ?? '');

if (!$material_id || !$section_id) {
    http_response_code(400);
    exit('Missing required parameters');
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Mark section as complete
    $stmt = $conn->prepare("
        INSERT INTO user_section_progress (user_id, material_id, section_id, completed_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE completed_at = NOW()
    ");
    $stmt->execute([$_SESSION['user_id'], $material_id, $section_id]);

    // Check if all sections are completed
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_sections,
               (SELECT COUNT(*) 
                FROM user_section_progress 
                WHERE user_id = ? AND material_id = ?) as completed_sections
        FROM material_sections 
        WHERE material_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $material_id, $material_id]);
    $result = $stmt->fetch();

    // If all sections are completed, mark the material as complete
    if ($result['total_sections'] == $result['completed_sections']) {
        $stmt = $conn->prepare("
            INSERT INTO user_material_progress (user_id, material_id, completed_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE completed_at = NOW()
        ");
        $stmt->execute([$_SESSION['user_id'], $material_id]);
    }

    // Commit transaction
    $conn->commit();

    http_response_code(200);
    exit('Success');

} catch(PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    http_response_code(500);
    exit('Error: ' . $e->getMessage());
} 