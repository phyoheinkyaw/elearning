<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Get the absolute path to the includes directory
$includes_path = dirname(__DIR__, 2) . '/includes';
require_once $includes_path . '/db.php';
require_once $includes_path . '/functions.php';
require_once $includes_path . '/game_functions.php';
require_once __DIR__ . '/includes/game_manager.php';

// Initialize game manager
$user_id = $_SESSION['user_id'];

// Create game manager instance to get the current level info
$initialGameManager = new WordscapesGameManager($conn, $user_id);

// Get the user's current level from the database
$current_level_number = $initialGameManager->getUserCurrentLevel();

// If the URL contains a level parameter, check if it matches the current level
if (isset($_GET['level']) && (int)$_GET['level'] !== $current_level_number) {
    // Redirect to the correct level with an explanatory message
    $_SESSION['wordscapes_error'] = "You've been redirected to your current level (Level {$current_level_number}).";
    header("Location: index.php?level=" . $current_level_number);
    exit;
} elseif (!isset($_GET['level'])) {
    // If no level is specified in the URL, add it for consistency
    header("Location: index.php?level=" . $current_level_number);
    exit;
}

// Save the current level number to session
$_SESSION['wordscapes_current_level_number'] = $current_level_number;

// Get the level_id based on level_number
$stmt = $conn->prepare("SELECT level_id FROM wordscapes_levels WHERE level_number = ?");
$stmt->execute([$current_level_number]);
$level_data = $stmt->fetch(PDO::FETCH_ASSOC);

// If level doesn't exist, default to level 1
if (!$level_data) {
    $stmt = $conn->prepare("SELECT level_id FROM wordscapes_levels WHERE level_number = 1");
    $stmt->execute();
    $level_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Reset current level number to 1
    $current_level_number = 1;
    $_SESSION['wordscapes_current_level_number'] = 1;
}

$level_id = $level_data['level_id'];

// Initialize game manager with the correct level ID
$gameManager = new WordscapesGameManager($conn, $user_id, $level_id);

// Get total number of levels
$stmt = $conn->prepare("SELECT COUNT(*) as total_levels FROM wordscapes_levels");
$stmt->execute();
$total_levels = $stmt->fetch(PDO::FETCH_ASSOC)['total_levels'];

// Get current level data
$current_level = $gameManager->getLevelData($level_id);

// If level doesn't exist or is invalid, redirect to first level
if (!$current_level) {
    header("Location: index.php?level=1");
    exit;
}

// Get all words for this level
$level_words = $gameManager->getLevelWords();

// If no words found, redirect to first level
if (empty($level_words)) {
    header("Location: index.php?level=1");
    exit;
}

// Helper function to generate hints
function generateHints($word, $words) {
    $length = strlen($word);
    
    // Get all possible positions except the first letter
    $positions = range(0, $length - 1);
    
    // For less than 6 words: 1 random hint (excluding first letter)
    if (count($words) < 6) {
        return [rand(0, $length - 1)];
    }
    // For less than 12 words: 2 random hints (excluding first letter)
    else if (count($words) < 12) {
        shuffle($positions);
        return array_slice($positions, 0, 2);
    }
    // For 12 or more words: 3 random hints (excluding first letter)
    else {
        shuffle($positions);
        return array_slice($positions, 0, 3);
    }
}

// Helper function to get hints from session or generate new ones
function getHintsForLevel($level_id, $words) {
    $session_key = "wordscapes_hints_level_{$level_id}";
    
    if (!isset($_SESSION[$session_key])) {
        $hints = [];
        foreach ($words as $word) {
            // Generate hints using the word
            $wordHints = generateHints($word, $words);
            
            // Store the hints in session
            $session_key_positions = "wordscapes_positions_level_{$level_id}_" . $word;
            $_SESSION[$session_key_positions] = $wordHints;
            
            $hints[strtoupper($word)] = $wordHints;
        }
        $_SESSION[$session_key] = $hints;
    }
    
    return $_SESSION[$session_key];
}

// Get hints for this level
$hintsForLevel = getHintsForLevel($level_id, $level_words);

// Parse the given letters string (e.g., 'APLE')
$letters = str_split($current_level['given_letters']);
shuffle($letters);

// Store the original letters in session to prevent shuffling on reload
$session_key_letters = "wordscapes_letters_level_{$level_id}";
if (!isset($_SESSION[$session_key_letters])) {
    $_SESSION[$session_key_letters] = $letters;
}

