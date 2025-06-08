<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if user_id is provided
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$user_id = (int)$_POST['user_id'];

try {
    // Get user role first
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    $role = (int)$user['role'];
    $data = ['user_role' => $role];
    
    // Get data based on user role
    if ($role === 2) { // Instructor
        // Get instructor's courses
        $stmt = $conn->prepare("
            SELECT c.course_id, c.title, c.level, c.is_featured,
                   (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.course_id) as student_count,
                   (SELECT COUNT(*) FROM course_materials cm WHERE cm.course_id = c.course_id) as materials_count
            FROM courses c
            WHERE c.instructor_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $courses = $stmt->fetchAll();
        
        $data['has_courses'] = count($courses) > 0;
        $data['course_count'] = count($courses);
        $data['courses'] = $courses;
        $data['total_students'] = array_sum(array_column($courses, 'student_count'));
        $data['total_materials'] = array_sum(array_column($courses, 'materials_count'));
        $data['featured_courses'] = count(array_filter($courses, function($course) { return $course['is_featured'] == 1; }));
    }
    
    // For all user types (including instructors), get:
    
    // 1. Course enrollments
    $stmt = $conn->prepare("
        SELECT c.title, c.level, 
               (SELECT COUNT(*) FROM user_progress up 
                JOIN course_materials cm ON up.material_id = cm.material_id 
                WHERE up.user_id = ? AND cm.course_id = c.course_id) as progress_count
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.course_id
        WHERE ce.user_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
    $enrollments = $stmt->fetchAll();
    $data['has_enrollments'] = count($enrollments) > 0;
    $data['enrollments_count'] = count($enrollments);
    $data['enrollments'] = $enrollments;
    
    // 2. Quiz attempts
    $stmt = $conn->prepare("
        SELECT q.title, qa.score, qa.completion_date
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        WHERE qa.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $quiz_attempts = $stmt->fetchAll();
    $data['has_quiz_attempts'] = count($quiz_attempts) > 0;
    $data['quiz_attempts_count'] = count($quiz_attempts);
    $data['quiz_attempts'] = $quiz_attempts;
    
    // 3. Level test results
    $stmt = $conn->prepare("
        SELECT assigned_level, score, test_date
        FROM level_test_results
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $level_tests = $stmt->fetchAll();
    $data['has_level_tests'] = count($level_tests) > 0;
    $data['level_tests_count'] = count($level_tests);
    $data['level_tests'] = $level_tests;
    
    // 4. Game progress (Wordscapes)
    $stmt = $conn->prepare("
        SELECT total_score, current_level_score, hints_used
        FROM wordscapes_user_progress
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $game_progress = $stmt->fetch();
    $data['has_game_progress'] = $game_progress ? true : false;
    $data['game_progress'] = $game_progress;
    
    // 5. Chat messages
    $stmt = $conn->prepare("
        SELECT COUNT(*) as message_count
        FROM chat_messages
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $chat = $stmt->fetch();
    $data['has_chat_messages'] = $chat['message_count'] > 0;
    $data['chat_message_count'] = (int)$chat['message_count'];
    
    // Return the combined data
    echo json_encode($data);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
exit(); 