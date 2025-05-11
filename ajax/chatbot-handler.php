<?php
// Start output buffering only for production
ob_start();

// For debugging, let's show errors for now
// error_reporting(E_ALL);
ini_set('display_errors', 0);

// Custom debug function
function debug_point($message) {
    // Debug disabled
    // error_log("DEBUG: $message");
    // Uncomment to log to a file in the server root
    // file_put_contents(__DIR__ . '/../debug.log', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Custom error handler to log errors but not display them
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    // Log error to server log file
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    // Don't display the error
    return true;
}
set_error_handler("custom_error_handler");

// Register shutdown function to catch fatal errors
function fatal_handler() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log detailed error information
        error_log("PHP Fatal Error [{$error['type']}]: {$error['message']} in {$error['file']} on line {$error['line']}");
        
        // Return a clean JSON error response
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal server error',
            'debug_info' => [
                'file' => basename($error['file']),
                'line' => $error['line']
            ]
        ]);
    }
}
register_shutdown_function("fatal_handler");

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
session_start();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
header('Content-Type: application/json');

// If this is a preflight OPTIONS request, return only the headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authentication check
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
    $content = file_get_contents($path);
    if ($content === false) return [];
    
    $env = [];
    $lines = preg_split('/\r\n|\r|\n/', $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        // Handle "KEY=value" format (no quotes)
        if (strpos($line, '=') !== false) {
        list($name, $value) = array_map('trim', explode('=', $line, 2));
            // Remove quotes if present
            $value = trim($value, '"\'');
        $env[$name] = $value;
        }
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
    // debug_point('API key not found in .env, checking config.php');
    if (file_exists(__DIR__ . '/../config.php')) {
        $config = include __DIR__ . '/../config.php';
        $OPENROUTER_API_KEY = $config['openrouter_api_key'] ?? null;
        // debug_point('API key from config.php: ' . ($OPENROUTER_API_KEY ? 'Found (length: ' . strlen($OPENROUTER_API_KEY) . ')' : 'Not found'));
    }
}

