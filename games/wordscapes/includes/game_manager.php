<?php
/**
 * Wordscapes Game Manager
 * Handles game state, level progression, and user progress tracking
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the absolute path to the includes directory
$includes_path = dirname(__DIR__, 3) . '/includes';
require_once $includes_path . '/db.php';
require_once $includes_path . '/functions.php';

/**
 * Game Manager Class
 */
class WordscapesGameManager {
    private $conn;
    private $user_id;
    private $current_level;
    private $game_data;
    
    /**
     * Constructor
     */
    public function __construct($conn, $user_id, $level_id = null) {
        $this->conn = $conn;
        $this->user_id = $user_id;
        
        // Set current level (if provided) or get the user's current level
        if ($level_id) {
            $this->current_level = $this->getLevelData($level_id);
        } else {
            $this->current_level = $this->getUserCurrentLevel();
        }
        
        // Initialize game data
        $this->initGameData();
    }
    
    /**
     * Initialize game data from database or session
     */
    private function initGameData() {
        // First check if game data exists in session
        if (isset($_SESSION['wordscapes_game_data'])) {
            $this->game_data = $_SESSION['wordscapes_game_data'];
        } else {
            // Default game data structure
            $this->game_data = [
                'current_level' => $this->current_level['level_id'] ?? 1,
                'found_words' => [],
                'score' => 0,
                'streak' => 0,
                'hints_used' => 0,
                'start_time' => time(),
                'last_played' => time()
            ];
            
            // Try to load from database
            $this->loadGameDataFromDB();
            
            // Save to session
            $_SESSION['wordscapes_game_data'] = $this->game_data;
        }
    }
    
    /**
     * Load game data from database
     */
    private function loadGameDataFromDB() {
        if (!$this->user_id || !$this->current_level) return;
        
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM wordscapes_user_progress 
                WHERE user_id = ? AND level_id = ?
            ");
            $stmt->execute([$this->user_id, $this->current_level['level_id']]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($progress) {
                // Update game data with database values
                $this->game_data['found_words'] = json_decode($progress['found_words'], true) ?: [];
                $this->game_data['score'] = $progress['score'] ?? 0;
                $this->game_data['hints_used'] = $progress['hints_used'] ?? 0;
                $this->game_data['last_played'] = strtotime($progress['last_played']);
            }
        } catch (PDOException $e) {
            // Log error (optional)
            error_log("Error loading game data: " . $e->getMessage());
        }
    }
    
