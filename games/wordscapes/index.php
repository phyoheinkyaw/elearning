<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
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

// Helper function to get hint letters for a word
function getHintLetters($word, $level_id) {
    $session_key = "wordscapes_positions_level_{$level_id}_" . $word;
    if (isset($_SESSION[$session_key])) {
        $positions = $_SESSION[$session_key];
        $hintLetters = [];
        foreach ($positions as $pos) {
            $hintLetters[] = strtoupper($word[$pos]);
        }
        return $hintLetters;
    }
    return [];
}

// Helper function to clear hints session when level is completed
function clearHintsSession($level_id) {
    $session_key = "wordscapes_hints_level_{$level_id}";
    if (isset($_SESSION[$session_key])) {
        unset($_SESSION[$session_key]);
    }
}

// Get the absolute path to the includes directory
$includes_path = dirname(__DIR__, 2) . '/includes';
require_once $includes_path . '/db.php';
require_once $includes_path . '/functions.php';

// Get total number of levels
$stmt = $conn->prepare("SELECT COUNT(*) as total_levels FROM wordscapes_levels");
$stmt->execute();
$total_levels = $stmt->fetch(PDO::FETCH_ASSOC)['total_levels'];

// Get current level
            $level_id = isset($_GET['level']) ? (int)$_GET['level'] : 1;

// If level is beyond total levels, redirect to last level
if ($level_id > $total_levels) {
    header("Location: index.php?level={$total_levels}");
    exit;
}

            // Get current level data
            $stmt = $conn->prepare("SELECT * FROM wordscapes_levels WHERE level_id = ?");
            $stmt->execute([$level_id]);
            $level = $stmt->fetch(PDO::FETCH_ASSOC);

// If level doesn't exist or is invalid, redirect to first level
if (!$level) {
    header("Location: index.php?level=1");
    exit;
}

// Get all words for this level
$stmt = $conn->prepare("SELECT word FROM wordscapes_words WHERE level_id = ? ORDER BY word");
            $stmt->execute([$level_id]);
            $words = $stmt->fetchAll(PDO::FETCH_COLUMN);

// If no words found, redirect to first level
if (empty($words)) {
    header("Location: index.php?level=1");
    exit;
}

            // Get hints for this level
            $hintsForLevel = getHintsForLevel($level_id, $words);

            // Parse the given letters string (e.g., 'APLE')
            $letters = str_split($level['given_letters']);
            shuffle($letters);

            // Store the original letters in session to prevent shuffling
            $session_key_letters = "wordscapes_letters_level_{$level_id}";
            if (!isset($_SESSION[$session_key_letters])) {
                $_SESSION[$session_key_letters] = $letters;
            }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wordscapes - Level <?php echo $level['level_number']; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../../css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script>
        // Make hints available to JavaScript
        window.gameHints = <?php echo json_encode($hintsForLevel); ?>;
    </script>
</head>
<body>
    <?php include $includes_path . '/nav.php'; ?>
    <div class="game-container" id="gameContainer" data-level-id="<?php echo $level_id; ?>" data-total-levels="<?php echo $total_levels; ?>">
        <h1>Wordscapes - Level <?php echo $level['level_number']; ?></h1>
        
        <div class="game-controls">
            <div class="button-container">
                <button id="submitWord" class="submit-btn">Submit</button>
                <button id="resetGame" class="reset-btn">Reset Game</button>
                </div>

                <div class="fill-boxes-container">
                    <div class="fill-boxes">
                        <?php
                        // Create fill boxes based on the number of letters in given_letters
                        $numLetters = strlen($level['given_letters']);
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

        <div class="button-container">
            <button id="shuffleLetters" class="shuffle-btn" style="background-color: orange;">Shuffle Letters</button>
                </div>

                <div class="answer-section">
                    <h3>Answers</h3>
                    <?php foreach ($words as $word): ?>
                        <div class="answer-group">
                    <div class="answer-hint">
                        <div class="hint-container">
                            <!-- Removed hint position display -->
                        </div>
                    </div>
                            <div class="answer-fill-boxes">
                                <?php
                                $wordLetters = str_split($word);
                                foreach ($wordLetters as $index => $letter):
                                    $isHint = isset($hintsForLevel[strtoupper($word)]) && in_array($index, $hintsForLevel[strtoupper($word)]);
                                    ?>
                                    <div class="answer-box" <?php echo $isHint ? 'class="success"' : ''; ?>>
                                        <?php echo $isHint ? htmlspecialchars($letter) : ''; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="answer-word" data-word="<?php echo htmlspecialchars($word); ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include $includes_path . '/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/game.js"></script>
</body>
</html>
