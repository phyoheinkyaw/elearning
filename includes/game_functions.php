<?php
/**
 * Game Functions
 * Helper functions for working with game data and help content
 */

/**
 * Get game information from database
 * 
 * @param PDO $conn Database connection
 * @param string $game_folder Folder name of the game
 * @return array|false Game information or false if not found
 */
function getGameInfo($conn, $game_folder) {
    try {
        $stmt = $conn->prepare("SELECT * FROM games_info WHERE game_folder = ? AND is_active = 1");
        $stmt->execute([$game_folder]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting game info: " . $e->getMessage());
        return false;
    }
}

/**
 * Get help content for a game
 * 
 * @param PDO $conn Database connection
 * @param string $game_folder Folder name of the game
 * @return array Help sections with title and content
 */
function getGameHelpContent($conn, $game_folder) {
    try {
        $stmt = $conn->prepare("
            SELECT h.section_title, h.section_content 
            FROM games_help h
            JOIN games_info g ON h.game_id = g.game_id
            WHERE g.game_folder = ?
            ORDER BY h.display_order ASC
        ");
        $stmt->execute([$game_folder]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting game help content: " . $e->getMessage());
        return [];
    }
}

/**
 * Render game help modal HTML
 * 
 * @param PDO $conn Database connection
 * @param string $game_folder Folder name of the game
 * @param string $game_title Game title to display in modal header
 * @param array $fallback_sections Fallback help sections if database content is unavailable
 * @return string HTML for the help modal
 */
function renderGameHelpModal($conn, $game_folder, $game_title, $fallback_sections = []) {
    $help_sections = getGameHelpContent($conn, $game_folder);
    
    ob_start();
    ?>
    <!-- Game Help Modal -->
    <div class="modal fade" id="gameHelpModal" tabindex="-1" aria-labelledby="gameHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gameHelpModalLabel"><?php echo htmlspecialchars($game_title); ?> - Game Help</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    if (!empty($help_sections)) {
                        foreach ($help_sections as $section) {
                            echo '<h5>' . htmlspecialchars($section['section_title']) . '</h5>';
                            echo $section['section_content']; // Content already contains HTML
                        }
                    } else if (!empty($fallback_sections)) {
                        // Use fallback content if no help sections found
                        foreach ($fallback_sections as $section) {
                            echo '<h5>' . htmlspecialchars($section['title']) . '</h5>';
                            echo $section['content'];
                        }
                    } else {
                        // No content available message
                        ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No help content available for this game.
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} 