    /**
     * Save game data to database
     */
    public function saveGameData() {
        if (!$this->user_id || !$this->current_level) return false;
        
        try {
            // Update session first
            $_SESSION['wordscapes_game_data'] = $this->game_data;
            
            // Convert found words to JSON
            $found_words_json = json_encode($this->game_data['found_words']);
            
            // Check if progress exists
            $stmt = $this->conn->prepare("
                SELECT progress_id FROM wordscapes_user_progress 
                WHERE user_id = ? AND level_id = ?
            ");
            $stmt->execute([$this->user_id, $this->current_level['level_id']]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exists) {
                // Update existing progress
                $stmt = $this->conn->prepare("
                    UPDATE wordscapes_user_progress 
                    SET found_words = ?, score = ?, hints_used = ?, last_played = NOW()
                    WHERE user_id = ? AND level_id = ?
                ");
                return $stmt->execute([
                    $found_words_json, 
                    $this->game_data['score'], 
                    $this->game_data['hints_used'],
                    $this->user_id, 
                    $this->current_level['level_id']
                ]);
            } else {
                // Insert new progress
                $stmt = $this->conn->prepare("
                    INSERT INTO wordscapes_user_progress 
                    (user_id, level_id, found_words, score, hints_used, last_played) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                return $stmt->execute([
                    $this->user_id, 
                    $this->current_level['level_id'],
                    $found_words_json,
                    $this->game_data['score'],
                    $this->game_data['hints_used']
                ]);
            }
        } catch (PDOException $e) {
            // Log error (optional)
            error_log("Error saving game data: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get level data by ID
     */
    public function getLevelData($level_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM wordscapes_levels 
                WHERE level_id = ?
            ");
            $stmt->execute([$level_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Get words for current level
     */
    public function getLevelWords() {
        if (!$this->current_level) return [];
        
        try {
            $stmt = $this->conn->prepare("
                SELECT word FROM wordscapes_words 
                WHERE level_id = ? 
                ORDER BY word
            ");
            $stmt->execute([$this->current_level['level_id']]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get user's current level (highest unlocked)
     */
    private function getUserCurrentLevel() {
        try {
            // First try to get from preferences
            $stmt = $this->conn->prepare("
                SELECT wordscapes_current_level
                FROM user_game_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['wordscapes_current_level']) {
                // User has a saved current level
                return $this->getLevelData($result['wordscapes_current_level']);
            }
            
            // If no preference found, get the highest level completed
            $stmt = $this->conn->prepare("
                SELECT MAX(level_id) as max_level 
                FROM wordscapes_user_progress 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $max_level = $result['max_level'] ?? 0;
            
            // If user has completed levels, return the next level, otherwise return level 1
            $next_level = $max_level + 1;
            
            // Check if next level exists
            $level_data = $this->getLevelData($next_level);
            if ($level_data) {
                return $level_data;
            } else {
                // If next level doesn't exist, return the last level
                return $this->getLevelData($max_level > 0 ? $max_level : 1);
            }
        } catch (PDOException $e) {
            // Default to level 1 if error
            return $this->getLevelData(1);
        }
    }
    
    /**
     * Get all level IDs and information
     */
    public function getAllLevels() {
        try {
            $stmt = $this->conn->prepare("
                SELECT level_id, level_number, difficulty
                FROM wordscapes_levels
                ORDER BY level_number ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Check if level is completed by user
     */
    public function isLevelCompleted($level_id) {
        try {
            // Get all words for this level
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total_words FROM wordscapes_words 
                WHERE level_id = ?
            ");
            $stmt->execute([$level_id]);
            $total_words = $stmt->fetch(PDO::FETCH_ASSOC)['total_words'] ?? 0;
            
            // Get user progress for this level
            $stmt = $this->conn->prepare("
                SELECT found_words FROM wordscapes_user_progress 
                WHERE user_id = ? AND level_id = ?
            ");
            $stmt->execute([$this->user_id, $level_id]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$progress) return false;
            
            $found_words = json_decode($progress['found_words'], true) ?: [];
            
            // Level is completed if all words are found
            return count($found_words) >= $total_words;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Add a found word
     */
    public function addFoundWord($word) {
        $word = strtolower(trim($word));
        
        // Get all level words and convert to lowercase for case-insensitive comparison
        $level_words = array_map('strtolower', $this->getLevelWords());
        
        // Check if word exists in the level (case-insensitive)
        if (!in_array($word, $level_words)) {
            error_log("Word '$word' not found in level words");
            return false;
        }
        
        // Check if word is already found
        if (in_array($word, $this->game_data['found_words'])) {
            error_log("Word '$word' already found");
            return false; // Word already found is a failure case
        }
        
        // Add word to found words
        $this->game_data['found_words'][] = $word;
        
        // Update score (points equal to word length)
        $this->game_data['score'] += strlen($word);
        
        // Update last played time
        $this->game_data['last_played'] = time();
        
        // Save game data
        $this->saveGameData();
        
        return true;
    }
    
    /**
     * Use a hint
     */
    public function useHint() {
        // Increment hints used
        $this->game_data['hints_used']++;
        
        // Save game data
        $this->saveGameData();
        
        return true;
    }
    
    /**
     * Get current game data
     */
    public function getGameData() {
        return $this->game_data;
    }
    
    /**
     * Reset level progress
     */
    public function resetLevel() {
        // Reset game data for current level
        $this->game_data['found_words'] = [];
        $this->game_data['score'] = 0;
        $this->game_data['streak'] = 0;
        $this->game_data['hints_used'] = 0;
        $this->game_data['start_time'] = time();
        $this->game_data['last_played'] = time();
        
        // Save to session
        $_SESSION['wordscapes_game_data'] = $this->game_data;
        
        // Reset in database
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM wordscapes_user_progress 
                WHERE user_id = ? AND level_id = ?
            ");
            return $stmt->execute([$this->user_id, $this->current_level['level_id']]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Save user's current level
     */
    public function saveCurrentLevel($level_id) {
        if (!$this->user_id || !$level_id) return false;
        
        // Save to session
        $_SESSION['wordscapes_current_level'] = $level_id;
        
        try {
            // Check if user has a preferences entry
            $stmt = $this->conn->prepare("
                SELECT 1 FROM user_game_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $exists = $stmt->fetch(PDO::FETCH_COLUMN);
            
            if ($exists) {
                // Update existing preferences
                $stmt = $this->conn->prepare("
                    UPDATE user_game_preferences 
                    SET wordscapes_current_level = ?
                    WHERE user_id = ?
                ");
                return $stmt->execute([$level_id, $this->user_id]);
            } else {
                // Create new preferences entry
                $stmt = $this->conn->prepare("
                    INSERT INTO user_game_preferences 
                    (user_id, wordscapes_current_level) 
                    VALUES (?, ?)
                ");
                return $stmt->execute([$this->user_id, $level_id]);
            }
        } catch (PDOException $e) {
            // Just save to session if database operation fails
            error_log("Error saving current level: " . $e->getMessage());
            return true; // Return true since we at least saved to session
        }
    }
    
    /**
     * Get leaderboard data for the current level
     * Returns top 10 scores and user's position
     */
    public function getLeaderboard() {
        if (!$this->current_level) return [];
        
        try {
            // Get top 10 users by score for this level
            $stmt = $this->conn->prepare("
                SELECT p.user_id, u.username, p.score, p.hints_used, 
                       TIMESTAMPDIFF(SECOND, p.start_time, p.last_played) as time_spent
                FROM wordscapes_user_progress p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.level_id = ?
                ORDER BY p.score DESC, p.hints_used ASC, time_spent ASC
                LIMIT 10
            ");
            $stmt->execute([$this->current_level['level_id']]);
            $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get user's rank if not in top 10
            $userRank = null;
            $userInTop10 = false;
            
            foreach ($topUsers as $index => $user) {
                if ($user['user_id'] == $this->user_id) {
                    $userRank = $index + 1;
                    $userInTop10 = true;
                    break;
                }
            }
            
            // If user not in top 10, find their rank
            if (!$userInTop10) {
                $stmt = $this->conn->prepare("
                    SELECT COUNT(*) as rank
                    FROM wordscapes_user_progress
                    WHERE level_id = ? AND (
                        score > (SELECT score FROM wordscapes_user_progress WHERE user_id = ? AND level_id = ?)
                        OR (
                            score = (SELECT score FROM wordscapes_user_progress WHERE user_id = ? AND level_id = ?)
                            AND hints_used < (SELECT hints_used FROM wordscapes_user_progress WHERE user_id = ? AND level_id = ?)
                        )
                        OR (
                            score = (SELECT score FROM wordscapes_user_progress WHERE user_id = ? AND level_id = ?)
                            AND hints_used = (SELECT hints_used FROM wordscapes_user_progress WHERE user_id = ? AND level_id = ?)
                            AND TIMESTAMPDIFF(SECOND, start_time, last_played) < (
                                SELECT TIMESTAMPDIFF(SECOND, start_time, last_played) 
                                FROM wordscapes_user_progress 
                                WHERE user_id = ? AND level_id = ?
                            )
                        )
                    )
                ");
                $stmt->execute([
                    $this->current_level['level_id'], 
                    $this->user_id, 
                    $this->current_level['level_id'],
                    $this->user_id, 
                    $this->current_level['level_id'],
                    $this->user_id, 
                    $this->current_level['level_id'],
                    $this->user_id, 
                    $this->current_level['level_id'],
                    $this->user_id, 
                    $this->current_level['level_id'],
                    $this->user_id, 
                    $this->current_level['level_id']
                ]);
                $userRank = $stmt->fetch(PDO::FETCH_COLUMN) + 1;
                
                // Get user's data
                $stmt = $this->conn->prepare("
                    SELECT p.user_id, u.username, p.score, p.hints_used, 
                           TIMESTAMPDIFF(SECOND, p.start_time, p.last_played) as time_spent
                    FROM wordscapes_user_progress p
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.user_id = ? AND p.level_id = ?
                ");
                $stmt->execute([$this->user_id, $this->current_level['level_id']]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userData) {
                    $userData['rank'] = $userRank;
                }
            }
            
            return [
                'top_users' => $topUsers,
                'user_rank' => $userRank,
                'user_in_top' => $userInTop10,
                'user_data' => $userInTop10 ? null : $userData
            ];
        } catch (PDOException $e) {
            error_log("Error getting leaderboard: " . $e->getMessage());
            return [
                'top_users' => [],
                'user_rank' => null,
                'user_in_top' => false,
                'user_data' => null
            ];
        }
    }
} 