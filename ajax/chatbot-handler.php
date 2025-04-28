<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$user_id = $_SESSION['user_id'];

require_once '../includes/db.php';

// Simple .env loader
function load_env($path) {
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        $env[$name] = $value;
    }
    return $env;
}

$env = load_env(__DIR__ . '/../.env');
$OPENROUTER_API_KEY = $env['OPENROUTER_API_KEY'] ?? null;
// if (!$OPENROUTER_API_KEY && file_exists(__DIR__ . '/../config.php')) {
//     $config = include __DIR__ . '/../config.php';
//     $OPENROUTER_API_KEY = $config['openrouter_api_key'] ?? null;
// }
if (!$OPENROUTER_API_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured']);
    exit;
}

// Utility: fetch chat history
function get_chat_history($conn, $user_id) {
    $stmt = $conn->prepare("SELECT sender, message, created_at FROM chat_messages WHERE user_id = ? ORDER BY message_id ASC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Utility: insert message
function insert_message($conn, $user_id, $sender, $message) {
    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $sender, $message]);
    // Fetch created_at
    $id = $conn->lastInsertId();
    $stmt2 = $conn->prepare("SELECT created_at FROM chat_messages WHERE message_id = ?");
    $stmt2->execute([$id]);
    return $stmt2->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return chat history
    $history = get_chat_history($conn, $user_id);
    echo json_encode(['history' => $history]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['message'] ?? '';
    $input = trim($input);
    if ($input === '') {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }
    // Save user message
    $user_created_at = insert_message($conn, $user_id, 0, $input);

    // Prepare API call
    $api_url = 'https://openrouter.ai/api/v1/chat/completions';
    // System prompt to restrict to eLearning
    $system_prompt = [
        [
            'role' => 'system',
            'content' => 'You are ELearning AI, an expert assistant for the eLearning platform. ONLY answer questions that are directly about education, online learning, courses or study tips. If the user asks about anything else (such as cars, programming, general knowledge, or unrelated topics), politely respond: "I can only assist with questions related to eLearning, education, or this platform." Never answer unrelated questions.'
        ]
    ];
    // Get last 10 messages as context
    $stmt = $conn->prepare("SELECT sender, message FROM chat_messages WHERE user_id = ? ORDER BY message_id DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $context_msgs = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $messages = [];
    foreach ($context_msgs as $msg) {
        $messages[] = [
            'role' => $msg['sender'] == 0 ? 'user' : 'assistant',
            'content' => $msg['message']
        ];
    }
    // Always prepend system prompt
    $messages = array_merge($system_prompt, $messages);
    $payload = [
        'model' => 'microsoft/mai-ds-r1:free',
        'messages' => $messages
    ];
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $OPENROUTER_API_KEY,
        'Content-Type: application/json',
        'HTTP-Referer: https://yourdomain.com', // Optional: set your domain
        'X-Title: eLearning Chatbot'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false || $httpcode !== 200) {
        $error = curl_error($ch);
        curl_close($ch);
        // Save error as AI reply
        $ai_reply = 'Sorry, the AI is currently unavailable.';
        $ai_created_at = insert_message($conn, $user_id, 1, $ai_reply);
        echo json_encode(['reply' => $ai_reply, 'created_at' => $ai_created_at, 'error' => $error]);
        exit;
    }
    curl_close($ch);
    $data = json_decode($response, true);
    $ai_reply = $data['choices'][0]['message']['content'] ?? 'Sorry, I could not understand the response.';
    // Save AI reply
    $ai_created_at = insert_message($conn, $user_id, 1, $ai_reply);
    echo json_encode(['reply' => $ai_reply, 'created_at' => $ai_created_at]);
    exit;
}

// Fallback
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);