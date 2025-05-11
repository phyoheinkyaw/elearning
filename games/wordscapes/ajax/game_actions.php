<?php
/**
 * Wordscapes Game Actions Handler
 * Handles AJAX requests for the game
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
                'score' => $gameData['score'],
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
        if ($result) {
            // Get hint information
            $gameData = $gameManager->getGameData();
            $response = [
                'success' => true,
                'message' => 'Hint used',
                'hints_used' => $gameData['hints_used']
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
            $response = [
                'success' => true,
                'current_level' => $level_id,
                'level_number' => $level['level_number'],
                'difficulty' => $level['difficulty'],
                'score' => $gameData['score'],
                'found_words' => $gameData['found_words'],
                'streak' => $gameData['streak'],
                'hints_used' => $gameData['hints_used'],
                'start_time' => $gameData['start_time'],
                'last_played' => $gameData['last_played']
            ];
        } else {
            $response = ['success' => false, 'message' => 'Level not found'];
        }
        break;
        
    case 'get_leaderboard':
        // Get leaderboard data for current level
        $leaderboard = $gameManager->getLeaderboard();
        
        if ($leaderboard) {
            $response = [
                'success' => true,
                'leaderboard' => $leaderboard
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to get leaderboard'];
        }
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
            $result = $gameManager->saveCurrentLevel($level_id);
            if ($result) {
                $response = [
                    'success' => true,
                    'message' => 'Current level saved'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Failed to save current level'
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
header('Content-Type: application/json');
echo json_encode($response); 