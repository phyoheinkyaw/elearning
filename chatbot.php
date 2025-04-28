<?php
// chatbot.php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Get user name
$stmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbot</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/custom.css">
    <link rel="stylesheet" href="css/chatbot.css">
</head>
<body data-username="<?php echo htmlspecialchars($username); ?>">
    <?php include 'includes/nav.php'; ?>
    <section class="py-5 bg-light">
      <div class="container">
        <div class="chatbot-wrapper">
            <div class="chatbot-header">
                <h2>AI Chatbot</h2>
                <span class="chatbot-user">Hi, <?php echo htmlspecialchars($profile && !empty($profile['full_name']) ? $profile['full_name'] : $_SESSION['username']); ?>!</span>
                <button type="button" id="chatbot-clear-btn" class="chatbot-clear-btn"><i class="fas fa-trash"></i> Clear All</button>
            </div>
            <div class="chatbot-messages" id="chatbot-messages">
                <!-- Messages will be loaded here by JS -->
            </div>
            <form class="chatbot-input-area" id="chatbot-form" autocomplete="off">
                <textarea id="chatbot-input" name="message" placeholder="Type your message..." required autofocus autocomplete="off" rows="1" style="resize: none;"></textarea>
                <button type="submit" id="chatbot-send-btn">Send</button>
            </form>
            <div class="chatbot-input-meta">
                <span id="chatbot-char-count">0/1000</span>
                <span class="chatbot-input-instructions">Press <b>Enter</b> to send, <b>Shift+Enter</b> for new line</span>
            </div>
            <div id="chatbot-scroll-bottom" class="chatbot-scroll-bottom" style="display:none;">
                <i class="fas fa-arrow-down"></i>
            </div>
        </div>
      </div>
    </section>
    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="js/chatbot.js"></script>
</body>
</html>
