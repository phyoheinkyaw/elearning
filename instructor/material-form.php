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

// Get material ID if editing
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $material_id > 0;

// Get course ID if coming from a specific course
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Initialize variables
$material = [];

// If editing, fetch material data
if ($is_edit) {
    try {
        $stmt = $conn->prepare("
            SELECT cm.* 
            FROM course_materials cm
            JOIN courses c ON cm.course_id = c.course_id
            WHERE cm.material_id = ? AND c.instructor_id = ?
        ");
        $stmt->execute([$material_id, $instructor_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$material) {
            $_SESSION['error_message'] = "Material not found or you don't have permission to edit it.";
            header('Location: materials.php');
            exit();
        }
        
        // Set course_id from material if not provided
        if (!$course_id) {
            $course_id = $material['course_id'];
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error fetching material data.";
        header('Location: materials.php');
        exit();
    }
}

// If course ID is provided, verify instructor owns this course
if ($course_id) {
    try {
        $stmt = $conn->prepare("SELECT course_id, title FROM courses WHERE course_id = ? AND instructor_id = ?");
        $stmt->execute([$course_id, $instructor_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            $_SESSION['error_message'] = "Course not found or you don't have permission to access it.";
            header('Location: courses.php');
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error verifying course ownership.";
        header('Location: courses.php');
        exit();
    }
}

// Get instructor's courses for dropdown
$stmt = $conn->prepare("SELECT course_id, title FROM courses WHERE instructor_id = ? ORDER BY title");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $content = $_POST['content'];
    $course_id = (int)$_POST['course_id'];
    $order_number = isset($_POST['order_number']) ? (int)$_POST['order_number'] : null;
    
    // Verify course belongs to instructor
    $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = "You don't have permission to add materials to this course.";
        header('Location: materials.php');
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // If order_number not provided, get the next available order number
        if (!$order_number) {
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(order_number), 0) + 1 as next_order 
                FROM course_materials 
                WHERE course_id = ?
            ");
            $stmt->execute([$course_id]);
            $order_number = $stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
        }
        
        if ($is_edit) {
            // Update existing material
            $stmt = $conn->prepare("
                UPDATE course_materials 
                SET title = ?, description = ?, content = ?, order_number = ?
                WHERE material_id = ?
            ");
            $stmt->execute([
                $title, $description, $content, $order_number, $material_id
            ]);
            
            $_SESSION['success_message'] = "Material updated successfully.";
        } else {
            // Insert new material
            $stmt = $conn->prepare("
                INSERT INTO course_materials 
                (course_id, title, description, content, order_number, type) 
                VALUES (?, ?, ?, ?, ?, 'document')
            ");
            $stmt->execute([
                $course_id, $title, $description, $content, $order_number
            ]);
            
            $_SESSION['success_message'] = "Material created successfully.";
        }
        
        $conn->commit();
        
        // Redirect to materials page or course materials page
        if ($course_id) {
            header("Location: course-details.php?id=$course_id");
        } else {
            header("Location: materials.php");
        }
        exit();
        
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Page title
$page_title = $is_edit ? "Edit Material" : "Add New Material";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Instructor Panel</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <link href="css/instructor-style.css" rel="stylesheet">
    <style>
        #editor-container {
            height: 400px;
            margin-bottom: 20px;
        }
        .ql-editor {
            min-height: 350px;
            font-size: 16px;
            font-family: inherit;
        }
    </style>
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
                            <a href="courses.php" class="nav-link">
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
                            <a href="materials.php" class="nav-link active">
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
                        <div>
                            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
                            <?php if (!empty($course)): ?>
                                <p class="text-muted">For course: <?php echo htmlspecialchars($course['title']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($course_id): ?>
                                <a href="course-details.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Course
                                </a>
                            <?php else: ?>
                                <a href="materials.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Materials
                                </a>
                            <?php endif; ?>
                        </div>
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
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="title" class="form-label">Material Title</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($material['title'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter a title for this material.</div>
                                    </div>
                                    
                                    <?php if (empty($course_id)): ?>
                                        <div class="col-md-4">
                                            <label for="course_id" class="form-label">Select Course</label>
                                            <select class="form-select" id="course_id" name="course_id" required>
                                                <option value="">Select a course</option>
                                                <?php foreach ($courses as $course_option): ?>
                                                    <option value="<?php echo $course_option['course_id']; ?>" 
                                                            <?php echo ($material['course_id'] ?? $course_id) == $course_option['course_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($course_option['title']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Please select a course.</div>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($material['description'] ?? ''); ?></textarea>
                                        <div class="invalid-feedback">Please provide a brief description.</div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="order_number" class="form-label">Order Number</label>
                                        <input type="number" class="form-control" id="order_number" name="order_number" min="1" 
                                               value="<?php echo htmlspecialchars($material['order_number'] ?? ''); ?>">
                                        <div class="form-text">Leave empty for automatic ordering at the end.</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="content" class="form-label">Content</label>
                                    <div id="editor-container">
                                        <div id="editor"><?php echo $material['content'] ?? ''; ?></div>
                                    </div>
                                    <input type="hidden" name="content" id="content">
                                    <div class="invalid-feedback">Please add content to this material.</div>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Update' : 'Create'; ?> Material
                                    </button>
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
    <!-- Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        // Initialize Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });
        
        // Form submission handling
        document.querySelector('form').addEventListener('submit', function(e) {
            // Get Quill content
            document.getElementById('content').value = quill.root.innerHTML;
            
            // Form validation
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            this.classList.add('was-validated');
        });
    </script>
</body>
</html> 