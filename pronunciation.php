<?php
session_start();
require_once 'includes/db.php';
// Optionally get user level from session
$user_level = null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT proficiency_level FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
    $user_level = $profile['proficiency_level'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="user-id" content="<?php echo htmlspecialchars($user_id); ?>">
    <title>Pronunciation Practice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/custom.css">
    <link rel="stylesheet" href="css/floating-chatbot.css">
    <link rel="stylesheet" href="css/search-autocomplete.css">
<style>
        .practice-target { font-size: 1.5rem; font-weight: 600; margin: 1rem 0; background: var(--light); color: var(--primary); border: 2px solid var(--primary); border-radius: var(--radius-md); }
        .transcript-box { font-size: 1.2rem; min-height: 2.5rem; background: var(--gray-100); border-radius: var(--radius-md); padding: 0.5rem 1rem; border: 1.5px solid var(--accent); color: var(--dark); }
        .feedback-box { min-height: 2rem; background: var(--gray-100); border-left: 5px solid var(--primary); color: var(--dark); }
        .btn-mic { font-size: 1.2rem; background: var(--primary); color: var(--white); border: none; }
        .btn-mic:hover, .btn-mic:focus { background: var(--secondary); color: var(--white); }
        .practice-history { font-size: 0.95rem; }
        .card { border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); }
        .card-body { background: var(--white); }
        h2.text-primary { color: var(--primary) !important; }
        .form-label { color: var(--primary); }
    </style>
</head>
<body data-user-id="<?php echo htmlspecialchars($user_id); ?>">
<?php include 'includes/nav.php'; ?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="mb-3 text-primary"><i class="fas fa-microphone-alt me-2"></i>Pronunciation Practice</h2>
                    <form id="practice-options" class="row g-3 align-items-end mb-3">
                        <div class="col-6 col-md-4">
                            <label for="practiceType" class="form-label">Practice Type</label>
                            <select class="form-select" id="practiceType" required>
                                <option value="word">Word</option>
                                <option value="sentence">Sentence</option>
                                <option value="paragraph">Paragraph</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="userLevel" class="form-label">Level</label>
                            <select class="form-select" id="userLevel" required>
                                <option value="A1">A1</option>
                                <option value="A2">A2</option>
                                <option value="B1">B1</option>
                                <option value="B2">B2</option>
                                <option value="C1">C1</option>
                                <option value="C2">C2</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <button type="button" class="btn btn-primary w-100" id="generateBtn">
                                <i class="fas fa-bolt me-1"></i> Generate Practice
                            </button>
                        </div>
                    </form>
                    <div id="practice-section" class="mb-3" style="display:none;">
                        <div class="practice-target card card-body mb-2 text-center" id="practiceTarget"></div>
                        <div class="d-flex justify-content-center mb-2">
                            <button class="btn btn-outline-secondary me-2" id="regenerateBtn"><i class="fas fa-sync-alt"></i> Regenerate</button>
                        </div>
                        <div class="d-flex justify-content-center align-items-center mb-3">
                            <button class="btn btn-success btn-mic me-2" id="startSpeechBtn"><i class="fas fa-microphone"></i> Start Speaking</button>
                            <button class="btn btn-outline-danger btn-mic" id="retryBtn" style="display:none;"><i class="fas fa-redo"></i> Retry</button>
                        </div>
                        <div class="transcript-box mb-2" id="transcriptBox">Transcript will appear here...</div>
                        <div class="d-flex justify-content-end mb-2">
                            <button class="btn btn-primary" id="feedbackBtn" disabled><i class="fas fa-comment-dots"></i> Get Feedback</button>
                        </div>
                        <div class="feedback-box alert alert-info" id="feedbackBox" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Separate container for history cards -->
<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="practice-history">
                <h4 class="mb-3 text-secondary"><i class="fas fa-history me-1"></i>Session History</h4>
                <div id="historyList"></div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/pronunciation.js"></script>
<script>
// Set default level if available from PHP
const userLevelFromSession = <?php echo $user_level ? json_encode($user_level) : 'null'; ?>;
window.addEventListener('DOMContentLoaded', function() {
    if (userLevelFromSession) {
        document.getElementById('userLevel').value = userLevelFromSession;
    }
});
</script>
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="js/lib/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>
</html>
