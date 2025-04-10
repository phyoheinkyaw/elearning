<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/courses';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $course_id > 0;
$course = [];

// Get course data if editing
if ($is_edit) {
    try {
        $stmt = $conn->prepare("
            SELECT c.*, u.username as instructor_name, up.full_name as instructor_full_name
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE c.course_id = ?
        ");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            $_SESSION['error_message'] = "Course not found.";
            header('Location: courses.php');
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error fetching course data.";
        header('Location: courses.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $instructor_id = (int)sanitize_input($_POST['instructor_id']);
    $level = sanitize_input($_POST['level']);
    $duration = sanitize_input($_POST['duration']);
    $difficulty_level = sanitize_input($_POST['difficulty_level']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    try {
        $conn->beginTransaction();
        
        // Handle thumbnail upload
        $thumbnail_url = $course['thumbnail_url'] ?? null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['thumbnail']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                // Delete old thumbnail if exists
                if ($thumbnail_url && file_exists($thumbnail_url)) {
                    unlink($thumbnail_url);
                }
                
                $file_ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $file_name = 'course_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_path)) {
                    $thumbnail_url = $upload_path;
                }
            }
        }
        
        if ($is_edit) {
            // Update course
            $stmt = $conn->prepare("
                UPDATE courses 
                SET title = ?, description = ?, instructor_id = ?, level = ?,
                    duration = ?, difficulty_level = ?, is_featured = ?,
                    thumbnail_url = ?, updated_at = CURRENT_TIMESTAMP
                WHERE course_id = ?
            ");
            $stmt->execute([
                $title, $description, $instructor_id, $level,
                $duration, $difficulty_level, $is_featured,
                $thumbnail_url, $course_id
            ]);
            
            $_SESSION['success_message'] = "Course updated successfully.";
        } else {
            // Insert new course
            $stmt = $conn->prepare("
                INSERT INTO courses (
                    title, description, instructor_id, level,
                    duration, difficulty_level, is_featured, thumbnail_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $description, $instructor_id, $level,
                $duration, $difficulty_level, $is_featured, $thumbnail_url
            ]);
            
            $_SESSION['success_message'] = "Course created successfully.";
        }
        
        $conn->commit();
        header('Location: courses.php');
        exit();
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Get available levels
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

// Get difficulty levels
$difficulty_levels = ['Beginner', 'Elementary', 'Intermediate', 'Upper Intermediate', 'Advanced', 'Proficient'];

// Get instructors
try {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, up.full_name
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE u.role = 2
        ORDER BY up.full_name
    ");
    $stmt->execute();
    $instructors = $stmt->fetchAll();
} catch(PDOException $e) {
    $instructors = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Course - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0"><?php echo $is_edit ? 'Edit' : 'Add'; ?> Course</h1>
                    <a href="courses.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Courses
                    </a>
                </div>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Course Title</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($course['title'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a course title.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="instructor_id" class="form-label">Instructor</label>
                                    <select class="form-select" id="instructor_id" name="instructor_id" required>
                                        <option value="">Select Instructor</option>
                                        <?php foreach ($instructors as $instructor): ?>
                                            <option value="<?php echo $instructor['user_id']; ?>" 
                                                <?php echo isset($course['instructor_id']) && $course['instructor_id'] == $instructor['user_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($instructor['full_name'] ?: $instructor['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select an instructor.</div>
                                </div>
                                
                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                                    <div class="invalid-feedback">Please enter a course description.</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="level" class="form-label">Level</label>
                                    <select class="form-select" id="level" name="level" required>
                                        <option value="">Select Level</option>
                                        <?php foreach ($levels as $level): ?>
                                            <option value="<?php echo $level; ?>" 
                                                <?php echo isset($course['level']) && $course['level'] === $level ? 'selected' : ''; ?>>
                                                <?php echo $level; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a level.</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="difficulty_level" class="form-label">Difficulty</label>
                                    <select class="form-select" id="difficulty_level" name="difficulty_level" required>
                                        <option value="">Select Difficulty</option>
                                        <?php foreach ($difficulty_levels as $difficulty): ?>
                                            <option value="<?php echo $difficulty; ?>" 
                                                <?php echo isset($course['difficulty_level']) && $course['difficulty_level'] === $difficulty ? 'selected' : ''; ?>>
                                                <?php echo $difficulty; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a difficulty level.</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="duration" class="form-label">Duration</label>
                                    <input type="text" class="form-control" id="duration" name="duration" 
                                           value="<?php echo htmlspecialchars($course['duration'] ?? ''); ?>" 
                                           placeholder="e.g., 8 weeks, 24 hours" required>
                                    <div class="invalid-feedback">Please enter the course duration.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="thumbnail" class="form-label">Course Thumbnail</label>
                                    <input type="file" class="form-control" id="thumbnail" name="thumbnail" 
                                           accept="image/jpeg,image/png,image/gif">
                                    <div class="form-text">Leave empty to keep current thumbnail. Accepted formats: JPG, PNG, GIF</div>
                                    <?php if (isset($course['thumbnail_url']) && $course['thumbnail_url']): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" 
                                                 alt="Current thumbnail" class="img-thumbnail" style="max-height: 100px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" 
                                               <?php echo isset($course['is_featured']) && $course['is_featured'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_featured">Feature this course</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Update' : 'Create'; ?> Course
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html> 