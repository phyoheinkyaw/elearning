<?php
session_start();
require_once '../includes/db.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Simple security check - make sure we have query parameter
if (!isset($_GET['query']) || empty($_GET['query'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No search query provided',
        'suggestions' => []
    ]);
    exit;
}

$query = trim($_GET['query']);

// Sanitize the input to prevent SQL injection
$searchQuery = "%" . $query . "%";

try {
    // Get course suggestions
    $courseStmt = $conn->prepare("
        SELECT title, description, 'course' as type, course_id as id 
        FROM courses 
        WHERE title LIKE ? OR description LIKE ?
        ORDER BY title ASC
        LIMIT 5
    ");
    $courseStmt->execute([$searchQuery, $searchQuery]);
    $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get English term suggestions (from dictionary_terms if it exists)
    $terms = [];
    try {
        // Check if the dictionary_terms table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'dictionary_terms'");
        if ($tableCheck->rowCount() > 0) {
            $termStmt = $conn->prepare("
                SELECT word as title, definition as description, 'term' as type, term_id as id 
                FROM dictionary_terms 
                WHERE word LIKE ?
                ORDER BY word ASC
                LIMIT 5
            ");
            $termStmt->execute([$searchQuery]);
            $terms = $termStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Just log the error and continue
        error_log("Error querying dictionary terms: " . $e->getMessage());
    }
    
    // Get material suggestions
    $materials = [];
    try {
        // Check if the course_materials table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'course_materials'");
        if ($tableCheck->rowCount() > 0) {
            $materialStmt = $conn->prepare("
                SELECT m.title, m.description, 'material' as type, m.material_id as id, c.title as course_title  
                FROM course_materials m
                JOIN courses c ON m.course_id = c.course_id
                WHERE m.title LIKE ? OR m.description LIKE ?
                ORDER BY m.title ASC
                LIMIT 5
            ");
            $materialStmt->execute([$searchQuery, $searchQuery]);
            $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Just log the error and continue
        error_log("Error querying course materials: " . $e->getMessage());
    }
    
    // Combine all suggestions
    $suggestions = array_merge($courses, $terms, $materials);
    
    // Sort by relevance (if title starts with query, prioritize it)
    usort($suggestions, function($a, $b) use ($query) {
        $aStartsWith = stripos($a['title'], $query) === 0;
        $bStartsWith = stripos($b['title'], $query) === 0;
        
        if ($aStartsWith && !$bStartsWith) return -1;
        if (!$aStartsWith && $bStartsWith) return 1;
        
        return strcasecmp($a['title'], $b['title']);
    });
    
    // Limit to 8 total suggestions
    $suggestions = array_slice($suggestions, 0, 8);
    
    // Return the results
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ]);
    
} catch(PDOException $e) {
    // Log the error server-side
    error_log("Search suggestion error: " . $e->getMessage());
    
    // Return a generic error to the client
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching search suggestions',
        'suggestions' => []
    ]);
} 