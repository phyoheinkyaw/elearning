<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get game_id from query parameter
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log all POST data for debugging
    error_log("POST data: " . print_r($_POST, true));
    
    if (!isset($_POST['action'])) {
        $_SESSION['error_message'] = "Invalid form submission";
        header('Location: game-help.php' . ($game_id ? "?game_id=$game_id" : ''));
        exit();
    }

    try {
        $conn->beginTransaction();
        
        switch ($_POST['action']) {
            case 'add_help':
                // Get all required fields
                $required_fields = ['game_id', 'section_title', 'section_content', 'display_order'];
                $missing_fields = array_filter($required_fields, function($field) {
                    return !isset($_POST[$field]) || $_POST[$field] === '';
                });
                
                if (!empty($missing_fields)) {
                    throw new Exception("Missing required fields: " . implode(", ", $missing_fields));
                }

                // Get and validate all fields
                $game_id = (int)$_POST['game_id'];
                $section_title = trim($_POST['section_title']);
                $section_content = trim($_POST['section_content']);
                $display_order = (int)$_POST['display_order'];

                // Check if game exists
                $stmt = $conn->prepare("SELECT game_id FROM games_info WHERE game_id = ?");
                $stmt->execute([$game_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Game not found.");
                }

                // Insert help section
                $stmt = $conn->prepare("
                    INSERT INTO games_help (game_id, section_title, section_content, display_order) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$game_id, $section_title, $section_content, $display_order]);

                $_SESSION['success_message'] = "Help section added successfully.";
                break;

            case 'edit_help':
                // Get all required fields
                $required_fields = ['help_id', 'game_id', 'section_title', 'section_content', 'display_order'];
                $missing_fields = array_filter($required_fields, function($field) {
                    return !isset($_POST[$field]) || $_POST[$field] === '';
                });
                
                if (!empty($missing_fields)) {
                    throw new Exception("Missing required fields: " . implode(", ", $missing_fields));
                }

                // Get and validate all fields
                $help_id = (int)$_POST['help_id'];
                $game_id = (int)$_POST['game_id'];
                $section_title = trim($_POST['section_title']);
                $section_content = trim($_POST['section_content']);
                $display_order = (int)$_POST['display_order'];

                // Update help section
                $stmt = $conn->prepare("
                    UPDATE games_help 
                    SET section_title = ?, section_content = ?, display_order = ?
                    WHERE help_id = ? AND game_id = ?
                ");
                $stmt->execute([$section_title, $section_content, $display_order, $help_id, $game_id]);

                $_SESSION['success_message'] = "Help section updated successfully.";
                break;

            case 'delete_help':
                if (!isset($_POST['help_id']) || !isset($_POST['game_id'])) {
                    throw new Exception("Missing help ID or game ID");
                }

                $help_id = (int)$_POST['help_id'];
                $game_id = (int)$_POST['game_id'];
                
                // Delete help section
                $stmt = $conn->prepare("DELETE FROM games_help WHERE help_id = ? AND game_id = ?");
                $stmt->execute([$help_id, $game_id]);
                
                $_SESSION['success_message'] = "Help section deleted successfully.";
                break;
        }
        
        $conn->commit();
        header('Location: game-help.php' . ($game_id ? "?game_id=$game_id" : ''));
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        
        // Log the error for debugging
        error_log("Error in game-help.php: " . $e->getMessage());
        
        // Set a user-friendly error message
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: game-help.php' . ($game_id ? "?game_id=$game_id" : ''));
        exit();
    }
}

// Get all games for dropdown
try {
    $stmt = $conn->query("SELECT * FROM games_info ORDER BY title");
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching games: " . $e->getMessage();
    $games = [];
}

// If no game_id specified and we have games, use the first one
if (!$game_id && !empty($games)) {
    $game_id = $games[0]['game_id'];
}

// Get current game info if game_id is set
$current_game = null;
if ($game_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM games_info WHERE game_id = ?");
        $stmt->execute([$game_id]);
        $current_game = $stmt->fetch();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error fetching game info: " . $e->getMessage();
    }
}

