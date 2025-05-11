<?php
/**
 * This file provides secure database access for the chatbot AI
 * to retrieve information about the platform (courses, stats, etc.)
 * while blocking access to sensitive information like test answers.
 */
// Start output buffering to capture any unwanted output
ob_start();

// Turn off error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Custom error handler to log errors but not display them
if (!function_exists('custom_error_handler')) {
    function custom_error_handler($errno, $errstr, $errfile, $errline) {
        // Log error to server log file
        error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
        // Don't display the error
        return true;
    }
    set_error_handler("custom_error_handler");
} else {
    // Function already exists, just set the error handler
    set_error_handler("custom_error_handler");
}

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/db.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/**
 * Get general platform statistics
 */
function getPlatformStats($conn) {
    try {
        // Get total courses
        $stmt = $conn->prepare("SELECT COUNT(*) FROM courses");
        $stmt->execute();
        $totalCourses = $stmt->fetchColumn();
        
        // Get total users
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $totalUsers = $stmt->fetchColumn();
        
        // Get total materials
        $stmt = $conn->prepare("SELECT COUNT(*) FROM course_materials");
        $stmt->execute();
        $totalMaterials = $stmt->fetchColumn();
        
        // Get total quizzes
        $stmt = $conn->prepare("SELECT COUNT(*) FROM quizzes");
        $stmt->execute();
        $totalQuizzes = $stmt->fetchColumn();
        
        return [
            'total_courses' => $totalCourses,
            'total_users' => $totalUsers,
            'total_materials' => $totalMaterials, 
            'total_quizzes' => $totalQuizzes
        ];
    } catch (PDOException $e) {
        error_log("Error getting platform stats: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all courses with basic information
 */
function getAllCourses($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT c.course_id, c.title, c.level, c.description, c.duration, c.difficulty_level, 
                   u.username as instructor_username, up.full_name as instructor_name,
                   (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.course_id) as enrollment_count
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            ORDER BY c.level, c.title
        ");
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format instructor name
        foreach ($courses as &$course) {
            $course['instructor'] = !empty($course['instructor_name']) ? $course['instructor_name'] : $course['instructor_username'];
            // Remove redundant fields
            unset($course['instructor_name']);
            unset($course['instructor_username']);
        }
        
        return $courses;
    } catch (PDOException $e) {
        error_log("Error getting courses: " . $e->getMessage());
        return false;
    }
}

/**
 * Get single course details
 */
function getCourseDetails($conn, $courseId) {
    try {
        // Get course info
        $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return false;
        }
        
        // Get course materials
        $stmt = $conn->prepare("SELECT material_id, title, type, description FROM course_materials WHERE course_id = ? ORDER BY sort_order");
        $stmt->execute([$courseId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get enrollment count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?");
        $stmt->execute([$courseId]);
        $enrollmentCount = $stmt->fetchColumn();
        
        return [
            'course' => $course,
            'materials' => $materials,
            'enrollment_count' => $enrollmentCount
        ];
    } catch (PDOException $e) {
        error_log("Error getting course details: " . $e->getMessage());
        return false;
    }
}

/**
 * Get courses by level
 */
function getCoursesByLevel($conn, $level) {
    try {
        $stmt = $conn->prepare("
            SELECT c.course_id, c.title, c.description, c.duration, c.difficulty_level, 
                   u.username as instructor_username, up.full_name as instructor_name,
                   (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.course_id) as enrollment_count
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE c.level = ? 
            ORDER BY c.title
        ");
        $stmt->execute([$level]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format instructor name
        foreach ($courses as &$course) {
            $course['instructor'] = !empty($course['instructor_name']) ? $course['instructor_name'] : $course['instructor_username'];
            // Remove redundant fields
            unset($course['instructor_name']);
            unset($course['instructor_username']);
        }
        
        return $courses;
    } catch (PDOException $e) {
        error_log("Error getting courses by level: " . $e->getMessage());
        return false;
    }
}

/**
 * Search courses
 */
function searchCourses($conn, $query) {
    try {
        $searchQuery = "%$query%";
        $stmt = $conn->prepare("
            SELECT c.course_id, c.title, c.level, c.description, c.duration, c.difficulty_level, 
                   u.username as instructor_username, up.full_name as instructor_name,
                   (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.course_id) as enrollment_count
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE c.title LIKE ? OR c.description LIKE ? 
            ORDER BY c.level, c.title
        ");
        $stmt->execute([$searchQuery, $searchQuery]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format instructor name
        foreach ($courses as &$course) {
            $course['instructor'] = !empty($course['instructor_name']) ? $course['instructor_name'] : $course['instructor_username'];
            // Remove redundant fields
            unset($course['instructor_name']);
            unset($course['instructor_username']);
        }
        
        return $courses;
    } catch (PDOException $e) {
        error_log("Error searching courses: " . $e->getMessage());
        return false;
    }
}

/**
 * Get available proficiency levels
 */
function getProficiencyLevels($conn) {
    try {
        $stmt = $conn->prepare("SELECT DISTINCT level FROM courses ORDER BY FIELD(level, 'A1', 'A2', 'B1', 'B2', 'C1', 'C2')");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting proficiency levels: " . $e->getMessage());
        return false;
    }
}

/**
 * Get available resources (dictionary, grammar guides, etc.)
 */
function getResources($conn) {
    try {
        $stmt = $conn->prepare("SELECT resource_id, title, type, description FROM resources ORDER BY title");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting resources: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's enrolled courses
 */
function getUserCourses($conn, $userId) {
    try {
        $stmt = $conn->prepare("
            SELECT c.course_id, c.title, c.level, c.description, e.enrollment_date
            FROM courses c
            JOIN course_enrollments e ON c.course_id = e.course_id
            WHERE e.user_id = ?
            ORDER BY e.enrollment_date DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user courses: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's progress across all courses
 */
function getUserProgress($conn, $userId) {
    try {
        $stmt = $conn->prepare("
            SELECT cm.title AS material_title, c.title AS course_title, c.level,
                  up.progress, up.last_accessed
            FROM user_progress up
            JOIN course_materials cm ON up.material_id = cm.material_id
            JOIN courses c ON cm.course_id = c.course_id
            WHERE up.user_id = ?
            ORDER BY up.last_accessed DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user progress: " . $e->getMessage());
        return false;
    }
}

/**
 * Get detailed course information with all materials
 */
function getCourseWithMaterials($conn, $courseId) {
    try {
        // Get course details
        $stmt = $conn->prepare("
            SELECT c.course_id, c.title, c.level, c.description, c.duration, c.difficulty_level, 
                   c.created_at, c.updated_at, c.is_featured,
                   u.username as instructor_username, up.full_name as instructor_name,
                   (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.course_id) as enrollment_count
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE c.course_id = ?
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return false;
        }
        
        // Format instructor name
        $course['instructor'] = !empty($course['instructor_name']) ? $course['instructor_name'] : $course['instructor_username'];
        unset($course['instructor_name']);
        unset($course['instructor_username']);
        
        // Get course materials
        $stmt = $conn->prepare("
            SELECT material_id, title, description, order_number, created_at, updated_at
            FROM course_materials 
            WHERE course_id = ? 
            ORDER BY order_number
        ");
        $stmt->execute([$courseId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'course' => $course,
            'materials' => $materials,
            'material_count' => count($materials)
        ];
    } catch (PDOException $e) {
        error_log("Error getting course with materials: " . $e->getMessage());
        return false;
    }
}

// Process API requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    $response = ['success' => false];
    
    switch ($action) {
        case 'platform_stats':
            $stats = getPlatformStats($conn);
            if ($stats) {
                $response = ['success' => true, 'data' => $stats];
            }
            break;
            
        case 'all_courses':
            $courses = getAllCourses($conn);
            if ($courses) {
                $response = ['success' => true, 'data' => $courses];
            }
            break;
            
        case 'course_details':
            $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
            if ($courseId > 0) {
                $details = getCourseDetails($conn, $courseId);
                if ($details) {
                    $response = ['success' => true, 'data' => $details];
                }
            }
            break;
            
        case 'courses_by_level':
            $level = $_GET['level'] ?? '';
            if ($level) {
                $courses = getCoursesByLevel($conn, $level);
                if ($courses) {
                    $response = ['success' => true, 'data' => $courses];
                }
            }
            break;
            
        case 'search_courses':
            $query = $_GET['query'] ?? '';
            if (strlen($query) >= 2) {
                $courses = searchCourses($conn, $query);
                if ($courses) {
                    $response = ['success' => true, 'data' => $courses];
                } else {
                    $response = ['success' => true, 'data' => []];
                }
            }
            break;
            
        case 'proficiency_levels':
            $levels = getProficiencyLevels($conn);
            if ($levels) {
                $response = ['success' => true, 'data' => $levels];
            }
            break;
            
        case 'resources':
            $resources = getResources($conn);
            if ($resources) {
                $response = ['success' => true, 'data' => $resources];
            }
            break;
            
        case 'user_courses':
            $courses = getUserCourses($conn, $user_id);
            if ($courses) {
                $response = ['success' => true, 'data' => $courses];
            } else {
                $response = ['success' => true, 'data' => []];
            }
            break;
            
        case 'user_progress':
            $progress = getUserProgress($conn, $user_id);
            if ($progress) {
                $response = ['success' => true, 'data' => $progress];
            } else {
                $response = ['success' => true, 'data' => []];
            }
            break;
            
        case 'course_with_materials':
            $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
            if ($courseId > 0) {
                $courseWithMaterials = getCourseWithMaterials($conn, $courseId);
                if ($courseWithMaterials) {
                    $response = ['success' => true, 'data' => $courseWithMaterials];
                }
            }
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Invalid action'];
    }
    
    // Clean the output buffer before sending JSON
    ob_clean();
    echo json_encode($response);
    exit;
}

// Fallback - only execute if this file is called directly, not when included
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    // Clean the output buffer before sending JSON
    ob_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?> 