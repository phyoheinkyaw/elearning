<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get material ID if editing
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $material_id > 0;

// Get course ID if coming from course-materials.php
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

// Get material data if editing
$material = [];
if ($is_edit) {
    try {
        $stmt = $conn->prepare("SELECT * FROM course_materials WHERE material_id = ?");
        $stmt->execute([$material_id]);
        $material = $stmt->fetch();
        
        if (!$material) {
            $_SESSION['error_message'] = "Material not found.";
            header('Location: materials.php');
            exit();
        }
        
        // If no course_id was provided in URL, use the material's course_id
        if (!$course_id) {
            $course_id = $material['course_id'];
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error fetching material data.";
        header('Location: materials.php');
        exit();
    }
}

// Get course data if course_id is provided
$course = null;
if ($course_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            $_SESSION['error_message'] = "Course not found.";
            header('Location: materials.php');
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error fetching course data.";
        header('Location: materials.php');
        exit();
    }
}

// Get all courses for dropdown
try {
    $stmt = $conn->query("SELECT course_id, title FROM courses ORDER BY title");
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $courses = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $content = $_POST['content'];
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    
    try {
        $conn->beginTransaction();
        
        // Get the next available order number if course is selected
        $order_number = null;
        if ($course_id) {
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(order_number), 0) + 1 as next_order 
                FROM course_materials 
                WHERE course_id = ?
            ");
            $stmt->execute([$course_id]);
            $order_number = $stmt->fetch()['next_order'];
        }
        
        if ($is_edit) {
            // Update material
            $stmt = $conn->prepare("
                UPDATE course_materials 
                SET title = ?, description = ?, content = ?, 
                    course_id = ?, order_number = ?
                WHERE material_id = ?
            ");
            $stmt->execute([
                $title, $description, $content, 
                $course_id, $order_number, $material_id
            ]);
            
            $_SESSION['success_message'] = "Material updated successfully.";
        } else {
            // Insert new material
            $stmt = $conn->prepare("
                INSERT INTO course_materials (
                    title, description, content, 
                    course_id, order_number
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $description, $content, 
                $course_id, $order_number
            ]);
            
            $_SESSION['success_message'] = "Material added successfully.";
        }
        
        $conn->commit();
        
        // Redirect based on context
        if ($course_id) {
            header('Location: course-materials.php?id=' . $course_id);
        } else {
            header('Location: materials.php');
        }
        exit();
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Material - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
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
        .submit-container {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-2"><?php echo $is_edit ? 'Edit' : 'Add'; ?> Material</h1>
                        <p class="text-muted mb-0">
                            <?php if ($course): ?>
                                For course: <?php echo htmlspecialchars($course['title']); ?>
                            <?php else: ?>
                                Create a new learning material
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($course_id): ?>
                        <a href="course-materials.php?course_id=<?php echo $course_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Course Materials
                        </a>
                    <?php else: ?>
                        <a href="materials.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Materials
                        </a>
                    <?php endif; ?>
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
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Material Title</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($material['title'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a title.</div>
                                </div>
                                
                                <?php if (!$course_id): ?>
                                    <div class="col-md-6">
                                        <label for="course_id" class="form-label">Course</label>
                                        <select class="form-select" id="course_id" name="course_id">
                                            <!-- <option value="">General Material</option> -->
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo $course['course_id']; ?>" 
                                                        <?php echo ($material['course_id'] ?? '') == $course['course_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course['title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <?php endif; ?>
                                
                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($material['description'] ?? ''); ?></textarea>
                                    <div class="invalid-feedback">Please enter a description.</div>
                                </div>
                                
                                <div class="col-12">
                                    <label for="content" class="form-label">Content</label>
                                    <div id="editor-container">
                                        <div id="editor"><?php echo $material['content'] ?? ''; ?></div>
                                    </div>
                                    <input type="hidden" name="content" id="content">
                                    <div class="invalid-feedback">Please enter content.</div>
                                </div>
                                
                                <div class="col-12 submit-container">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Update' : 'Create'; ?> Material
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
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
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

        // Initialize Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Enter material content...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        // Update hidden input with Quill content before form submission
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('content').value = quill.root.innerHTML;
        });
    </script>
</body>
</html> 