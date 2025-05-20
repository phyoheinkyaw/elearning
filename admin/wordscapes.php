<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log all POST data for debugging
    error_log("POST data: " . print_r($_POST, true));
    
    if (!isset($_POST['action'])) {
        $_SESSION['error_message'] = "Invalid form submission";
        header('Location: wordscapes.php');
        exit();
    }

    try {
        $conn->beginTransaction();
        
        switch ($_POST['action']) {
            case 'add_level':
                // Get all required fields
                $required_fields = ['game_id', 'difficulty', 'level_number', 'given_letters', 'words'];
                $missing_fields = array_filter($required_fields, function($field) {
                    return !isset($_POST[$field]);
                });
                
                if (!empty($missing_fields)) {
                    throw new Exception("Missing required fields: " . implode(", ", $missing_fields));
                }

                // Get and validate all fields
                $game_id = (int)$_POST['game_id'];
                $difficulty = (int)$_POST['difficulty'];
                $level_number = (int)$_POST['level_number'];
                $given_letters = trim($_POST['given_letters']);
                $words = array_filter(array_map('trim', $_POST['words']));
                $unique_words = array_unique($words);

                // Validate inputs
                if ($difficulty < 1 || $difficulty > 3) {
                    throw new Exception("Invalid difficulty level. Must be between 1 and 3.");
                }
                if (count($unique_words) < 2) {
                    throw new Exception("At least two unique words are required.");
                }

                // Check if game exists
                $stmt = $conn->prepare("SELECT game_id FROM games_info WHERE game_id = ?");
                $stmt->execute([$game_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Invalid game selected.");
                }

                // Check if level number already exists for this game
                $stmt = $conn->prepare("SELECT level_id FROM wordscapes_levels WHERE level_number = ? AND game_id = ?");
                $stmt->execute([$level_number, $game_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Level number already exists for this game. Please choose a different number.");
                }

                // Insert level
                $stmt = $conn->prepare("INSERT INTO wordscapes_levels (game_id, difficulty, level_number, given_letters) VALUES (?, ?, ?, ?)");
                $stmt->execute([$game_id, $difficulty, $level_number, $given_letters]);
                $level_id = $conn->lastInsertId();
                
                // Insert words
                $stmt = $conn->prepare("INSERT INTO wordscapes_words (level_id, word) VALUES (?, ?)");
                foreach ($unique_words as $word) {
                    $stmt->execute([$level_id, $word]);
                }

                $_SESSION['success_message'] = "Level added successfully.";
                break;

            case 'edit_level':
                // Get all required fields
                $required_fields = ['level_id', 'game_id', 'difficulty', 'level_number', 'given_letters', 'words'];
                $missing_fields = array_filter($required_fields, function($field) {
                    return !isset($_POST[$field]);
                });
                
                if (!empty($missing_fields)) {
                    throw new Exception("Missing required fields: " . implode(", ", $missing_fields));
                }

                // Get and validate all fields
                $level_id = (int)$_POST['level_id'];
                $game_id = (int)$_POST['game_id'];
                $difficulty = (int)$_POST['difficulty'];
                $level_number = (int)$_POST['level_number'];
                $given_letters = trim($_POST['given_letters']);
                $words = array_filter(array_map('trim', $_POST['words']));
                $unique_words = array_unique($words);

                // Validate inputs
                if ($difficulty < 1 || $difficulty > 3) {
                    throw new Exception("Invalid difficulty level. Must be between 1 and 3.");
                }
                if (count($unique_words) < 2) {
                    throw new Exception("At least two unique words are required.");
                }

                // Check if game exists
                $stmt = $conn->prepare("SELECT game_id FROM games_info WHERE game_id = ?");
                $stmt->execute([$game_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Invalid game selected.");
                }

                // Check if level number already exists for another level in the same game
                $stmt = $conn->prepare("SELECT level_id FROM wordscapes_levels WHERE level_number = ? AND game_id = ? AND level_id != ?");
                $stmt->execute([$level_number, $game_id, $level_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Level number already exists for this game. Please choose a different number.");
                }

                // Update level
                $stmt = $conn->prepare("UPDATE wordscapes_levels SET game_id = ?, difficulty = ?, level_number = ?, given_letters = ? WHERE level_id = ?");
                $stmt->execute([$game_id, $difficulty, $level_number, $given_letters, $level_id]);

                // Update words
                // First delete existing words
                $stmt = $conn->prepare("DELETE FROM wordscapes_words WHERE level_id = ?");
                $stmt->execute([$level_id]);

                // Insert new words
                $stmt = $conn->prepare("INSERT INTO wordscapes_words (level_id, word) VALUES (?, ?)");
                foreach ($unique_words as $word) {
                    $stmt->execute([$level_id, $word]);
                }

                $_SESSION['success_message'] = "Level updated successfully.";
                break;

            case 'delete_level':
                if (!isset($_POST['level_id'])) {
                    throw new Exception("Missing level ID");
                }

                $level_id = (int)$_POST['level_id'];
                
                // Delete words first
                $stmt = $conn->prepare("DELETE FROM wordscapes_words WHERE level_id = ?");
                $stmt->execute([$level_id]);
                
                // Delete level
                $stmt = $conn->prepare("DELETE FROM wordscapes_levels WHERE level_id = ?");
                $stmt->execute([$level_id]);
                
                $_SESSION['success_message'] = "Level deleted successfully.";
                break;
        }
        
        $conn->commit();
        header('Location: wordscapes.php');
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        
        // Log the error for debugging
        error_log("Error in wordscapes.php: " . $e->getMessage());
        
        // Set a user-friendly error message
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: wordscapes.php');
        exit();
    }
}

// Get all levels with words and highest level number
try {
    $stmt = $conn->query("SELECT l.*, g.title as game_title, GROUP_CONCAT(w.word ORDER BY w.word SEPARATOR ', ') as words 
                        FROM wordscapes_levels l 
                        JOIN games_info g ON l.game_id = g.game_id
                        LEFT JOIN wordscapes_words w ON l.level_id = w.level_id 
                        GROUP BY l.level_id 
                        ORDER BY g.title, l.level_number");
    $levels = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching levels: " . $e->getMessage();
    header('Location: wordscapes.php');
    exit();
}

// Get next level number
$stmt = $conn->prepare("SELECT MAX(level_number) as max_level FROM wordscapes_levels");
$stmt->execute();
$row = $stmt->fetch();
$next_level = $row['max_level'] ? $row['max_level'] + 1 : 1;

// Get all games for select dropdown
try {
    $stmt = $conn->query("SELECT game_id, title, game_folder FROM games_info ORDER BY title");
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    $games = [];
}

// Helper function to convert difficulty to text
function getDifficultyText($difficulty) {
    return match($difficulty) {
        1 => 'Easy',
        2 => 'Medium',
        3 => 'Hard',
        default => 'Unknown',
    };
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wordscapes - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="../js/lib/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="../js/lib/jquery-3.7.1.min.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-2">Wordscapes</h1>
                        <p class="text-muted mb-0">Manage wordscapes game levels and words</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLevelModal">
                        <i class="fas fa-plus me-2"></i>Add New Level
                    </button>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="levelsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Game</th>
                                        <th>Level</th>
                                        <th>Difficulty</th>
                                        <th>Letters</th>
                                        <th>Words</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($levels as $level): ?>
                                        <tr>
                                            <td><?php echo $level['level_id']; ?></td>
                                            <td><?php echo htmlspecialchars($level['game_title']); ?></td>
                                            <td><?php echo $level['level_number']; ?></td>
                                            <td><?php echo getDifficultyText($level['difficulty']); ?></td>
                                            <td><?php echo htmlspecialchars($level['given_letters']); ?></td>
                                            <td><?php echo htmlspecialchars($level['words'] ?? ''); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary edit-level-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editLevelModal"
                                                        data-level-id="<?php echo $level['level_id']; ?>"
                                                        data-game-id="<?php echo $level['game_id']; ?>"
                                                        data-difficulty="<?php echo $level['difficulty']; ?>"
                                                        data-level-number="<?php echo $level['level_number']; ?>"
                                                        data-given-letters="<?php echo htmlspecialchars($level['given_letters']); ?>"
                                                        data-words="<?php echo htmlspecialchars($level['words'] ?? ''); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger delete-level-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteLevelModal"
                                                        data-level-id="<?php echo $level['level_id']; ?>"
                                                        data-level-number="<?php echo $level['level_number']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Level Modal -->
    <div class="modal fade" id="addLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_level">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="game_id" class="form-label">Game <span class="text-danger">*</span></label>
                                <select class="form-select" id="game_id" name="game_id" required>
                                    <option value="">Select a game</option>
                                    <?php foreach ($games as $game): ?>
                                        <option value="<?php echo $game['game_id']; ?>"><?php echo htmlspecialchars($game['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="level_number" class="form-label">Level Number <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="level_number" name="level_number" value="<?php echo $next_level; ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="difficulty" class="form-label">Difficulty Level</label>
                            <select class="form-select" id="difficulty" name="difficulty" required>
                                <option value="1">Easy</option>
                                <option value="2">Medium</option>
                                <option value="3">Hard</option>
                            </select>
                            <small class="form-text text-muted">Select the difficulty level for this level</small>
                        </div>
                        <div class="mb-3">
                            <label for="given_letters" class="form-label">Available Letters</label>
                            <input type="text" class="form-control" id="given_letters" name="given_letters" required>
                            <small class="form-text text-muted">Enter letters without spaces (e.g., ABCDE)</small>
                        </div>
                        <div class="mb-3">
                            <label for="words" class="form-label">Words</label>
                            <div id="wordInputs">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" name="words[]" required>
                                    <button type="button" class="btn btn-outline-danger remove-word" style="display: none;" disabled>
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addWord">Add Word</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="removeEmptyWords">Remove Empty Words</button>
                            </div>
                            <small class="form-text text-muted">Enter words. At least two unique words are required.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Level</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Level Modal -->
    <div class="modal fade" id="editLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editLevelForm" method="POST" action="">
                        <input type="hidden" name="action" value="edit_level">
                        <input type="hidden" name="level_id" id="edit_level_id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_game_id" class="form-label">Game <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_game_id" name="game_id" required>
                                    <option value="">Select a game</option>
                                    <?php foreach ($games as $game): ?>
                                        <option value="<?php echo $game['game_id']; ?>"><?php echo htmlspecialchars($game['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_level_number" class="form-label">Level Number <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_level_number" name="level_number" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_difficulty" class="form-label">Difficulty Level</label>
                            <select class="form-select" id="edit_difficulty" name="difficulty" required>
                                <option value="1">Easy</option>
                                <option value="2">Medium</option>
                                <option value="3">Hard</option>
                            </select>
                            <small class="form-text text-muted">Select the difficulty level for this level</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_given_letters" class="form-label">Available Letters</label>
                            <input type="text" class="form-control" id="edit_given_letters" name="given_letters" required>
                            <small class="form-text text-muted">Enter letters without spaces (e.g., ABCDE)</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_words" class="form-label">Words</label>
                            <div id="editWordInputs">
                                <!-- Word inputs will be populated here -->
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addEditWord">Add Word</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="removeEmptyEditWords">Remove Empty Words</button>
                            </div>
                            <small class="form-text text-muted">Enter words. At least two unique words are required.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this level? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="../js/lib/jquery.dataTables.min.js"></script>
    <script src="../js/lib/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            console.log('DataTable initialization started');
            $('#levelsTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 10
            });
            console.log('DataTable initialization completed');

            // Initialize word management
            initializeWordManagement();

            // Initialize edit functionality
            initializeEditFunctionality();

            // Initialize delete functionality
            initializeDeleteFunctionality();
        });

        function initializeWordManagement() {
            // Add word button click handler
            const addWordBtn = document.getElementById('addWord');
            if (addWordBtn) {
                addWordBtn.addEventListener('click', function() {
                    console.log('Add Word button clicked');
                    const wordInputs = document.getElementById('wordInputs');
                    if (wordInputs) {
                        const inputGroup = document.createElement('div');
                        inputGroup.className = 'input-group mb-2';
                        
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-control';
                        input.name = 'words[]';
                        input.required = true;
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.className = 'btn btn-outline-danger remove-word';
                        removeBtn.type = 'button';
                        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                        removeBtn.addEventListener('click', function(e) {
                            const inputGroup = e.target.closest('.input-group');
                            if (inputGroup) {
                                const wordInputs = document.getElementById('wordInputs');
                                const inputGroups = wordInputs.querySelectorAll('.input-group');
                                
                                // Allow removing second word when there are exactly 2 words
                                if (inputGroups.length === 2 && inputGroup === inputGroups[1]) {
                                    console.log('Removing second word when there are exactly 2 words');
                                    inputGroup.remove();
                                    return;
                                }
                                
                                // For more than 2 words, ensure at least 2 words remain
                                if (inputGroups.length > 2) {
                                    console.log('Removing word input group');
                                    inputGroup.remove();
                                }
                            }
                        });
                        
                        inputGroup.appendChild(input);
                        inputGroup.appendChild(removeBtn);
                        wordInputs.appendChild(inputGroup);
                        
                        // Initialize event listeners for the new input
                        initializeInputEventListeners();
                        
                        console.log('New word input added');
                    }
                });
            }

            // Remove empty words button
            const removeEmptyWordsBtn = document.getElementById('removeEmptyWords');
            if (removeEmptyWordsBtn) {
                removeEmptyWordsBtn.addEventListener('click', function() {
                    console.log('Remove Empty Words button clicked');
                    const inputs = document.querySelectorAll('#wordInputs .form-control');
                    let emptyCount = 0;
                    inputs.forEach(input => {
                        const inputGroup = input.closest('.input-group');
                        if (inputGroup) {
                            const wordInputs = document.getElementById('wordInputs');
                            const inputGroups = wordInputs.querySelectorAll('.input-group');
                            
                            // Skip the first word
                            if (inputGroup === inputGroups[0]) {
                                return;
                            }
                            
                            if (!input.value.trim()) {
                                emptyCount++;
                                inputGroup.remove();
                            }
                        }
                    });
                    console.log(`Removed ${emptyCount} empty word(s)`);
                });
            }
        }

        function initializeEditFunctionality() {
            // Add click event to edit buttons
            $('.edit-level-btn').click(function() {
                $('#edit_level_id').val($(this).data('level-id'));
                $('#edit_game_id').val($(this).data('game-id'));
                $('#edit_difficulty').val($(this).data('difficulty'));
                $('#edit_level_number').val($(this).data('level-number'));
                $('#edit_given_letters').val($(this).data('given-letters'));
                
                // Split words by comma and trim
                let wordsString = $(this).data('words');
                let words = wordsString ? wordsString.split(',').map(word => word.trim()) : [];
                
                // Clear existing word fields
                $('.edit-word-container').html('');
                
                // Add each word
                words.forEach(function(word, index) {
                    addWordField('edit', word);
                });
                
                // Add empty field if no words
                if (words.length === 0) {
                    addWordField('edit', '');
                }
            });

            // Edit word button click handler
            const addEditWordBtn = document.getElementById('addEditWord');
            if (addEditWordBtn) {
                addEditWordBtn.addEventListener('click', function() {
                    console.log('Add Edit Word button clicked');
                    const wordInputs = document.getElementById('editWordInputs');
                    if (wordInputs) {
                        const inputGroup = document.createElement('div');
                        inputGroup.className = 'input-group mb-2';
                        
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-control';
                        input.name = 'words[]';
                        input.required = true;
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.className = 'btn btn-outline-danger remove-word';
                        removeBtn.type = 'button';
                        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                        removeBtn.addEventListener('click', function(e) {
                            const inputGroup = e.target.closest('.input-group');
                            if (inputGroup) {
                                const wordInputs = document.getElementById('editWordInputs');
                                const inputGroups = wordInputs.querySelectorAll('.input-group');
                                
                                // Allow removing second word when there are exactly 2 words
                                if (inputGroups.length === 2 && inputGroup === inputGroups[1]) {
                                    console.log('Removing second word when there are exactly 2 words');
                                    inputGroup.remove();
                                    return;
                                }
                                
                                // For more than 2 words, ensure at least 2 words remain
                                if (inputGroups.length > 2) {
                                    console.log('Removing word input group');
                                    inputGroup.remove();
                                }
                            }
                        });
                        
                        inputGroup.appendChild(input);
                        inputGroup.appendChild(removeBtn);
                        wordInputs.appendChild(inputGroup);
                        
                        // Initialize event listeners for the new input
                        initializeInputEventListeners();
                        
                        console.log('New edit word input added');
                    }
                });
            }

            // Remove empty edit words button
            const removeEmptyEditWordsBtn = document.getElementById('removeEmptyEditWords');
            if (removeEmptyEditWordsBtn) {
                removeEmptyEditWordsBtn.addEventListener('click', function() {
                    console.log('Remove Empty Edit Words button clicked');
                    const inputs = document.querySelectorAll('#editWordInputs .form-control');
                    let emptyCount = 0;
                    inputs.forEach(input => {
                        const inputGroup = input.closest('.input-group');
                        if (inputGroup) {
                            const wordInputs = document.getElementById('editWordInputs');
                            const inputGroups = wordInputs.querySelectorAll('.input-group');
                            
                            // Skip the first word
                            if (inputGroup === inputGroups[0]) {
                                return;
                            }
                            
                            if (!input.value.trim()) {
                                emptyCount++;
                                inputGroup.remove();
                            }
                        }
                    });
                    console.log(`Removed ${emptyCount} empty word(s)`);
                });
            }
        }

        function initializeDeleteFunctionality() {
            // Add click event to delete buttons
            $('.delete-level-btn').click(function() {
                const levelId = $(this).data('level-id');
                const levelNumber = $(this).data('level-number');
                
                // Show confirmation modal
                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
                modal.show();
                
                // Set up confirmation button
                const confirmDeleteBtn = document.getElementById('confirmDelete');
                confirmDeleteBtn.addEventListener('click', function() {
                    // Submit delete form
                    const deleteForm = document.createElement('form');
                    deleteForm.method = 'POST';
                    deleteForm.action = '';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_level';
                    deleteForm.appendChild(actionInput);
                    
                    const levelIdInput = document.createElement('input');
                    levelIdInput.type = 'hidden';
                    levelIdInput.name = 'level_id';
                    levelIdInput.value = levelId;
                    deleteForm.appendChild(levelIdInput);
                    
                    document.body.appendChild(deleteForm);
                    deleteForm.submit();
                });
            });
        }

        function initializeInputEventListeners() {
            // Show/hide remove buttons based on input value
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const removeBtn = this.closest('.input-group')?.querySelector('.remove-word');
                    if (removeBtn) {
                        const inputGroup = this.closest('.input-group');
                        const wordInputs = this.closest('#wordInputs') || this.closest('#editWordInputs');
                        const inputGroups = wordInputs.querySelectorAll('.input-group');
                        
                        // First word remove button is always disabled
                        if (inputGroup === inputGroups[0]) {
                            removeBtn.style.display = 'none';
                            removeBtn.disabled = true;
                        } else {
                            removeBtn.disabled = false;
                            removeBtn.style.display = this.value.trim() ? 'block' : 'none';
                        }
                    }
                });
            });
        }

        // Handle form submission
        const form = document.querySelector('form[action=""]');
        if (form) {
            form.addEventListener('submit', function(e) {
                const inputs = document.querySelectorAll('.form-control');
                let validWords = 0;
                const wordSet = new Set();

                inputs.forEach(input => {
                    const value = input.value.trim();
                    if (value) {
                        if (!wordSet.has(value)) {
                            wordSet.add(value);
                            validWords++;
                        }
                    }
                });

                if (validWords < 2) {
                    e.preventDefault();
                    alert('Please enter at least two unique words.');
                }
            });
        }

        // Handle edit form submission
        const editForm = document.getElementById('editLevelForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                const inputs = document.querySelectorAll('#editWordInputs .form-control');
                let validWords = 0;
                const wordSet = new Set();

                inputs.forEach(input => {
                    const value = input.value.trim();
                    if (value) {
                        if (!wordSet.has(value)) {
                            wordSet.add(value);
                            validWords++;
                        }
                    }
                });

                if (validWords < 2) {
                    e.preventDefault();
                    alert('Please enter at least two unique words.');
                }
            });
        }
    </script>

    <style>
        .word-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .word-list .badge {
            padding: 0.35em 0.65em;
            font-size: 0.875rem;
            line-height: 1;
            border-radius: 0.25rem;
        }
        .form-control:invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .form-control:invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
    </style>
</body>
</html>