// Get help sections for current game
$help_sections = [];
if ($game_id) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM games_help 
            WHERE game_id = ? 
            ORDER BY display_order ASC, section_title ASC
        ");
        $stmt->execute([$game_id]);
        $help_sections = $stmt->fetchAll();
        
        // Get next display order
        $next_order = 1;
        if (!empty($help_sections)) {
            $max_order = max(array_column($help_sections, 'display_order'));
            $next_order = $max_order + 1;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error fetching help sections: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Help Content - ELearning Admin</title>
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
    <!-- Summernote CSS/JS for rich text editing -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-2">Game Help Content</h1>
                        <p class="text-muted mb-0">
                            Manage help content for 
                            <?php if ($current_game): ?>
                                <strong><?php echo htmlspecialchars($current_game['title']); ?></strong>
                            <?php else: ?>
                                games
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($current_game): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHelpModal">
                                <i class="fas fa-plus me-2"></i>Add Help Section
                            </button>
                            <a href="games.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-arrow-left me-2"></i>Back to Games
                            </a>
                        <?php else: ?>
                            <a href="games.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Games
                            </a>
                        <?php endif; ?>
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

                <!-- Game Selector -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <label for="gameSelector" class="form-label mb-0">Select Game:</label>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" id="gameSelector" onchange="changeGame(this.value)">
                                    <option value="">-- Select a Game --</option>
                                    <?php foreach ($games as $game): ?>
                                        <option value="<?php echo $game['game_id']; ?>" <?php echo $game_id == $game['game_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($game['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($current_game): ?>
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($help_sections)): ?>
                                <div class="alert alert-info">
                                    No help sections found for this game. Add a new section to get started.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="helpSectionsTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 80px;">Order</th>
                                                <th style="width: 30%;">Section Title</th>
                                                <th>Content</th>
                                                <th style="width: 150px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($help_sections as $section): ?>
                                                <tr>
                                                    <td><?php echo $section['display_order']; ?></td>
                                                    <td><?php echo htmlspecialchars($section['section_title']); ?></td>
                                                    <td>
                                                        <div class="content-preview">
                                                            <?php echo mb_substr(strip_tags($section['section_content']), 0, 100) . (mb_strlen(strip_tags($section['section_content'])) > 100 ? '...' : ''); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-primary edit-help-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editHelpModal"
                                                                data-help-id="<?php echo $section['help_id']; ?>"
                                                                data-section-title="<?php echo htmlspecialchars($section['section_title']); ?>"
                                                                data-section-content="<?php echo htmlspecialchars($section['section_content']); ?>"
                                                                data-display-order="<?php echo $section['display_order']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger delete-help-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteHelpModal"
                                                                data-help-id="<?php echo $section['help_id']; ?>"
                                                                data-section-title="<?php echo htmlspecialchars($section['section_title']); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Add Help Modal -->
                    <div class="modal fade" id="addHelpModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add Help Section for <?php echo htmlspecialchars($current_game['title']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="post" action="game-help.php">
                                    <input type="hidden" name="action" value="add_help">
                                    <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-8">
                                                <label for="section_title" class="form-label">Section Title <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="section_title" name="section_title" required 
                                                    placeholder="e.g., How to Play">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="display_order" class="form-label">Display Order <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="display_order" name="display_order" 
                                                    required min="1" value="<?php echo $next_order; ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="section_content" class="form-label">Content <span class="text-danger">*</span></label>
                                            <textarea class="form-control rich-editor" id="section_content" name="section_content" rows="6" required></textarea>
                                            <div class="form-text">
                                                Use the editor to format your content. HTML is allowed.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add Section</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Help Modal -->
                    <div class="modal fade" id="editHelpModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Help Section</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="post" action="game-help.php">
                                    <input type="hidden" name="action" value="edit_help">
                                    <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                                    <input type="hidden" name="help_id" id="edit_help_id">
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-8">
                                                <label for="edit_section_title" class="form-label">Section Title <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="edit_section_title" name="section_title" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="edit_display_order" class="form-label">Display Order <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="edit_display_order" name="display_order" required min="1">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="edit_section_content" class="form-label">Content <span class="text-danger">*</span></label>
                                            <textarea class="form-control rich-editor" id="edit_section_content" name="section_content" rows="6" required></textarea>
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

                    <!-- Delete Help Modal -->
                    <div class="modal fade" id="deleteHelpModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete Help Section</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete the help section "<span id="delete_section_title"></span>"?</p>
                                    <p class="text-danger">This action cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <form method="post" action="game-help.php">
                                        <input type="hidden" name="action" value="delete_help">
                                        <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                                        <input type="hidden" name="help_id" id="delete_help_id">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        No games found. <a href="games.php" class="alert-link">Add a game</a> first to manage its help content.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="../js/lib/jquery.dataTables.min.js"></script>
    <script src="../js/lib/dataTables.bootstrap5.min.js"></script>
    <!-- Popper.js (required for Summernote) -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <!-- Bootstrap 4 (required for Summernote) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        // Change game selection
        function changeGame(gameId) {
            if (gameId) {
                window.location.href = 'game-help.php?game_id=' + gameId;
            }
        }
        
        $(document).ready(function() {
            // Initialize DataTable
            $('#helpSectionsTable').DataTable({
                "order": [[0, "asc"]], // Sort by display order
                "pageLength": 25,
                "language": {
                    "search": "Filter:",
                    "emptyTable": "No help sections have been added yet."
                }
            });
            
            // Initialize Summernote rich text editor
            $('.rich-editor').summernote({
                height: 200,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link']],
                    ['view', ['codeview']]
                ]
            });
            
            // Edit help modal
            $('.edit-help-btn').click(function() {
                $('#edit_help_id').val($(this).data('help-id'));
                $('#edit_section_title').val($(this).data('section-title'));
                $('#edit_display_order').val($(this).data('display-order'));
                
                // Set content in Summernote editor
                $('#edit_section_content').summernote('code', $(this).data('section-content'));
            });
            
            // Delete help modal
            $('.delete-help-btn').click(function() {
                $('#delete_help_id').val($(this).data('help-id'));
                $('#delete_section_title').text($(this).data('section-title'));
            });
        });
    </script>
</body>
</html> 