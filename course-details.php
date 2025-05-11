<?php
session_start();
require_once 'includes/db.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get course details
    $stmt = $conn->prepare("
        SELECT c.*, u.username as instructor_name, 
               COUNT(DISTINCT e.user_id) as enrolled_students,
               COUNT(DISTINCT m.material_id) as total_materials,
               (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.course_id AND user_id = ?) as is_enrolled
        FROM courses c
        LEFT JOIN users u ON c.instructor_id = u.user_id
        LEFT JOIN course_enrollments e ON c.course_id = e.course_id
        LEFT JOIN course_materials m ON c.course_id = m.course_id
        WHERE c.course_id = ?
        GROUP BY c.course_id
    ");
    $stmt->execute([$_SESSION['user_id'], $course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        die('Course not found');
    }

    // Get course materials
    $stmt = $conn->prepare("
        SELECT m.*, 
               (SELECT progress FROM user_progress WHERE user_id = ? AND material_id = m.material_id) as user_progress
        FROM course_materials m
        WHERE m.course_id = ?
        ORDER BY m.order_number
    ");
    $stmt->execute([$_SESSION['user_id'], $course_id]);
    $materials = $stmt->fetchAll();
    
    // Get course resources
    $stmt = $conn->prepare("
        SELECT r.* 
        FROM resource_library r
        WHERE r.course_id = ?
        ORDER BY r.proficiency_level ASC, r.title ASC
    ");
    $stmt->execute([$course_id]);
    $resources = $stmt->fetchAll();
    
    // Get resource types for display
    $resource_types = [0 => 'PDF', 1 => 'E-book', 2 => 'Worksheet'];

} catch(PDOException $e) {
    die('An error occurred.');
}

// Calculate course completion and find first incomplete material
$all_materials_complete = true;
$first_incomplete_material_id = null;

if (!empty($materials)) {
    foreach ($materials as $material) {
        $progress = isset($material['user_progress']) ? (int)$material['user_progress'] : 0;
        
        if ($progress < 100) {
            $all_materials_complete = false;
            if ($first_incomplete_material_id === null) {
                $first_incomplete_material_id = $material['material_id'];
            }
            break;
        }
    }
}

// If all materials are complete or no incomplete material found, use the first material
if ($first_incomplete_material_id === null && !empty($materials)) {
    $first_incomplete_material_id = $materials[0]['material_id'];
}

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    try {
        // Check if already enrolled
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM course_enrollments WHERE user_id = ? AND course_id = ?");
        $check_stmt->execute([$_SESSION['user_id'], $course_id]);
        if ($check_stmt->fetchColumn() > 0) {
            $error = 'You are already enrolled in this course.';
        } else {
            $stmt = $conn->prepare("INSERT INTO course_enrollments (user_id, course_id, enrolled_at) VALUES (?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $course_id]);
            header("Location: course-details.php?id=" . $course_id . "&enrolled=1");
            exit();
        }
    } catch(PDOException $e) {
        $error = 'Failed to enroll in the course. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Search Autocomplete CSS -->
    <link href="css/search-autocomplete.css" rel="stylesheet">
    <!-- Floating Chatbot CSS -->
    <link href="css/floating-chatbot.css" rel="stylesheet">
    <style>
    .course-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        padding: 3rem 0;
        margin-bottom: 2rem;
    }

    .material-card {
        transition: all 0.3s ease;
    }

    .material-card:hover {
        transform: translateY(-5px);
    }

    .progress {
        height: 0.5rem;
    }

    .course-sidebar {
        position: sticky;
        top: 80px;
        /* Adjust based on your navbar height */
        z-index: 100;
        margin-bottom: 2rem;
    }

    @media (max-width: 991.98px) {
        .course-sidebar {
            position: static;
            margin-top: 2rem;
        }
    }

    .course-info-list li {
        display: flex;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .course-info-list li:last-child {
        border-bottom: none;
    }

    .course-info-list i {
        width: 24px;
        text-align: center;
        margin-right: 1rem;
    }
    </style>
    <!-- Floating Chatbot CSS -->
</head>

<body class="bg-light">
    <?php include 'includes/nav.php'; ?>

    <div class="course-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="lead mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                    <div class="d-flex flex-wrap align-items-center gap-4">
                        <div>
                            <i class="fas fa-user-tie me-2"></i>
                            Instructor: <?php echo htmlspecialchars($course['instructor_name']); ?>
                        </div>
                        <div>
                            <i class="fas fa-users me-2"></i>
                            <?php echo $course['enrolled_students']; ?> students
                        </div>
                        <div>
                            <i class="fas fa-book me-2"></i>
                            <?php echo $course['total_materials']; ?> materials
                        </div>
                        <div>
                            <i class="fas fa-signal me-2"></i>
                            Level: <?php echo htmlspecialchars($course['difficulty_level']); ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!$course['is_enrolled']): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="enroll" class="btn btn-light btn-lg px-5">
                            <i class="fas fa-graduation-cap me-2"></i>Enroll Now
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-success btn-lg px-5" disabled>
                        <i class="fas fa-check-circle me-2"></i>Enrolled
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Course Overview</h4>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Course Materials</h4>
                        <?php if (empty($materials)): ?>
                        <p class="text-muted">No materials available yet.</p>
                        <?php else: ?>
                        <div class="materials-list">
                            <?php foreach ($materials as $material): ?>
                            <div class="card material-card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="me-3">
                                            <h5 class="card-title mb-2">
                                                <?php echo htmlspecialchars($material['title']); ?>
                                            </h5>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($material['description']); ?>
                                            </p>
                                        </div>
                                        <?php if ($course['is_enrolled']): ?>
                                        <a href="material-details.php?id=<?php echo $material['material_id']; ?>"
                                            class="btn btn-primary">
                                            <i class="fas fa-book-reader me-2"></i>Start Learning
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($course['is_enrolled'] && isset($material['user_progress'])): ?>
                                    <div class="progress mt-3">
                                        <div class="progress-bar" role="progressbar"
                                            style="width: <?php echo $material['user_progress']; ?>%"
                                            aria-valuenow="<?php echo $material['user_progress']; ?>" aria-valuemin="0"
                                            aria-valuemax="100">
                                            <?php echo $material['user_progress']; ?>%
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Resources Section -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">Course Resources</h4>
                            <?php if (!empty($resources)): ?>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                    id="resourceFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    Filter by Type
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="resourceFilterDropdown">
                                    <li><a class="dropdown-item" href="#" data-resource-filter="all">All Types</a></li>
                                    <?php foreach ($resource_types as $type_id => $type_name): ?>
                                    <li><a class="dropdown-item" href="#"
                                            data-resource-filter="<?php echo $type_id; ?>"><?php echo $type_name; ?></a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($resources)): ?>
                        <p class="text-muted">No resources available for this course.</p>
                        <?php else: ?>
                        <div class="row g-3" id="resourcesContainer">
                            <?php foreach ($resources as $resource): ?>
                            <div class="col-md-6 resource-item"
                                data-resource-type="<?php echo $resource['file_type']; ?>">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span
                                                class="badge bg-primary"><?php echo $resource['proficiency_level']; ?></span>
                                            <span
                                                class="badge bg-info"><?php echo $resource_types[$resource['file_type']]; ?></span>
                                        </div>
                                        <h5 class="card-title h6 mb-2">
                                            <?php echo htmlspecialchars($resource['title']); ?></h5>
                                        <p class="card-text small text-muted mb-3">
                                            <?php echo htmlspecialchars(substr($resource['description'], 0, 80)) . (strlen($resource['description']) > 80 ? '...' : ''); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M d, Y', strtotime($resource['upload_date'])); ?>
                                            </small>
                                            <a href="<?php echo htmlspecialchars($resource['file_path']); ?>"
                                                class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-download me-1"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="course-sidebar">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h4 class="card-title mb-4">Course Information</h4>
                            <ul class="list-unstyled course-info-list mb-4">
                                <li>
                                    <i class="fas fa-clock text-primary"></i>
                                    <span>Duration: <?php echo htmlspecialchars($course['duration']); ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-signal text-primary"></i>
                                    <span>Level: <?php echo htmlspecialchars($course['difficulty_level']); ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-users text-primary"></i>
                                    <span>Students: <?php echo $course['enrolled_students']; ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-book text-primary"></i>
                                    <span>Materials: <?php echo $course['total_materials']; ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-calendar-alt text-primary"></i>
                                    <span>Last Updated:
                                        <?php echo date('F j, Y', strtotime($course['updated_at'])); ?></span>
                                </li>
                            </ul>

                            <?php if (!$course['is_enrolled']): ?>
                            <form method="POST">
                                <button type="submit" name="enroll" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-graduation-cap me-2"></i>Enroll Now
                                </button>
                            </form>
                            <?php else: ?>
                            <a href="material-details.php?id=<?php echo $first_incomplete_material_id ?? $materials[0]['material_id'] ?? 0; ?>"
                                class="btn btn-success btn-lg w-100">
                                <i
                                    class="fas fa-book-reader me-2"></i><?php echo $all_materials_complete ? 'Start Revision' : 'Continue Learning'; ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Resource filtering script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Resource filtering
        const filterLinks = document.querySelectorAll('[data-resource-filter]');
        const resourceItems = document.querySelectorAll('.resource-item');

        filterLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const filterValue = this.getAttribute('data-resource-filter');

                resourceItems.forEach(item => {
                    if (filterValue === 'all' || item.getAttribute(
                            'data-resource-type') === filterValue) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Update dropdown button text
                document.getElementById('resourceFilterDropdown').textContent =
                    filterValue === 'all' ? 'Filter by Type' : 'Type: ' + this.textContent
                    .trim();
            });
        });
    });
    </script>
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html>