<?php
// Check if the current page is chatbot.php
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'chatbot.php') {
    // Skip loading the floating chatbot on the dedicated chatbot page
    return;
}

// Accessible only to logged-in users
if (!isset($_SESSION['user_id'])) {
    return;
}

// Get user name
$stmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();
$display_name = htmlspecialchars($profile && !empty($profile['full_name']) ? $profile['full_name'] : $_SESSION['username']);
?>

<!-- Floating Chatbot -->
<div class="floating-chatbot">
    <!-- Chat Button -->
    <div class="chat-button" id="chat-button">
        <i class="fas fa-comment"></i>
        <div class="chat-badge" id="chat-badge">1</div>
    </div>

    <!-- Welcome Message Bubble -->
    <div class="welcome-bubble" id="welcome-bubble">
        <div class="close-welcome" id="close-welcome">&times;</div>
        <strong>Hello <?php echo $display_name; ?>!</strong> 
        <p>Need help with your English learning journey? I'm here to assist you!</p>
    </div>

    <!-- Chat Window -->
    <div class="chat-window" id="chat-window">
        <div class="chatbot-header">
            <h5><i class="fas fa-robot me-2"></i>AI Assistant</h5>
            <div class="chat-controls">
                <button type="button" id="chatbot-clear-btn" class="btn btn-sm btn-link text-white">
                    <i class="fas fa-trash"></i>
                </button>
                <button type="button" id="chat-close-btn" class="btn btn-sm btn-link text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="chatbot-body">
            <div class="chatbot-messages" id="chatbot-messages">
                <!-- Messages will be loaded here by JS -->
            </div>
            <div id="chatbot-scroll-bottom" class="chatbot-scroll-bottom">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="chatbot-input">
            <form id="chatbot-form" autocomplete="off">
                <div class="input-group">
                    <textarea id="chatbot-input" name="message" placeholder="Type your message..." required autofocus autocomplete="off" rows="1" class="form-control" style="resize: none;"></textarea>
                    <button type="submit" id="chatbot-send-btn" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="chatbot-input-meta">
                    <span id="chatbot-char-count">0/1000</span>
                    <span class="d-none d-md-inline chatbot-input-instructions">Press <b>Enter</b> to send, <b>Shift+Enter</b> for new line</span>
                </div>
            </form>
        </div>
    </div>
</div> 