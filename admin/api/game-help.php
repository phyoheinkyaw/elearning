<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_help_sections':
        $game_id = (int)$_GET['game_id'];
        
        if (!$game_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Game ID is required']);
            exit();
        }
        
        try {
            // Get game info
            $stmt = $conn->prepare("SELECT * FROM games_info WHERE game_id = ?");
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$game) {
                http_response_code(404);
                echo json_encode(['error' => 'Game not found']);
                exit();
            }
            
            // Get help sections
            $stmt = $conn->prepare("
                SELECT * FROM games_help 
                WHERE game_id = ? 
                ORDER BY display_order ASC
            ");
            $stmt->execute([$game_id]);
            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'game' => $game,
                'sections' => $sections
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
        break;
        
    case 'get_help_section':
        $help_id = (int)$_GET['help_id'];
        
        if (!$help_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Help ID is required']);
            exit();
        }
        
        try {
            // Get help section
            $stmt = $conn->prepare("
                SELECT h.*, g.title as game_title 
                FROM games_help h
                JOIN games_info g ON h.game_id = g.game_id
                WHERE h.help_id = ?
            ");
            $stmt->execute([$help_id]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$section) {
                http_response_code(404);
                echo json_encode(['error' => 'Help section not found']);
                exit();
            }
            
            echo json_encode($section);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
        break;
        
    case 'get_next_order':
        $game_id = (int)$_GET['game_id'];
        
        if (!$game_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Game ID is required']);
            exit();
        }
        
        try {
            // Get max display order
            $stmt = $conn->prepare("
                SELECT MAX(display_order) as max_order 
                FROM games_help 
                WHERE game_id = ?
            ");
            $stmt->execute([$game_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $next_order = 1;
            if ($result && $result['max_order']) {
                $next_order = (int)$result['max_order'] + 1;
            }
            
            echo json_encode(['next_order' => $next_order]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?> 