if (!$OPENROUTER_API_KEY) {
    http_response_code(500);
    echo json_encode([
        'error' => 'API key not configured', 
        'message' => 'Please add your OpenRouter API key to config.php or .env file. You can get a free API key from https://openrouter.ai/keys'
    ]);
    error_log("ERROR: OpenRouter API key is missing. Please add it to config.php or .env file.");
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

// Utility: fetch user profile info
function get_user_profile($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT u.username, p.full_name, p.proficiency_level 
        FROM users u
        LEFT JOIN user_profiles p ON u.user_id = p.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Utility: fetch user learning info
function get_user_learning_info($conn, $user_id) {
    // Get enrolled courses
    $courses_stmt = $conn->prepare("
        SELECT c.course_id, c.title, c.level, c.description
        FROM courses c 
        INNER JOIN course_enrollments e ON c.course_id = e.course_id
        WHERE e.user_id = ?
        LIMIT 3
    ");
    $courses_stmt->execute([$user_id]);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent progress
    $progress_stmt = $conn->prepare("
        SELECT cm.title AS material_title, c.title AS course_title, up.progress, up.last_accessed
        FROM user_progress up
        JOIN course_materials cm ON up.material_id = cm.material_id
        JOIN courses c ON cm.course_id = c.course_id
        WHERE up.user_id = ?
        ORDER BY up.last_accessed DESC
        LIMIT 3
    ");
    $progress_stmt->execute([$user_id]);
    $progress = $progress_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get test results
    $test_stmt = $conn->prepare("
        SELECT assigned_level, score, test_date
        FROM level_test_results
        WHERE user_id = ?
        ORDER BY test_date DESC
        LIMIT 1
    ");
    $test_stmt->execute([$user_id]);
    $test = $test_stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'courses' => $courses,
        'recent_progress' => $progress,
        'test_result' => $test
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return chat history
    $history = get_chat_history($conn, $user_id);
    echo json_encode(['history' => $history]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // debug_point('Starting POST request processing');
    $input = $_POST['message'] ?? '';
    $input = trim($input);
    if ($input === '') {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }
    
    // debug_point('Checking for database info');
    // Check if we received database information from JavaScript
    $db_info = null;
    if (isset($_POST['db_info']) && !empty($_POST['db_info'])) {
        $db_info = json_decode($_POST['db_info'], true);
    }
    
    // debug_point('Saving user message');
    // Save user message
    $user_created_at = insert_message($conn, $user_id, 0, $input);

    // debug_point('Getting user profile');
    // Get user profile for personalization
    $user_profile = get_user_profile($conn, $user_id);
    $user_name = $user_profile['full_name'] ?? $user_profile['username'] ?? 'User';
    $proficiency_level = $user_profile['proficiency_level'] ?? 'Unknown';
    
    // Get user learning information
    $learning_info = get_user_learning_info($conn, $user_id);
    
    // Format courses info
    $courses_info = '';
    if (!empty($learning_info['courses'])) {
        $courses_info = "User's enrolled courses:\n";
        foreach ($learning_info['courses'] as $course) {
            $courses_info .= "- {$course['title']} (Level: {$course['level']})\n";
        }
    }
    
    // Format progress info
    $progress_info = '';
    if (!empty($learning_info['recent_progress'])) {
        $progress_info = "User's recent learning activities:\n";
        foreach ($learning_info['recent_progress'] as $progress) {
            $date = date('Y-m-d', strtotime($progress['last_accessed']));
            $progress_info .= "- {$progress['material_title']} from {$progress['course_title']} ({$progress['progress']}% complete, last accessed: {$date})\n";
        }
    }
    
    // Format test info
    $test_info = '';
    if (!empty($learning_info['test_result'])) {
        $test = $learning_info['test_result'];
        $date = date('Y-m-d', strtotime($test['test_date']));
        $test_info = "User's level test result: {$test['assigned_level']} (Score: {$test['score']}%, Date: {$date})\n";
    }

    // IMPORTANT: Include database access utility
    require_once 'chatbot-ai-data.php';
    
    // Get platform statistics
    $platform_stats = getPlatformStats($conn);
    $platform_stats_text = '';
    if ($platform_stats) {
        $platform_stats_text = "Platform statistics:\n";
        $platform_stats_text .= "- Total courses: {$platform_stats['total_courses']}\n";
        $platform_stats_text .= "- Total users: {$platform_stats['total_users']}\n";
        $platform_stats_text .= "- Total materials: {$platform_stats['total_materials']}\n";
        $platform_stats_text .= "- Total quizzes: {$platform_stats['total_quizzes']}\n";
    }
    
    // Get all proficiency levels
    $proficiency_levels = getProficiencyLevels($conn);
    $levels_text = '';
    if ($proficiency_levels) {
        $levels_text = "Available proficiency levels: " . implode(', ', $proficiency_levels);
    }
    
    // Process specific database info from the JavaScript
    $specific_db_info = '';
    if ($db_info) {
        if (isset($db_info['success']) && $db_info['success'] && isset($db_info['data'])) {
            $data = $db_info['data'];
            
            // Handle platform stats
            if (isset($data['total_courses'])) {
                $specific_db_info = "Current platform statistics:\n";
                $specific_db_info .= "- Total courses: {$data['total_courses']}\n";
                $specific_db_info .= "- Total users: {$data['total_users']}\n";
                $specific_db_info .= "- Total materials: {$data['total_materials']}\n";
                $specific_db_info .= "- Total quizzes: {$data['total_quizzes']}\n";
            } 
            // Handle all courses
            else if (is_array($data) && isset($data[0]['course_id'])) {
                $specific_db_info = "Available courses:\n";
                foreach ($data as $course) {
                    $specific_db_info .= "- {$course['title']} (Level: {$course['level']})\n";
                    $specific_db_info .= "  ID: {$course['course_id']}\n";
                    $specific_db_info .= "  Description: " . substr($course['description'], 0, 100) . (strlen($course['description']) > 100 ? "..." : "") . "\n";
                    if (isset($course['duration']) && $course['duration']) {
                        $specific_db_info .= "  Duration: {$course['duration']}\n";
                    }
                    if (isset($course['difficulty_level']) && $course['difficulty_level']) {
                        $specific_db_info .= "  Difficulty: {$course['difficulty_level']}\n";
                    }
                    if (isset($course['instructor']) && $course['instructor']) {
                        $specific_db_info .= "  Instructor: {$course['instructor']}\n";
                    }
                    if (isset($course['enrollment_count'])) {
                        $specific_db_info .= "  Enrollments: {$course['enrollment_count']}\n";
                    }
                    $specific_db_info .= "\n";
                }
            }
            // Handle course details
            else if (isset($data['course'])) {
                $course = $data['course'];
                $specific_db_info = "Course details for {$course['title']} (ID: {$course['course_id']}):\n";
                $specific_db_info .= "Level: {$course['level']}\n";
                $specific_db_info .= "Description: {$course['description']}\n";
                
                if (isset($course['duration']) && $course['duration']) {
                    $specific_db_info .= "Duration: {$course['duration']}\n";
                }
                
                if (isset($course['difficulty_level']) && $course['difficulty_level']) {
                    $specific_db_info .= "Difficulty: {$course['difficulty_level']}\n";
                }
                
                if (isset($course['instructor']) && $course['instructor']) {
                    $specific_db_info .= "Instructor: {$course['instructor']}\n";
                }
                
                if (isset($course['enrollment_count'])) {
                    $specific_db_info .= "Total enrollments: {$course['enrollment_count']}\n";
                }
                
                if (isset($data['materials']) && !empty($data['materials'])) {
                    $specific_db_info .= "\nCourse materials:\n";
                    foreach ($data['materials'] as $idx => $material) {
                        $specific_db_info .= ($idx + 1) . ". {$material['title']}\n";
                        if (!empty($material['description'])) {
                            $specific_db_info .= "   {$material['description']}\n";
                        }
                    }
                }
                
                if (isset($data['material_count'])) {
                    $specific_db_info .= "\nTotal materials: {$data['material_count']}\n";
                }
            }
        } else if (isset($db_info['error'])) {
            $specific_db_info = "Database error: {$db_info['error']}\n";
        }
    }

    // Get current date and time for the system prompt
    $current_date_time = date('Y-m-d H:i:s');

    // Create better system prompt with user profile information
    $system_prompt = <<<EOT
You are an AI assistant for an English e-learning platform called "ELearning". Your primary role is to help users learn English and navigate the platform.

About the user:
Name: $user_name
Proficiency Level: $proficiency_level

$courses_info
$progress_info
$test_info

Platform information:
$platform_stats_text
$levels_text

$specific_db_info

Your task is to:
1. Be friendly, supportive and motivational for language learners
2. Answer questions about English grammar, vocabulary, and common usage
3. Provide examples when explaining language concepts
4. Help users navigate the platform features like courses, tests, and resources
5. When users ask about course information, tell them about the available courses based on the data I've provided
6. Always provide accurate information from the database when it's available

If asked about courses or platform statistics, provide only the factual information from the database. Never make up courses or student numbers that aren't in the data.

Current date/time: $current_date_time
EOT;

    // Define the full conversation context
    $full_context = [
        [
            'role' => 'system',
            'content' => $system_prompt
        ]
    ];

    // Get chat history if any
    $history = get_chat_history($conn, $user_id);
    foreach ($history as $msg) {
        $role = $msg['sender'] == 0 ? 'user' : 'assistant';
        $full_context[] = [
            'role' => $role,
            'content' => $msg['message']
        ];
    }

    // Add the current message
    $full_context[] = [
        'role' => 'user',
        'content' => $input
    ];

    // Get last 20 messages as context
    $stmt = $conn->prepare("SELECT sender, message FROM chat_messages WHERE user_id = ? ORDER BY message_id DESC LIMIT 20");
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
    $messages = array_merge($full_context, $messages);
    
    // Use simpler model configuration for stability
    $payload = [
        'model' => 'meta-llama/llama-4-scout:free',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 300
    ];
    
    // Basic rate limiting (4 seconds between requests)
    if (!isset($_SESSION['last_ai_request'])) {
        $_SESSION['last_ai_request'] = 0;
    }
    
    $now = time();
    $timeSinceLastRequest = $now - $_SESSION['last_ai_request'];
    if ($timeSinceLastRequest < 4) {
        sleep(4 - $timeSinceLastRequest);
    }
    
    $_SESSION['last_ai_request'] = time();
    
    // Set up the API request
    // debug_point('Setting up API request');
    $json_payload = json_encode($payload);
    $api_url = 'https://openrouter.ai/api/v1/chat/completions';
    
    // debug_point('Initializing cURL');
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $OPENROUTER_API_KEY,
        'Content-Type: application/json',
        'HTTP-Referer: http://localhost/el', 
        'X-Title: eLearning AI Chatbot',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Execute the request
    // debug_point('Executing API request');
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    // debug_point('API response code: ' . $httpcode);
    if ($curl_error) {
        // debug_point('cURL error: ' . $curl_error);
    }
    curl_close($ch);
    
    // Helper to generate a friendly response with emoji
    function add_emoji($message) {
        $emojis = ['😊', '📚', '✨', '👋', '🎓', '👍', '🤔', '💡', '⭐', '🌟', '🔤', '📝', '📖'];
        $rand_emoji = $emojis[array_rand($emojis)];
        
        // Check if the message already has an emoji
        if (!preg_match('/[\x{1F300}-\x{1F6FF}]/u', $message)) {
            // Add emoji to beginning or end based on if it's a greeting
            if (preg_match('/^(hi|hello|hey|greetings)/i', $message)) {
                $message = $rand_emoji . ' ' . $message;
            } else {
                $message = rtrim($message, '.!?') . '! ' . $rand_emoji;
            }
        }
        
        return $message;
    }
    
    // Process the API response
    // debug_point('Processing API response');
    if ($response === false) {
        // Connection error
        // debug_point('API connection failure');
        $ai_reply = add_emoji('Sorry, I\'m having trouble connecting right now. Please try again later.');
    } else {
        // Try to parse the JSON response
        // debug_point('Parsing JSON response');
    $data = json_decode($response, true);
        $json_error = json_last_error();
        
        // Check if JSON parsing was successful
        if ($json_error !== JSON_ERROR_NONE) {
            // debug_point('JSON decode error: ' . json_last_error_msg());
            // debug_point('Raw response beginning: ' . substr($response, 0, 200));
            $ai_reply = add_emoji('Sorry, I received an invalid response. Please try again later.');
        }
        else if ($httpcode === 200 && isset($data['choices'][0]['message']['content'])) {
            // Success - extract the response content
            // debug_point('Successfully extracted AI response');
            $ai_reply = $data['choices'][0]['message']['content'];
            $ai_reply = add_emoji($ai_reply);
        } else {
            // API error or invalid response format
            // debug_point('Invalid response format or API error. HTTP code: ' . $httpcode);
            if (isset($data['error'])) {
                // debug_point('API error: ' . json_encode($data['error']));
            }
            // debug_point('Response keys: ' . (is_array($data) ? json_encode(array_keys($data)) : 'data is not an array'));
            $ai_reply = add_emoji('Sorry, I encountered an issue processing your request. Please try again.');
        }
    }
    
    // Clean the output buffer
    ob_clean();
    
    // Save the AI reply to database
    $ai_created_at = insert_message($conn, $user_id, 1, $ai_reply);
    echo json_encode(['reply' => $ai_reply, 'created_at' => $ai_created_at]);
    exit;
}

// Fallback for any other method
ob_clean();
http_response_code(405);
$supported_methods = ['GET', 'POST'];
echo json_encode([
    'error' => 'Method not allowed', 
    'message' => 'This endpoint only supports: ' . implode(', ', $supported_methods),
    'current_method' => $_SERVER['REQUEST_METHOD']
]);
exit;