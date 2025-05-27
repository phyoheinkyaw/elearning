<?php
/**
 * Instructor Functions
 * Helper functions for the instructor panel
 */

/**
 * Check if a user has instructor role
 *
 * @param int $user_id The user ID to check
 * @return bool True if the user is an instructor, false otherwise
 */
function check_instructor_role($user_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['role'] === '2'; // Instructor role ID is 2
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get total student count for an instructor's courses
 *
 * @param int $instructor_id The instructor ID
 * @return int The total number of unique students
 */
function get_instructor_student_count($instructor_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ce.user_id) as student_count
            FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.course_id
            WHERE c.instructor_id = ?
        ");
        $stmt->execute([$instructor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['student_count'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get student progress for a specific course
 *
 * @param int $course_id The course ID
 * @return array Student progress data
 */
function get_course_student_progress($course_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                u.user_id,
                u.username,
                COALESCE(up.full_name, u.username) as student_name,
                COUNT(DISTINCT cm.material_id) as total_materials,
                COUNT(DISTINCT CASE WHEN up2.progress = 100 THEN cm.material_id END) as completed_materials,
                COALESCE(ROUND(AVG(up2.progress)), 0) as avg_progress
            FROM course_enrollments ce
            JOIN users u ON ce.user_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            JOIN course_materials cm ON ce.course_id = cm.course_id
            LEFT JOIN user_progress up2 ON cm.material_id = up2.material_id AND u.user_id = up2.user_id
            WHERE ce.course_id = ?
            GROUP BY u.user_id
            ORDER BY avg_progress DESC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get enrollment trend data for an instructor's courses
 *
 * @param int $instructor_id The instructor ID
 * @param int $days Number of days to include in the trend (default: 30)
 * @return array Enrollment trend data
 */
function get_enrollment_trend($instructor_id, $days = 30) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                DATE(ce.enrolled_at) as date,
                COUNT(*) as count
            FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.course_id
            WHERE c.instructor_id = ?
            AND ce.enrolled_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            GROUP BY DATE(ce.enrolled_at)
            ORDER BY date
        ");
        $stmt->execute([$instructor_id, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get quiz performance data for a course
 *
 * @param int $course_id The course ID
 * @return array Quiz performance data
 */
function get_course_quiz_performance($course_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                q.quiz_id,
                q.title,
                COUNT(qa.attempt_id) as attempt_count,
                ROUND(AVG(qa.score / qa.max_score * 100)) as avg_score,
                MIN(qa.score / qa.max_score * 100) as min_score,
                MAX(qa.score / qa.max_score * 100) as max_score
            FROM quizzes q
            JOIN course_materials cm ON q.material_id = cm.material_id
            LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
            WHERE cm.course_id = ?
            GROUP BY q.quiz_id
            ORDER BY cm.order_number
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get material engagement data for a course
 *
 * @param int $course_id The course ID
 * @return array Material engagement data
 */
function get_material_engagement($course_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                cm.material_id,
                cm.title,
                COUNT(DISTINCT up.user_id) as viewed_by,
                ROUND(AVG(up.progress)) as avg_progress,
                cm.order_number
            FROM course_materials cm
            LEFT JOIN user_progress up ON cm.material_id = up.material_id
            WHERE cm.course_id = ?
            GROUP BY cm.material_id
            ORDER BY cm.order_number
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
} 