// Get game data
$game_data = $gameManager->getGameData();

// Page title
$page_title = "Wordscapes - Level " . $current_level['level_number'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../../css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="../../css/search-autocomplete.css">
    <script>
        // Make hints available to JavaScript
        window.gameHints = <?php echo json_encode($hintsForLevel); ?>;
    </script>
    <style>
        /* Game container and elements */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Help Modal Styles */
        .help-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            overflow-y: auto;
        }
        
        .help-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            width: 90%;
            max-width: 600px;
            border-radius: 15px;
            position: relative;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .help-modal-header {
            background-color: #3498db;
            color: white;
            padding: 15px 20px;
            position: relative;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        
        .help-modal-header h5 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .help-modal-close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
            transition: transform 0.2s;
        }
        
        .help-modal-close-btn:hover {
            transform: scale(1.2);
        }
        
        .help-modal-body {
            padding: 20px;
            overflow-y: auto;
            max-height: calc(85vh - 130px);
        }
        
        .help-section-title {
            color: #3498db;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .help-section-content {
            margin-bottom: 20px;
            line-height: 1.6;
            color: #333;
        }
        
        .help-section-content ul {
            padding-left: 20px;
        }
        
        .help-section-content li {
            margin-bottom: 8px;
        }
        
        .help-modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
            text-align: right;
            border-top: 1px solid #eee;
        }
        
        .help-modal-footer .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            padding: 8px 20px;
            font-weight: 500;
            border-radius: 30px;
            transition: all 0.2s;
        }
        
        .help-modal-footer .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Floating Help Button Styles */
        .floating-help-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            z-index: 1500;
            transition: all 0.3s ease;
        }
        
        .help-tooltip {
            position: absolute;
            top: -40px;
            right: 0;
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            white-space: nowrap;
        }
        
        .help-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            right: 20px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        .floating-help-button:hover .help-tooltip {
            opacity: 1;
        }
        
        .floating-help-button:hover {
            background-color: #2980b9;
            transform: scale(1.1);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        }
        
        .floating-help-button i {
            animation: pulse 2s infinite;
        }
        
        /* Introduction tooltip for first-time users */
        .intro-tooltip {
            position: fixed;
            z-index: 1600;
            background: transparent;
            transition: opacity 0.3s ease;
            pointer-events: auto;
            right: 30px; /* Will be overridden by JS */
            bottom: 100px; /* Will be overridden by JS */
        }
        
        .intro-tooltip-content {
            background-color: #fff;
            color: #333;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 250px;
            border: 1px solid #e0e0e0;
            animation: fadeInUp 0.5s forwards;
            position: relative;
            text-align: center;
        }
        
        .intro-tooltip-content p {
            margin: 0 0 10px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .intro-tooltip-content p:first-child {
            color: #3498db;
        }
        
        .intro-tooltip-arrow {
            position: absolute;
            width: 0;
            height: 0;
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-top: 10px solid #fff;
            bottom: -10px;
            right: 30px; /* Align with the center of the help button */
            filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.1));
        }
        
        .intro-tooltip-close {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            margin-top: 5px;
            display: block;
            width: 100%;
            transition: background-color 0.2s;
        }
        
        .intro-tooltip-close:hover {
            background-color: #2980b9;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @media (max-width: 768px) {
            .floating-help-button {
                width: 50px;
                height: 50px;
                font-size: 20px;
                bottom: 20px;
                right: 20px;
            }
            
            .button-container button {
                padding: 10px 18px;
                font-size: 14px;
                margin: 0 5px 10px;
            }
            
            .shuffle-btn, .reset-btn {
                flex: 1;
                min-width: 120px;
                max-width: unset;
            }
            
            .submit-container {
                margin-top: 20px;
            }
            
            .submit-btn {
                min-width: 180px;
            }
            
            .help-modal-content {
                width: 95%;
                margin: 10px auto;
                max-height: 90vh;
            }
            
            .help-modal-header h5 {
                font-size: 1.1rem;
            }
            
            .help-section-title {
                font-size: 1rem;
            }
            
            .help-modal-body {
                padding: 15px;
                max-height: calc(90vh - 120px);
            }
            
            .intro-tooltip-content {
                max-width: 200px;
                padding: 12px;
            }
            
            .intro-tooltip-content p {
                font-size: 13px;
                margin-bottom: 8px;
            }
            
            .intro-tooltip-close {
                padding: 5px 10px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .floating-help-button {
                width: 45px;
                height: 45px;
                font-size: 18px;
                bottom: 15px;
                right: 15px;
            }
            
            .button-container {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                margin: 15px 0;
            }
            
            .button-container button {
                margin: 0 3px;
                padding: 8px 10px;
                font-size: 12px;
                flex: 1;
                min-height: 40px;
                white-space: nowrap;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .shuffle-btn, .reset-btn {
                max-width: 49%;
                width: 49%;
            }
            
            .button-container.submit-container {
                justify-content: center;
                margin-top: 15px;
                margin-bottom: 20px;
                flex-direction: column;
            }
            
            .submit-btn {
                width: 70%;
                max-width: 180px;
                padding: 10px 15px;
                font-size: 14px;
            }
            
            .button-text {
                display: none !important;
            }
            
            .button-text-sm {
                display: inline-block !important;
                font-size: 12px;
                margin-left: 2px;
                visibility: visible !important;
            }
            
            .shuffle-btn span.button-text-sm, 
            .reset-btn span.button-text-sm,
            .submit-btn span.button-text-sm {
                display: inline-block !important;
            }
            
            .button-container button i {
                margin-right: 3px;
                font-size: 12px;
            }
            
            .help-tooltip {
                font-size: 12px;
                top: -35px;
                padding: 4px 8px;
            }
        }
        
        .bounce-animation {
            animation: bounce 1s ease;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }
        
        /* Game button styles */
        .button-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .button-container button {
            margin: 0 10px;
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .submit-container {
            margin-top: 30px;
        }
        
        .submit-btn {
            background: linear-gradient(to right, #4caf50, #45a049);
            color: white;
            min-width: 200px;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
            transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
            pointer-events: none;
        }
        
        .submit-btn:hover {
            background: linear-gradient(to right, #45a049, #2e7d32);
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }
        
        .submit-btn:hover::before {
            transform: rotate(45deg) translateY(100%);
        }
        
        .submit-btn i {
            animation: submitPulse 2s infinite;
            margin-right: 8px;
            transition: transform 0.3s ease;
        }
        
        .submit-btn:hover i {
            transform: translateX(3px);
        }
        
        @keyframes submitPulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .reset-btn {
            background: linear-gradient(to right, #ff9800, #f57c00);
            color: white;
        }
        
        .reset-btn:hover {
            background: linear-gradient(to right, #f57c00, #e65100);
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }
        
        .shuffle-container {
            margin-top: 0;
        }
        
        .shuffle-btn {
            background: linear-gradient(to right, #3f51b5, #303f9f);
            color: white;
            width: 100%;
            max-width: 250px;
            position: relative;
            overflow: hidden;
        }
        
        .shuffle-btn::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
            transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
            pointer-events: none;
        }
        
        .shuffle-btn:hover {
            background: linear-gradient(to right, #303f9f, #1a237e);
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }
        
        .shuffle-btn:hover::before {
            transform: rotate(45deg) translateY(100%);
        }
        
        .shuffle-btn i {
            animation: rotateShuffle 3s infinite;
            margin-right: 8px;
        }
        
        @keyframes rotateShuffle {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(0deg); }
            50% { transform: rotate(180deg); }
            75% { transform: rotate(180deg); }
            100% { transform: rotate(360deg); }
        }
        
        .button-container button:active {
            transform: translateY(1px);
            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
        }
        
        .button-container button i {
            margin-right: 8px;
        }
        
        .button-text-sm {
            display: none;
        }
    </style>
</head>
<body>
    <?php include $includes_path . '/nav.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <!-- Simple breadcrumb instead of navigation -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="../">Games</a></li>
                            <li class="breadcrumb-item active">Wordscapes</li>
                        </ol>
                    </nav>
                </div>
                
                <div class="game-container" id="gameContainer" data-level-id="<?php echo $level_id; ?>" data-total-levels="<?php echo $total_levels; ?>" data-user-id="<?php echo $user_id; ?>" data-level-number="<?php echo $current_level['level_number']; ?>">
                    <?php 
                    // Display error message if there is one
                    if (isset($_SESSION['wordscapes_error'])) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-lock me-2"></i> ' . htmlspecialchars($_SESSION['wordscapes_error']) . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                        // Clear the error message
                        unset($_SESSION['wordscapes_error']);
                    }
                    ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <h1>Wordscapes - Level <?php echo $current_level['level_number']; ?></h1>
                    </div>
                    
                    <div class="wordscapes-layout">
                        <div class="game-main">
                            <div class="game-controls">
                                <div class="button-container">
                                    <button id="shuffleLetters" class="shuffle-btn">
                                        <i class="fas fa-random"></i> <span class="button-text">Shuffle Letters</span>
                                        <span class="button-text-sm">Shuffle</span>
                                    </button>
                                    <button id="resetWord" class="reset-btn">
                                        <i class="fas fa-backspace"></i> <span class="button-text">Reset Word</span>
                                        <span class="button-text-sm">Reset</span>
                                    </button>
                                </div>

                                <div class="fill-boxes-container">
                                    <div class="fill-boxes">
                                        <?php
                                        // Create fill boxes based on the number of letters in given_letters
                                        $numLetters = strlen($current_level['given_letters']);
                                        for ($i = 0; $i < $numLetters; $i++): ?>
                                            <div class="fill-box"></div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="letter-container">
                                    <?php foreach ($letters as $letter): ?>
                                        <div class="letter" data-letter="<?php echo htmlspecialchars($letter); ?>">
                                            <?php echo htmlspecialchars($letter); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="button-container submit-container">
                                    <button id="submitWord" class="submit-btn">
                                        <i class="fas fa-paper-plane"></i> <span class="button-text">Submit Word</span>
                                        <span class="button-text-sm">Submit</span>
                                    </button>
                                </div>
                                
                                <div class="answer-section">
                                    <h3>Words to Find</h3>
                                    <?php foreach ($level_words as $word): ?>
                                        <div class="answer-group">
                                            <div class="answer-fill-boxes">
                                                <?php
                                                $wordLetters = str_split($word);
                                                foreach ($wordLetters as $index => $letter):
                                                    $isHint = isset($hintsForLevel[strtoupper($word)]) && in_array($index, $hintsForLevel[strtoupper($word)]);
                                                    ?>
                                                    <div class="answer-box <?php echo $isHint ? 'success' : ''; ?>">
                                                        <?php echo $isHint ? htmlspecialchars(strtoupper($letter)) : ''; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="answer-word" data-word="<?php echo htmlspecialchars($word); ?>">
                                                <?php 
                                                // Check if word has been found, if so, show green check mark icon
                                                if (in_array(strtolower($word), array_map('strtolower', $game_data['found_words'] ?? []))) {
                                                    echo '<i class="fas fa-check-circle text-success ms-2"></i>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            </div>
                        </div>
                        
                        <div class="game-sidebar">
                            <div class="level-info">
                                <h3>Level Information</h3>
                                <p>Level: <strong><?php echo $current_level['level_number']; ?> / <?php echo $total_levels; ?></strong></p>
                                <p>Difficulty: <strong><?php echo $current_level['difficulty'] == 1 ? 'Easy' : ($current_level['difficulty'] == 2 ? 'Medium' : 'Hard'); ?></strong></p>
                                <p>Words to find: <strong><?php echo count($level_words); ?></strong></p>
                                
                                <?php 
                                // Calculate completion percentage
                                $found = count($game_data['found_words'] ?? []);
                                $total = count($level_words);
                                $percentage = $total > 0 ? round(($found / $total) * 100) : 0; 
                                ?>
                                
                                <div class="level-progress">
                                    <div class="level-progress-bar" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                                <p><small><span class="found-count"><?php echo $found; ?></span> of <span class="total-count"><?php echo $total; ?></span> words found (<span class="percentage-display"><?php echo $percentage; ?></span>%)</small></p>
                            </div>
                            
                            <div class="persistent-leaderboard">
                                <h3>Leaderboard</h3>
                                <div id="persistentLeaderboard">
                                    <p class="text-center">Loading leaderboard data...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php 
    // No more fallback sections - modal will only use content from the database
    ?>

    <!-- Custom modal implementation that doesn't rely on Bootstrap's modal component -->
    <div id="helpModalOverlay" class="help-modal-overlay" role="dialog" aria-labelledby="helpModalTitle" aria-modal="true">
        <div id="helpModalContent" class="help-modal-content">
            <div class="help-modal-header">
                <h5 id="helpModalTitle">How to Play Wordscapes</h5>
                <button id="closeHelpBtn" class="help-modal-close-btn" aria-label="Close help modal">&times;</button>
            </div>
            <div class="help-modal-body">
                <?php
                // Get help content from database only
                $helpContent = [];
                try {
                    $stmt = $conn->prepare("
                        SELECT section_title, section_content 
                        FROM games_help 
                        WHERE game_id = (SELECT game_id FROM games_info WHERE game_folder = 'wordscapes')
                        ORDER BY display_order
                    ");
                    $stmt->execute();
                    $helpContent = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // Error fetching help content (don't show error to user)
                    error_log("Error fetching help content: " . $e->getMessage());
                }
                
                if (!empty($helpContent)) {
                    foreach ($helpContent as $section): ?>
                        <h5 class="help-section-title"><?php echo htmlspecialchars($section['section_title']); ?></h5>
                        <div class="help-section-content"><?php echo $section['section_content']; ?></div>
                    <?php endforeach;
                } else {
                    // If no help content exists in database, show message to admin
                    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 0) { ?>
                        <div class="alert alert-warning">
                            <p>No help content found for Wordscapes in the database.</p>
                            <p>Please add content through the admin interface.</p>
                        </div>
                    <?php } else { ?>
                        <p>Help content is not available at this time.</p>
                    <?php }
                }
                ?>
            </div>
            <div class="help-modal-footer">
                <button id="closeHelpBtnBottom" class="btn btn-primary">Got it!</button>
            </div>
        </div>
    </div>

    <?php include $includes_path . '/footer.php'; ?>

    <!-- Floating Help Button -->
    <div class="floating-help-button" id="openHelpBtn" title="Game Instructions" 
         role="button" 
         tabindex="0" 
         aria-label="Open game instructions and help"
         aria-haspopup="dialog">
        <i class="fas fa-question" aria-hidden="true"></i>
        <span class="help-tooltip">Need help?</span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple custom modal implementation
        document.addEventListener('DOMContentLoaded', function() {
            const openHelpBtn = document.getElementById('openHelpBtn');
            const helpModalOverlay = document.getElementById('helpModalOverlay');
            const closeHelpBtn = document.getElementById('closeHelpBtn');
            const closeHelpBtnBottom = document.getElementById('closeHelpBtnBottom');
            const helpModalContent = document.getElementById('helpModalContent');
            
            // Function to handle button text display based on screen size
            function handleMobileButtonText() {
                const isMobile = window.innerWidth <= 480;
                const buttonTexts = document.querySelectorAll('.button-text');
                const buttonTextsSm = document.querySelectorAll('.button-text-sm');
                
                buttonTexts.forEach(text => {
                    text.style.display = isMobile ? 'none' : 'inline';
                });
                
                buttonTextsSm.forEach(text => {
                    text.style.display = isMobile ? 'inline' : 'none';
                });
            }
            
            // Run on page load
            handleMobileButtonText();
            
            // Run on window resize
            window.addEventListener('resize', handleMobileButtonText);
            
            // Store last focused element to restore focus when modal closes
            let lastFocusedElement = null;
            
            // Add initial bounce animation to the help button
            if (openHelpBtn) {
                // Add the bounce class after a slight delay so users notice it
                setTimeout(() => {
                    openHelpBtn.classList.add('bounce-animation');
                    // Remove the animation class after it completes
                    setTimeout(() => {
                        openHelpBtn.classList.remove('bounce-animation');
                    }, 1000);
                }, 1500);
                
                // Add keyboard support for the help button
                openHelpBtn.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        if (helpModalOverlay) {
                            helpModalOverlay.style.display = 'block';
                            
                            // Set focus to the close button for better keyboard navigation
                            if (closeHelpBtn) {
                                closeHelpBtn.focus();
                            }
                        }
                    }
                });
            }
            
            // Show modal
            if (openHelpBtn) {
                openHelpBtn.addEventListener('click', function() {
                    if (helpModalOverlay) {
                        // Store the currently focused element
                        lastFocusedElement = document.activeElement;
                        
                        // Show modal
                        helpModalOverlay.style.display = 'block';
                        
                        // Focus the close button
                        if (closeHelpBtn) {
                            closeHelpBtn.focus();
                        }
                    }
                });
                
                // Show first-time user notification about help button
                // Only show if it's the first visit (using localStorage)
                if (!localStorage.getItem('helpButtonIntroShown')) {
                    // Create and show the introduction tooltip
                    const introTooltip = document.createElement('div');
                    introTooltip.className = 'intro-tooltip';
                    introTooltip.innerHTML = `
                        <div class="intro-tooltip-content">
                            <p><strong>Need help?</strong></p>
                            <p>Click this button anytime to see game instructions!</p>
                            <button class="intro-tooltip-close">Got it!</button>
                        </div>
                        <div class="intro-tooltip-arrow"></div>
                    `;
                    document.body.appendChild(introTooltip);
                    
                    // Simple positioning directly with the help button
                    const helpButtonRect = openHelpBtn.getBoundingClientRect();
                    introTooltip.style.bottom = (window.innerHeight - helpButtonRect.top + 20) + 'px';
                    
                    // Position tooltip to align with the help button
                    // A slight offset to the left to center it better
                    introTooltip.style.right = (window.innerWidth - helpButtonRect.right - 25) + 'px';
                    
                    // Variable to track if user clicked "Got it"
                    let userClickedGotIt = false;
                    
                    // Add close button functionality
                    const closeIntroBtn = introTooltip.querySelector('.intro-tooltip-close');
                    if (closeIntroBtn) {
                        closeIntroBtn.addEventListener('click', function() {
                            userClickedGotIt = true; // Mark that user clicked the button
                            introTooltip.style.opacity = '0';
                            
                            // Only set localStorage if user clicks "Got it"
                            localStorage.setItem('helpButtonIntroShown', 'true');
                            
                            // Remove the tooltip after fade out
                            setTimeout(() => {
                                if (introTooltip.parentNode) {
                                    introTooltip.remove();
                                }
                            }, 300);
                        });
                    }
                    
                    // Auto hide after 10 seconds WITHOUT setting localStorage
                    setTimeout(() => {
                        if (introTooltip.parentNode && !userClickedGotIt) {
                            introTooltip.style.opacity = '0';
                            setTimeout(() => {
                                if (introTooltip.parentNode) {
                                    introTooltip.remove();
                                }
                            }, 300);
                            // No localStorage update for auto-dismiss
                        }
                    }, 10000);
                }
            }
            
            // Hide modal with close button
            if (closeHelpBtn) {
                closeHelpBtn.addEventListener('click', function() {
                    if (helpModalOverlay) {
                        helpModalOverlay.style.display = 'none';
                        
                        // Restore focus to the previous element
                        if (lastFocusedElement) {
                            lastFocusedElement.focus();
                        }
                    }
                });
            }
            
            // Hide modal with bottom close button
            if (closeHelpBtnBottom) {
                closeHelpBtnBottom.addEventListener('click', function() {
                    if (helpModalOverlay) {
                        helpModalOverlay.style.display = 'none';
                        
                        // Restore focus to the previous element
                        if (lastFocusedElement) {
                            lastFocusedElement.focus();
                        }
                    }
                });
            }
            
            // Hide modal when clicking outside
            if (helpModalOverlay) {
                helpModalOverlay.addEventListener('click', function(e) {
                    if (e.target === helpModalOverlay) {
                        helpModalOverlay.style.display = 'none';
                        
                        // Restore focus to the previous element
                        if (lastFocusedElement) {
                            lastFocusedElement.focus();
                        }
                    }
                });
                
                // Trap focus inside modal when open
                helpModalOverlay.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        // Close modal on Escape key
                        helpModalOverlay.style.display = 'none';
                        
                        // Restore focus to the previous element
                        if (lastFocusedElement) {
                            lastFocusedElement.focus();
                        }
                        return;
                    }
                    
                    if (e.key === 'Tab') {
                        // Get all focusable elements in the modal
                        const focusableElements = helpModalContent.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                        const firstElement = focusableElements[0];
                        const lastElement = focusableElements[focusableElements.length - 1];
                        
                        // If shift+tab on first element, go to last element
                        if (e.shiftKey && document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement.focus();
                        }
                        // If tab on last element, go to first element
                        else if (!e.shiftKey && document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                });
            }
        });
    </script>
    <script src="js/game.js"></script>
</body>
</html>
