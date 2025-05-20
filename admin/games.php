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
        header('Location: games.php');
        exit();
    }

    try {
        $conn->beginTransaction();
        
        switch ($_POST['action']) {
            case 'add_game':
                // Get all required fields
                $required_fields = ['game_folder', 'title', 'description', 'icon', 'background', 'difficulty', 'duration'];
                $missing_fields = array_filter($required_fields, function($field) {
                    return !isset($_POST[$field]) || $_POST[$field] === '';
                });
                
                if (!empty($missing_fields)) {
                    throw new Exception("Missing required fields: " . implode(", ", $missing_fields));
                }

                // Get and validate all fields
                $game_folder = trim($_POST['game_folder']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $background = trim($_POST['background']);
                $difficulty = trim($_POST['difficulty']);
                $duration = trim($_POST['duration']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                // Check if game folder already exists
                $stmt = $conn->prepare("SELECT game_id FROM games_info WHERE game_folder = ?");
                $stmt->execute([$game_folder]);
                if ($stmt->fetch()) {
                    throw new Exception("Game folder already exists. Please choose a different name.");
                }

                // Insert game info
                $stmt = $conn->prepare("
                    INSERT INTO games_info (game_folder, title, description, icon, background, difficulty, duration, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$game_folder, $title, $description, $icon, $background, $difficulty, $duration, $is_active]);

                $_SESSION['success_message'] = "Game added successfully.";
                break;

            case 'edit_game':
                // Get all required fields
                $required_fields = ['game_id', 'game_folder', 'title', 'description', 'icon', 'background', 'difficulty', 'duration'];
                $missing_fields = array_filter($required_fields, function($field) {
                    return !isset($_POST[$field]) || $_POST[$field] === '';
                });
                
                if (!empty($missing_fields)) {
                    throw new Exception("Missing required fields: " . implode(", ", $missing_fields));
                }

                // Get and validate all fields
                $game_id = (int)$_POST['game_id'];
                $game_folder = trim($_POST['game_folder']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $background = trim($_POST['background']);
                $difficulty = trim($_POST['difficulty']);
                $duration = trim($_POST['duration']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                // Check if game folder already exists for other games
                $stmt = $conn->prepare("SELECT game_id FROM games_info WHERE game_folder = ? AND game_id != ?");
                $stmt->execute([$game_folder, $game_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Game folder already exists. Please choose a different name.");
                }

                // Update game info
                $stmt = $conn->prepare("
                    UPDATE games_info 
                    SET game_folder = ?, title = ?, description = ?, icon = ?, background = ?, 
                        difficulty = ?, duration = ?, is_active = ?
                    WHERE game_id = ?
                ");
                $stmt->execute([$game_folder, $title, $description, $icon, $background, $difficulty, $duration, $is_active, $game_id]);

                $_SESSION['success_message'] = "Game updated successfully.";
                break;

            case 'delete_game':
                if (!isset($_POST['game_id'])) {
                    throw new Exception("Missing game ID");
                }

                $game_id = (int)$_POST['game_id'];
                
                // Get the game information
                $stmt = $conn->prepare("SELECT game_folder FROM games_info WHERE game_id = ?");
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$game) {
                    throw new Exception("Game not found");
                }
                
                // With ON DELETE CASCADE properly configured in the database schema, 
                // we can simply delete the game and all related records will be deleted automatically
                $stmt = $conn->prepare("DELETE FROM games_info WHERE game_id = ?");
                $stmt->execute([$game_id]);
                
                $_SESSION['success_message'] = "Game deleted successfully.";
                break;
        }
        
        $conn->commit();
        header('Location: games.php');
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        
        // Log the error for debugging
        error_log("Error in games.php: " . $e->getMessage());
        
        // Set a user-friendly error message
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: games.php');
        exit();
    }
}

// Get all games
try {
    $stmt = $conn->query("SELECT * FROM games_info ORDER BY title");
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching games: " . $e->getMessage();
    $games = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games Management - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="/js/lib/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="/js/lib/jquery-3.7.1.min.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-2">Games Management</h1>
                        <p class="text-muted mb-0">Manage educational games information and content</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                            <i class="fas fa-plus me-2"></i>Add New Game
                        </button>
                        <a href="game-help.php" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-question-circle me-2"></i>Manage Help Content
                        </a>
                    </div>
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
                            <table class="table table-hover" id="gamesTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Folder</th>
                                        <th>Icon</th>
                                        <th>Description</th>
                                        <th>Difficulty</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($games as $game): ?>
                                        <tr>
                                            <td><?php echo $game['game_id']; ?></td>
                                            <td><?php echo htmlspecialchars($game['title']); ?></td>
                                            <td><?php echo htmlspecialchars($game['game_folder']); ?></td>
                                            <td><i class="<?php echo htmlspecialchars($game['icon']); ?>"></i> <?php echo htmlspecialchars($game['icon']); ?></td>
                                            <td><?php echo htmlspecialchars(mb_substr($game['description'], 0, 50)) . (mb_strlen($game['description']) > 50 ? '...' : ''); ?></td>
                                            <td><?php echo htmlspecialchars($game['difficulty']); ?></td>
                                            <td><?php echo htmlspecialchars($game['duration']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $game['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $game['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary edit-game-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editGameModal"
                                                        data-game-id="<?php echo $game['game_id']; ?>"
                                                        data-game-folder="<?php echo htmlspecialchars($game['game_folder']); ?>"
                                                        data-title="<?php echo htmlspecialchars($game['title']); ?>"
                                                        data-description="<?php echo htmlspecialchars($game['description']); ?>"
                                                        data-icon="<?php echo htmlspecialchars($game['icon']); ?>"
                                                        data-background="<?php echo htmlspecialchars($game['background']); ?>"
                                                        data-difficulty="<?php echo htmlspecialchars($game['difficulty']); ?>"
                                                        data-duration="<?php echo htmlspecialchars($game['duration']); ?>"
                                                        data-is-active="<?php echo $game['is_active']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="game-help.php?game_id=<?php echo $game['game_id']; ?>" class="btn btn-outline-info">
                                                        <i class="fas fa-question-circle"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger delete-game-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteGameModal"
                                                        data-game-id="<?php echo $game['game_id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($game['title']); ?>">
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

    <!-- Add Game Modal -->
    <div class="modal fade" id="addGameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="games.php">
                    <input type="hidden" name="action" value="add_game">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="game_folder" class="form-label">Game Folder Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="game_folder" name="game_folder" required 
                                       placeholder="e.g., wordscapes (no spaces, lowercase)">
                                <div class="form-text">Folder name in the games directory</div>
                            </div>
                            <div class="col-md-6">
                                <label for="title" class="form-label">Game Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required
                                       placeholder="e.g., Wordscapes">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required
                                      placeholder="Brief description of the game"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="icon" class="form-label">Icon Class <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="icon" name="icon" required
                                       placeholder="e.g., fas fa-font">
                                <div class="form-text">
                                    Font Awesome icon class. <a href="https://fontawesome.com/icons" target="_blank">Browse icons</a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="background" class="form-label">Background <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="background" name="background" required
                                       placeholder="e.g., linear-gradient(135deg, #3f51b5, #7986cb)">
                                <div class="form-text">CSS background value (color or gradient)</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="difficulty" class="form-label">Difficulty <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="difficulty" name="difficulty" required
                                       placeholder="e.g., Medium">
                            </div>
                            <div class="col-md-6">
                                <label for="duration" class="form-label">Duration <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="duration" name="duration" required
                                       placeholder="e.g., 5-10 min">
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Game Modal -->
    <div class="modal fade" id="editGameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="games.php">
                    <input type="hidden" name="action" value="edit_game">
                    <input type="hidden" name="game_id" id="edit_game_id">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_game_folder" class="form-label">Game Folder Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_game_folder" name="game_folder" required>
                                <div class="form-text">Folder name in the games directory</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_title" class="form-label">Game Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_title" name="title" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_icon" class="form-label">Icon Class <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_icon" name="icon" required>
                                <div class="form-text">
                                    Font Awesome icon class. <a href="https://fontawesome.com/icons" target="_blank">Browse icons</a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_background" class="form-label">Background <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_background" name="background" required>
                                <div class="form-text">CSS background value (color or gradient)</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_difficulty" class="form-label">Difficulty <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_difficulty" name="difficulty" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_duration" class="form-label">Duration <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_duration" name="duration" required>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Game Modal -->
    <div class="modal fade" id="deleteGameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the game "<span id="delete_game_title"></span>"?</p>
                    <p class="text-danger">This will also delete all associated help content. This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="games.php">
                        <input type="hidden" name="action" value="delete_game">
                        <input type="hidden" name="game_id" id="delete_game_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="/js/lib/jquery.dataTables.min.js"></script>
    <script src="/js/lib/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#gamesTable').DataTable({
                "order": [[1, "asc"]], // Sort by title
                "pageLength": 25,
                "language": {
                    "search": "Filter:",
                    "emptyTable": "No games have been added yet."
                }
            });
            
            // Edit game modal
            $('.edit-game-btn').click(function() {
                $('#edit_game_id').val($(this).data('game-id'));
                $('#edit_game_folder').val($(this).data('game-folder'));
                $('#edit_title').val($(this).data('title'));
                $('#edit_description').val($(this).data('description'));
                $('#edit_icon').val($(this).data('icon'));
                $('#edit_background').val($(this).data('background'));
                $('#edit_difficulty').val($(this).data('difficulty'));
                $('#edit_duration').val($(this).data('duration'));
                $('#edit_is_active').prop('checked', $(this).data('is-active') == 1);
            });
            
            // Delete game modal
            $('.delete-game-btn').click(function() {
                $('#delete_game_id').val($(this).data('game-id'));
                $('#delete_game_title').text($(this).data('title'));
            });
        });
    </script>
</body>
</html> 