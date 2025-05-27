<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/instructor_functions.php';

// Check if user is logged in and is an instructor
if (!is_logged_in() || $_SESSION['role'] !== 'instructor') {
    header('Location: ../login.php');
    exit();
}

// Get instructor ID
$instructor_id = $_SESSION['user_id'];

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
            SELECT * FROM courses
            WHERE course_id = ? AND instructor_id = ?
        ");
        $stmt->execute([$course_id, $instructor_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            $_SESSION['error_message'] = "Course not found or you don't have permission to edit it.";
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
    $level = sanitize_input($_POST['level']);
    $duration = sanitize_input($_POST['duration']);
    $difficulty_level = sanitize_input($_POST['difficulty_level']);
    
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
                SET title = ?, description = ?, level = ?,
                    duration = ?, difficulty_level = ?,
                    thumbnail_url = ?, updated_at = CURRENT_TIMESTAMP
                WHERE course_id = ? AND instructor_id = ?
            ");
            $stmt->execute([
                $title, $description, $level,
                $duration, $difficulty_level,
                $thumbnail_url, $course_id, $instructor_id
            ]);
            
            $_SESSION['success_message'] = "Course updated successfully.";
        } else {
            // Insert new course
            $stmt = $conn->prepare("
                INSERT INTO courses (
                    title, description, instructor_id, level,
                    duration, difficulty_level, thumbnail_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $description, $instructor_id, $level,
                $duration, $difficulty_level, $thumbnail_url
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Create'; ?> Course - Instructor Panel</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <link href="css/instructor-style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 instructor-sidebar">
                <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-white min-vh-100">
                    <a href="../index.php" class="d-flex align-items-center pb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <span class="fs-5 d-none d-sm-inline">ELearning</span>
                    </a>
                    <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start w-100" id="menu">
                        <li class="nav-item w-100">
                            <a href="index.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i>
                                <span class="d-none d-sm-inline">Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="courses.php" class="nav-link active">
                                <i class="fas fa-book"></i>
                                <span class="d-none d-sm-inline">My Courses</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="students.php" class="nav-link">
                                <i class="fas fa-users"></i>
                                <span class="d-none d-sm-inline">Students</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="analytics.php" class="nav-link">
                                <i class="fas fa-chart-bar"></i>
                                <span class="d-none d-sm-inline">Analytics</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="materials.php" class="nav-link">
                                <i class="fas fa-file-alt"></i>
                                <span class="d-none d-sm-inline">Materials</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="../profile.php" class="nav-link">
                                <i class="fas fa-user"></i>
                                <span class="d-none d-sm-inline">Profile</span>
                            </a>
                        </li>
                        <li class="nav-item w-100">
                            <a href="../logout.php" class="nav-link text-danger">
                                <i class="fas fa-sign-out-alt"></i>
                                <span class="d-none d-sm-inline">Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 instructor-main">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0"><?php echo $is_edit ? 'Edit' : 'Create'; ?> Course</h1>
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Courses
                        </a>
                    </div>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
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
                                        <label for="level" class="form-label">Proficiency Level</label>
                                        <select class="form-select" id="level" name="level" required>
                                            <option value="">Select Level</option>
                                            <?php foreach ($levels as $level): ?>
                                                <option value="<?php echo $level; ?>" 
                                                    <?php echo isset($course['level']) && $course['level'] === $level ? 'selected' : ''; ?>>
                                                    <?php echo $level; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a proficiency level.</div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                                        <div class="invalid-feedback">Please enter a course description.</div>
                                    </div>
                                    
                                    <div class="col-md-6">
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
                                    
                                    <div class="col-md-6">
                                        <label for="duration" class="form-label">Course Duration</label>
                                        <input type="text" class="form-control" id="duration" name="duration" 
                                               value="<?php echo htmlspecialchars($course['duration'] ?? ''); ?>" 
                                               placeholder="e.g., 8 weeks, 24 hours" required>
                                        <div class="invalid-feedback">Please enter the course duration.</div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="thumbnail" class="form-label">Course Thumbnail</label>
                                        <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                        <div class="form-text">Recommended size: 800x600 pixels. Leave empty to keep current image.</div>
                                        
                                        <?php if (isset($course['thumbnail_url']) && $course['thumbnail_url']): ?>
                                            <div class="mt-2">
                                                <p>Current thumbnail:</p>
                                                <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" class="img-thumbnail" style="max-height: 150px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Update' : 'Create'; ?> Course
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
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