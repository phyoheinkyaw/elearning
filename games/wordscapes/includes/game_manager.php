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
            $this->current_level = $this->getCurrentLevelData();
        }
        
        // Initialize game data
        $this->initGameData();
    }
    
    /**
     * Initialize game data from database or session
     */
    private function initGameData() {
        // Default game data structure
        $this->game_data = [
            'current_level' => $this->current_level['level_id'] ?? 1,
            'found_words' => [],
            'current_level_score' => 0,
            'total_score' => 0, 
            'hints_used' => 0,
            'hints_received' => 0,
            'completed_levels' => [],
            'start_time' => time(),
            'last_played' => time(),
            'revealed_hints' => [] // Store information about which letters were revealed by hints
        ];
        
        // Try to load from database first (prioritize database over session)
        $this->loadGameDataFromDB();
        
        // Update session with latest data
        $_SESSION['wordscapes_game_data'] = $this->game_data;
    }
    
    /**
     * Load game data from database
     */
    private function loadGameDataFromDB() {
        if (!$this->user_id || !$this->current_level) return;
        
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM wordscapes_user_progress 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($progress) {
                // Check if we need to load a different level than what's in the database
                $isCurrentLevelDifferent = $this->current_level['level_id'] != $progress['current_level_id'];
                
                if ($isCurrentLevelDifferent) {
                    // User is accessing a different level than their current progress
                    // Only load global data, but keep current level data fresh
                    $this->game_data['total_score'] = $progress['total_score'] ?? 0;
                    $this->game_data['hints_used'] = $progress['hints_used'] ?? 0;
                    $this->game_data['hints_received'] = $progress['hints_received'] ?? 0;
                    $this->game_data['completed_levels'] = json_decode($progress['completed_levels'], true) ?: [];
                    
                    // Initialize empty data for this level
                    $this->game_data['found_words'] = [];
                    $this->game_data['current_level_score'] = 0;
                    $this->game_data['revealed_hints'] = json_decode($progress['revealed_hints'], true) ?: [];
                } else {
                    // User is on their current level, load all data
                    $this->game_data['found_words'] = json_decode($progress['found_words'], true) ?: [];
                    $this->game_data['current_level_score'] = $progress['current_level_score'] ?? 0;
                    $this->game_data['total_score'] = $progress['total_score'] ?? 0;
                    $this->game_data['hints_used'] = $progress['hints_used'] ?? 0;
                    $this->game_data['hints_received'] = $progress['hints_received'] ?? 0;
                    $this->game_data['completed_levels'] = json_decode($progress['completed_levels'], true) ?: [];
                    $this->game_data['last_played'] = strtotime($progress['last_played']);
                    $this->game_data['revealed_hints'] = json_decode($progress['revealed_hints'], true) ?: [];
                }
            }
            // If no progress data exists, the default empty values from initGameData will be used
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
            // Convert arrays to JSON
            $found_words_json = json_encode($this->game_data['found_words']);
            $completed_levels_json = json_encode($this->game_data['completed_levels']);
            $revealed_hints_json = json_encode($this->game_data['revealed_hints']);
            
            // Check if progress record exists for this user
            $stmt = $this->conn->prepare("
                SELECT progress_id FROM wordscapes_user_progress 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $result = false;
            
            if ($exists) {
                // Update existing progress
                $stmt = $this->conn->prepare("
                    UPDATE wordscapes_user_progress 
                    SET current_level_id = ?, 
                        found_words = ?, 
                        total_score = ?, 
                        current_level_score = ?,
                        hints_used = ?, 
                        hints_received = ?,
                        completed_levels = ?,
                        revealed_hints = ?,
                        last_played = NOW()
                    WHERE user_id = ?
                ");
                $result = $stmt->execute([
                    $this->current_level['level_id'],
                    $found_words_json,
                    $this->game_data['total_score'],
                    $this->game_data['current_level_score'],
                    $this->game_data['hints_used'],
                    $this->game_data['hints_received'],
                    $completed_levels_json,
                    $revealed_hints_json,
                    $this->user_id
                ]);
            } else {
                // Insert new progress
                $stmt = $this->conn->prepare("
                    INSERT INTO wordscapes_user_progress 
                    (user_id, current_level_id, found_words, total_score, current_level_score, 
                    hints_used, hints_received, completed_levels, revealed_hints, last_played) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $result = $stmt->execute([
                    $this->user_id,
                    $this->current_level['level_id'],
                    $found_words_json,
                    $this->game_data['total_score'],
                    $this->game_data['current_level_score'],
                    $this->game_data['hints_used'],
                    $this->game_data['hints_received'],
                    $completed_levels_json,
                    $revealed_hints_json
                ]);
            }
            
            // Only update session if database update was successful
            if ($result) {
                // Update last_played time in the game_data
                $this->game_data['last_played'] = time();
                
                // Update session with the latest data
                $_SESSION['wordscapes_game_data'] = $this->game_data;
            }
            
            return $result;
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
     * Returns level number rather than full level data
     */
    public function getUserCurrentLevel() {
        try {
            // Get user progress from database
            $stmt = $this->conn->prepare("
                SELECT wl.level_number
                FROM wordscapes_user_progress wup
                JOIN wordscapes_levels wl ON wup.current_level_id = wl.level_id
                WHERE wup.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['level_number'])) {
                return (int)$result['level_number'];
            }
            
            // If no level found, return 1 as default
            return 1;
            
        } catch (PDOException $e) {
            // Default to level 1 if error
            error_log("Error getting user current level: " . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Get complete level data for user's current level
     * Used for initialization
     */
    private function getCurrentLevelData() {
        try {
            // Get user progress from database
            $stmt = $this->conn->prepare("
                SELECT current_level_id
                FROM wordscapes_user_progress 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['current_level_id']) {
                // User has a saved level
                $level_data = $this->getLevelData($result['current_level_id']);
                if ($level_data) {
                    return $level_data;
                }
            }
            
            // If no level found or level doesn't exist, create user progress with level 1
            $this->initializeUserProgress(1);
            
            // Return level 1 as default
            return $this->getLevelData(1);
        } catch (PDOException $e) {
            // Default to level 1 if error
            error_log("Error getting user current level: " . $e->getMessage());
            return $this->getLevelData(1);
        }
    }
    
    /**
     * Initialize user progress
     */
    private function initializeUserProgress($level_id) {
        try {
            // Create new user progress entry with default values
            $stmt = $this->conn->prepare("
                INSERT INTO wordscapes_user_progress 
                (user_id, current_level_id, found_words, total_score, current_level_score, 
                hints_used, hints_received, completed_levels, revealed_hints, last_played) 
                VALUES (?, ?, '[]', 0, 0, 0, 0, '[]', '{}', NOW())
                ON DUPLICATE KEY UPDATE current_level_id = ?
            ");
            return $stmt->execute([
                $this->user_id, 
                $level_id,
                $level_id
            ]);
        } catch (PDOException $e) {
            error_log("Error initializing user progress: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save current level
     */
    public function saveCurrentLevel($level_id) {
        try {
            // Update session
            $_SESSION['wordscapes_current_level'] = $level_id;
            $this->current_level = $this->getLevelData($level_id);
            
            // Reset found words for the new level if we're changing levels
            if ($this->game_data['current_level'] != $level_id) {
                $this->game_data['current_level'] = $level_id;
                $this->game_data['found_words'] = [];
                $this->game_data['current_level_score'] = 0;
            }
            
            // Update database
            return $this->saveGameData();
        } catch (Exception $e) {
            error_log("Error saving current level: " . $e->getMessage());
            return false;
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
        // Check if level ID is in the completed levels array
        if (isset($this->game_data['completed_levels']) && 
            in_array($level_id, $this->game_data['completed_levels'])) {
            return true;
        }
        
        // If checking current level and not in completed array, check words count
        if ($level_id == $this->current_level['level_id']) {
            try {
                // Get all words for this level
                $valid_words = $this->getLevelWords();
                $found_words = $this->game_data['found_words'] ?? [];
                
                // Level is completed if all words are found
                return count($found_words) >= count($valid_words);
            } catch (PDOException $e) {
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Add a found word
     */
    public function addFoundWord($word) {
        // Convert to lowercase for consistency
        $word = strtolower($word);
        
        // Check if word is already found
        if (in_array($word, $this->game_data['found_words'])) {
            return false;
        }
        
        // Check if word is valid for this level
        $valid_words = array_map('strtolower', $this->getLevelWords());
        if (!in_array($word, $valid_words)) {
            return false;
        }
        
        // Calculate points (1 point per letter)
        $points = strlen($word) * 1;
        
        // Add word to found words
        $this->game_data['found_words'][] = $word;
        
        // Update scores
        $this->game_data['current_level_score'] += $points;
        $this->game_data['total_score'] += $points;
        
        // Award 1 hint per 30 points earned
        $previousHints = floor(($this->game_data['total_score'] - $points) / 30);
        $currentHints = floor($this->game_data['total_score'] / 30);
        
        if ($currentHints > $previousHints) {
            // We've crossed a 30-point threshold, add a hint
            $this->game_data['hints_received'] += ($currentHints - $previousHints);
        }
        
        // Check if level is completed
        $allWordsFound = count($this->game_data['found_words']) >= count($valid_words);
        if ($allWordsFound) {
            // Add to completed levels if not already there
            if (!in_array($this->current_level['level_id'], $this->game_data['completed_levels'])) {
                $this->game_data['completed_levels'][] = $this->current_level['level_id'];
            }
        }
        
        // Update last played time
        $this->game_data['last_played'] = time();
        
        // Save to database
        $this->saveGameData();
        
        return true;
    }
    
    /**
     * Use a hint
     */
    public function useHint() {
        // Check if user has available hints
        $available_hints = $this->game_data['hints_received'] - $this->game_data['hints_used'];
        if ($available_hints <= 0) {
            return false;
        }
        
        // Increment hints used
        $this->game_data['hints_used']++;
        
        // Save game data
        $this->saveGameData();
        
        return true;
    }
    
    /**
     * Get available hints
     */
    public function getAvailableHints() {
        return $this->game_data['hints_received'] - $this->game_data['hints_used'];
    }
    
    /**
     * Reset level progress for current level
     */
    public function resetLevel() {
        // Reset game data for current level
        $this->game_data['found_words'] = [];
        $this->game_data['current_level_score'] = 0;
        
        // Save to session
        $_SESSION['wordscapes_game_data'] = $this->game_data;
        
        // Save to database
        return $this->saveGameData();
    }
    
    /**
     * Get current game data
     */
    public function getGameData() {
        return $this->game_data;
    }
    
    /**
     * Get leaderboard data for the current level
     * Returns top scores and user's position
     */
    public function getLeaderboard() {
        try {
            // Check if there are any users with progress data
            $checkStmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM wordscapes_user_progress
            ");
            $checkStmt->execute();
            $userCount = $checkStmt->fetchColumn();
            
            // If no users have played yet, return empty leaderboard
            if ($userCount == 0) {
                return [
                    'top_users' => [],
                    'user_rank' => null,
                    'user_in_top' => false,
                    'user_data' => null,
                    'no_data' => true
                ];
            }
            
            // Get top 10 users by total score - now joining with user_profiles for full names
            $stmt = $this->conn->prepare("
                SELECT wup.user_id, u.username, 
                       COALESCE(up.full_name, u.username) as display_name,
                       wup.total_score as score, wup.hints_used, 
                       TIMESTAMPDIFF(SECOND, '2023-01-01', wup.last_played) as time_spent
                FROM wordscapes_user_progress wup
                JOIN users u ON wup.user_id = u.user_id
                LEFT JOIN user_profiles up ON wup.user_id = up.user_id
                ORDER BY wup.total_score DESC, wup.hints_used ASC
                LIMIT 10
            ");
            $stmt->execute();
            $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check if current user is in top 10
            $userInTop10 = false;
            $userRank = null;
            $userData = null;
            
            if (isset($this->user_id)) {
                foreach ($topUsers as $index => $user) {
                    if ((int)$user['user_id'] === (int)$this->user_id) {
                        $userRank = $index + 1;
                        $userInTop10 = true;
                        break;
                    }
                }
                
                // If user not in top 10, find their rank
                if (!$userInTop10) {
                    try {
                        // First check if this user has any progress
                        $userCheck = $this->conn->prepare("
                            SELECT COUNT(*) FROM wordscapes_user_progress WHERE user_id = ?
                        ");
                        $userCheck->execute([$this->user_id]);
                        $hasProgress = $userCheck->fetchColumn() > 0;
                        
                        if ($hasProgress) {
                            $stmt = $this->conn->prepare("
                                SELECT COUNT(*) as rank
                                FROM wordscapes_user_progress
                                WHERE total_score > (
                                    SELECT total_score 
                                    FROM wordscapes_user_progress 
                                    WHERE user_id = ?
                                )
                            ");
                            $stmt->execute([$this->user_id]);
                            $userRank = $stmt->fetch(PDO::FETCH_COLUMN) + 1;
                            
                            // Get user's data - also get full name
                            $stmt = $this->conn->prepare("
                                SELECT wup.user_id, u.username, 
                                       COALESCE(up.full_name, u.username) as display_name,
                                       wup.total_score as score, wup.hints_used, 
                                       TIMESTAMPDIFF(SECOND, '2023-01-01', wup.last_played) as time_spent
                                FROM wordscapes_user_progress wup
                                JOIN users u ON wup.user_id = u.user_id
                                LEFT JOIN user_profiles up ON wup.user_id = up.user_id
                                WHERE wup.user_id = ?
                            ");
                            $stmt->execute([$this->user_id]);
                            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($userData) {
                                $userData['rank'] = $userRank;
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Error getting user rank: " . $e->getMessage());
                    }
                }
            }
            
            return [
                'top_users' => $topUsers,
                'user_rank' => $userRank,
                'user_in_top' => $userInTop10,
                'user_data' => !$userInTop10 && isset($userData) ? $userData : null,
                'no_data' => false
            ];
        } catch (PDOException $e) {
            error_log("Error getting leaderboard: " . $e->getMessage());
            return [
                'top_users' => [],
                'user_rank' => null,
                'user_in_top' => false,
                'user_data' => null,
                'no_data' => true,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if a specific level is accessible for the user
     * A level is accessible if:
     * 1. It's level 1 (always accessible)
     * 2. It's the user's current level
     * 3. It's a level that comes before the current level (user has passed it)
     * 4. It's the next level after the current level (only if current level is completed)
     */
    public function isLevelAccessible($level_number) {
        // Level 1 is always accessible
        if ($level_number == 1) {
            return true;
        }
        
        try {
            // Get the user's current level number
            $stmt = $this->conn->prepare("
                SELECT wl.level_number
                FROM wordscapes_user_progress wup
                JOIN wordscapes_levels wl ON wup.current_level_id = wl.level_id
                WHERE wup.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // If no progress exists, only level 1 is accessible
                return $level_number == 1;
            }
            
            $current_level_number = (int)$result['level_number'];
            
            // If the requested level is less than or equal to the current level, it's accessible
            if ($level_number <= $current_level_number) {
                return true;
            }
            
            // If the requested level is one more than the current level,
            // check if the current level is completed
            if ($level_number == $current_level_number + 1) {
                // Get the current level ID
                $stmt = $this->conn->prepare("
                    SELECT level_id FROM wordscapes_levels WHERE level_number = ?
                ");
                $stmt->execute([$current_level_number]);
                $current_level_id = $stmt->fetchColumn();
                
                // Check if current level is completed
                return $this->isLevelCompleted($current_level_id);
            }
            
            // Otherwise, level is not accessible
            return false;
            
        } catch (PDOException $e) {
            error_log("Error checking level accessibility: " . $e->getMessage());
            return $level_number == 1; // Only allow level 1 in case of error
        }
    }
    
    /**
     * Record a revealed hint for a specific word
     * 
     * @param string $word The word that had a hint revealed
     * @param int $position The position of the revealed letter (0-based index)
     * @return bool Success status
     */
    public function recordRevealedHint($word, $position) {
        // Convert word to lowercase for consistency
        $word = strtolower($word);
        
        // Initialize the word in the revealed_hints array if not exists
        if (!isset($this->game_data['revealed_hints'][$word])) {
            $this->game_data['revealed_hints'][$word] = [];
        }
        
        // Add the position if not already revealed
        if (!in_array($position, $this->game_data['revealed_hints'][$word])) {
            $this->game_data['revealed_hints'][$word][] = $position;
            
            // Save to session and database
            $_SESSION['wordscapes_game_data'] = $this->game_data;
            return $this->saveGameData();
        }
        
        return true;
    }
    
    /**
     * Get all revealed hints for the current level
     * 
     * @return array An array of [word => [positions]] for all revealed hints
     */
    public function getRevealedHints() {
        return $this->game_data['revealed_hints'] ?? [];
    }
    
    /**
     * Clear the session data for this game
     * Useful for testing and ensuring fresh data from database
     * 
     * @return bool Success status
     */
    public function clearSessionData() {
        if (isset($_SESSION['wordscapes_game_data'])) {
            unset($_SESSION['wordscapes_game_data']);
            return true;
        }
        return false;
    }
} 