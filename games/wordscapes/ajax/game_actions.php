<?php
/**
 * Wordscapes Game Actions Handler
 * Handles AJAX requests for the game
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent warnings and notices from breaking JSON output
ini_set('display_errors', 0);
error_reporting(E_ERROR);

// Set JSON content type header early
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get the absolute path to the includes directory
$includes_path = dirname(__DIR__, 3) . '/includes';
require_once $includes_path . '/db.php';
require_once $includes_path . '/functions.php';
require_once dirname(__DIR__) . '/includes/game_manager.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Process request
$action = $_REQUEST['action'] ?? '';
$level_id = isset($_REQUEST['level_id']) ? (int)$_REQUEST['level_id'] : null;

// Initialize game manager
$gameManager = new WordscapesGameManager($conn, $user_id, $level_id);

// Handle different actions
$response = ['success' => false, 'message' => 'Unknown action'];

switch ($action) {
    case 'check_word':
        // Check if word exists and mark as found
        $word = $_REQUEST['word'] ?? '';
        if (empty($word)) {
            $response = ['success' => false, 'message' => 'No word provided'];
            break;
        }
        
        // Sanitize input and convert to lowercase for comparison
        $word = strtolower(trim($word));
        
        // Debug: Log the submitted word and all valid words
        $allWords = $gameManager->getLevelWords();
        error_log("Submitted word: '$word'");
        error_log("Valid words: " . implode(", ", array_map(function($w) { return "'$w'"; }, $allWords)));
        
        // Check if the word exists in the level's word list
        $wordExists = in_array($word, array_map('strtolower', $allWords));
        
        // Try to add word
        $result = $wordExists ? $gameManager->addFoundWord($word) : false;
        
        if ($result) {
            // Get updated game data
            $gameData = $gameManager->getGameData();
            
            // Check if level is completed
            $isCompleted = count(array_intersect($gameData['found_words'], array_map('strtolower', $allWords))) >= count($allWords);
            
            $response = [
                'success' => true, 
                'message' => 'Word found',
                'word' => $word,
                'score' => $gameData['total_score'],
                'current_level_score' => $gameData['current_level_score'],
                'found_words' => $gameData['found_words'],
                'level_completed' => $isCompleted
            ];
        } else {
            $message = $wordExists ? 'Word already found' : 'Invalid word';
            $response = ['success' => false, 'message' => $message, 'word' => $word];
        }
        break;
        
    case 'use_hint':
        // Record hint usage
        $result = $gameManager->useHint();
        
        // Get the word and position that this hint is for
        $word = isset($_REQUEST['word']) ? trim($_REQUEST['word']) : '';
        $position = isset($_REQUEST['position']) ? (int)$_REQUEST['position'] : -1;
        
        if ($result) {
            // Record which letter was revealed, if specified
            if (!empty($word) && $position >= 0) {
                $gameManager->recordRevealedHint($word, $position);
            }
            
            // Get hint information
            $gameData = $gameManager->getGameData();
            $response = [
                'success' => true,
                'message' => 'Hint used',
                'hints_used' => $gameData['hints_used'],
                'hints_available' => ($gameData['hints_received'] - $gameData['hints_used']),
                'revealed_hints' => $gameManager->getRevealedHints()
            ];
        } else {
            $response = ['success' => false, 'message' => 'Could not use hint'];
        }
        break;
        
    case 'get_progress':
        // Get current game data
        $gameData = $gameManager->getGameData();
        $level = $gameManager->getLevelData($level_id);
        
        if ($level) {
            // Get level-specific info to ensure correct level is displayed
            $response = [
                'success' => true,
                'current_level' => $level_id,
                'level_number' => (int)$level['level_number'],
                'difficulty' => (int)$level['difficulty'],
                'score' => (int)$gameData['total_score'],
                'current_level_score' => (int)$gameData['current_level_score'],
                'found_words' => $gameData['found_words'],
                'hints_used' => (int)$gameData['hints_used'],
                'hints_received' => (int)$gameData['hints_received'],
                'available_hints' => (int)($gameData['hints_received'] - $gameData['hints_used']),
                'completed_levels' => $gameData['completed_levels'],
                'revealed_hints' => $gameData['revealed_hints'] ?? [],
                'start_time' => isset($gameData['start_time']) ? (int)$gameData['start_time'] : time(),
                'last_played' => isset($gameData['last_played']) ? (int)$gameData['last_played'] : time()
            ];
        } else {
            $response = ['success' => false, 'message' => 'Level not found'];
        }
        break;
        
    case 'get_leaderboard':
        // Get leaderboard data for current level
        $leaderboard = $gameManager->getLeaderboard();
        
        // Always return success and the leaderboard data, even if it's empty
        $response = [
            'success' => true,
            'leaderboard' => $leaderboard
        ];
        break;
        
    case 'reset_level':
        // Reset level progress
        $result = $gameManager->resetLevel();
        if ($result) {
            $response = [
                'success' => true,
                'message' => 'Level reset successfully'
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to reset level'];
        }
        break;
        
    case 'clear_session':
        // Clear the session data for testing/debugging
        $result = $gameManager->clearSessionData();
        $response = [
            'success' => true,
            'message' => 'Session data cleared. Reload page to get fresh data from database.'
        ];
        break;
        
    case 'get_levels':
        // Get all available levels
        $levels = $gameManager->getAllLevels();
        
        // For each level, check if it's completed
        $processedLevels = [];
        foreach ($levels as $level) {
            $level['completed'] = $gameManager->isLevelCompleted($level['level_id']);
            $processedLevels[] = $level;
        }
        
        $response = [
            'success' => true,
            'levels' => $processedLevels
        ];
        break;
        
    case 'save_current_level':
        // Save the current level in the session and database
        if ($level_id) {
            // First, check if the previous level is completed
            // If moving to level N, check if level N-1 is completed
            $previousLevel = $level_id - 1;
            $canAdvance = true;
            
            if ($previousLevel > 0) {
                // Get level ID for the previous level number
                $stmt = $conn->prepare("SELECT level_id FROM wordscapes_levels WHERE level_number = ?");
                $stmt->execute([$previousLevel]);
                $prevLevelData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($prevLevelData) {
                    $prevLevelId = $prevLevelData['level_id'];
                    $canAdvance = $gameManager->isLevelCompleted($prevLevelId);
                }
            }
            
            // Get the level data based on level_number
            $stmt = $conn->prepare("SELECT level_id FROM wordscapes_levels WHERE level_number = ?");
            $stmt->execute([$level_id]);
            $levelData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If level exists and user is allowed to advance, save it
            if ($levelData && $canAdvance) {
                $actualLevelId = $levelData['level_id'];
                $result = $gameManager->saveCurrentLevel($actualLevelId);
                
                if ($result) {
                    // Also update the session
                    $_SESSION['wordscapes_current_level_number'] = $level_id;
                    
                    $response = [
                        'success' => true,
                        'message' => 'Current level saved',
                        'level_id' => $actualLevelId,
                        'level_number' => $level_id
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Failed to save current level'
                    ];
                }
            } else {
                // If level doesn't exist or user can't advance, send error
                $response = [
                    'success' => false, 
                    'message' => $canAdvance ? 'Invalid level ID' : 'Cannot advance until previous level is completed'
                ];
            }
        } else {
            $response = [
                'success' => false, 
                'message' => 'Invalid level ID'
            ];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Unknown action'];
}

// Return JSON response
echo json_encode($response); 