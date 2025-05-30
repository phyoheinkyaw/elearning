<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get resource ID if editing
$resource_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $resource_id > 0;

// Get resource data if editing
$resource = [];
if ($is_edit) {
    try {
        $stmt = $conn->prepare("SELECT * FROM resource_library WHERE resource_id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();
        
        if (!$resource) {
            $_SESSION['error_message'] = "Resource not found.";
            header('Location: resources.php');
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error fetching resource data.";
        header('Location: resources.php');
        exit();
    }
}

// Get courses for dropdown
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
    $proficiency_level = sanitize_input($_POST['proficiency_level']);
    $file_type = (int)$_POST['file_type'];
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    
    try {
        $conn->beginTransaction();
        
        // Handle file upload
        $file_path = '';
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['resource_file'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file type
            $allowed_types = [
                0 => ['pdf'],
                1 => ['epub', 'mobi'],
                2 => ['pdf', 'doc', 'docx']
            ];
            
            if (!in_array($file_extension, $allowed_types[$file_type])) {
                throw new Exception("Invalid file type for the selected resource type.");
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/resources/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename using the resource title
            $filename = uniqid() . '_' . sanitize_filename($file['name'], $title);
            $file_path = '/uploads/resources/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                throw new Exception("Error uploading file.");
            }
            
            // Delete old file if editing
            if ($is_edit && $resource['file_path']) {
                $old_file = $_SERVER['DOCUMENT_ROOT'] . $resource['file_path'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
        } elseif ($is_edit) {
            // Keep existing file if no new file uploaded
            $file_path = $resource['file_path'];
        } else {
            throw new Exception("Please upload a file.");
        }
        
        if ($is_edit) {
            // Update resource
            $stmt = $conn->prepare("
                UPDATE resource_library 
                SET title = ?, description = ?, proficiency_level = ?, 
                    file_type = ?, course_id = ?, file_path = ?
                WHERE resource_id = ?
            ");
            $stmt->execute([
                $title, $description, $proficiency_level, 
                $file_type, $course_id, $file_path, $resource_id
            ]);
            
            $_SESSION['success_message'] = "Resource updated successfully.";
        } else {
            // Insert new resource
            $stmt = $conn->prepare("
                INSERT INTO resource_library (
                    title, description, proficiency_level, 
                    file_type, course_id, file_path
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $description, $proficiency_level, 
                $file_type, $course_id, $file_path
            ]);
            
            $_SESSION['success_message'] = "Resource added successfully.";
        }
        
        $conn->commit();
        header('Location: resources.php');
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
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Resource - ELearning Admin</title>
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
                    <div>
                        <h1 class="h3 mb-2"><?php echo $is_edit ? 'Edit' : 'Add'; ?> Resource</h1>
                        <p class="text-muted mb-0">Upload and manage learning resources</p>
                    </div>
                    <a href="resources.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Resources
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
                                    <label for="title" class="form-label">Resource Title</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($resource['title'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a title.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="proficiency_level" class="form-label">Proficiency Level</label>
                                    <select class="form-select" id="proficiency_level" name="proficiency_level" required>
                                        <option value="">Select Level</option>
                                        <option value="A1" <?php echo ($resource['proficiency_level'] ?? '') === 'A1' ? 'selected' : ''; ?>>A1</option>
                                        <option value="A2" <?php echo ($resource['proficiency_level'] ?? '') === 'A2' ? 'selected' : ''; ?>>A2</option>
                                        <option value="B1" <?php echo ($resource['proficiency_level'] ?? '') === 'B1' ? 'selected' : ''; ?>>B1</option>
                                        <option value="B2" <?php echo ($resource['proficiency_level'] ?? '') === 'B2' ? 'selected' : ''; ?>>B2</option>
                                        <option value="C1" <?php echo ($resource['proficiency_level'] ?? '') === 'C1' ? 'selected' : ''; ?>>C1</option>
                                        <option value="C2" <?php echo ($resource['proficiency_level'] ?? '') === 'C2' ? 'selected' : ''; ?>>C2</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a proficiency level.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="file_type" class="form-label">Resource Type</label>
                                    <select class="form-select" id="file_type" name="file_type" required>
                                        <option value="">Select Type</option>
                                        <option value="0" <?php echo ($resource['file_type'] ?? '') === 0 ? 'selected' : ''; ?>>PDF</option>
                                        <option value="1" <?php echo ($resource['file_type'] ?? '') === 1 ? 'selected' : ''; ?>>E-book</option>
                                        <option value="2" <?php echo ($resource['file_type'] ?? '') === 2 ? 'selected' : ''; ?>>Worksheet</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a resource type.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="course_id" class="form-label">Associated Course (Optional)</label>
                                    <select class="form-select" id="course_id" name="course_id">
                                        <option value="">General Resource</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['course_id']; ?>" 
                                                    <?php echo ($resource['course_id'] ?? '') == $course['course_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($resource['description'] ?? ''); ?></textarea>
                                    <div class="invalid-feedback">Please enter a description.</div>
                                </div>
                                
                                <div class="col-12">
                                    <label for="resource_file" class="form-label">Resource File</label>
                                    <?php if ($is_edit && $resource['file_path']): ?>
                                        <div class="mb-2">
                                            <a href="<?php echo $resource['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download me-2"></i>Current File
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="resource_file" name="resource_file" 
                                           <?php echo $is_edit ? '' : 'required'; ?>>
                                    <div class="form-text">
                                        <strong>Accepted file types:</strong><br>
                                        PDF: .pdf<br>
                                        E-book: .epub, .mobi<br>
                                        Worksheet: .pdf, .doc, .docx
                                    </div>
                                    <div class="invalid-feedback">Please upload a file.</div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Update' : 'Upload'; ?> Resource
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

        // File type validation
        document.getElementById('file_type').addEventListener('change', function() {
            const fileInput = document.getElementById('resource_file');
            const fileType = this.value;
            
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const extension = file.name.split('.').pop().toLowerCase();
                
                let validExtensions = [];
                switch(fileType) {
                    case '0': // PDF
                        validExtensions = ['pdf'];
                        break;
                    case '1': // E-book
                        validExtensions = ['epub', 'mobi'];
                        break;
                    case '2': // Worksheet
                        validExtensions = ['pdf', 'doc', 'docx'];
                        break;
                }
                
                if (!validExtensions.includes(extension)) {
                    alert('Invalid file type for the selected resource type.');
                    fileInput.value = '';
                }
            }
        });
    </script>
</body>
</html> 