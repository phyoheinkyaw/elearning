<?php
session_start();
require_once 'includes/db.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get material ID from URL
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get material details and check enrollment
    $stmt = $conn->prepare("
        SELECT m.*, c.title as course_title, c.course_id,
               (SELECT COUNT(*) FROM course_enrollments 
                WHERE course_id = c.course_id AND user_id = ?) as is_enrolled,
               (SELECT progress FROM user_progress 
                WHERE material_id = m.material_id AND user_id = ?) as user_progress
        FROM course_materials m
        JOIN courses c ON m.course_id = c.course_id
        WHERE m.material_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $material_id]);
    $material = $stmt->fetch();

    if (!$material) {
        die('Material not found');
    }

    if (!$material['is_enrolled']) {
        header('Location: course-details.php?id=' . $material['course_id']);
        exit();
    }

    // Get next and previous materials
    $stmt = $conn->prepare("
        SELECT material_id, title, 
               CASE WHEN material_id = ? THEN 1 ELSE 0 END as is_current
        FROM course_materials 
        WHERE course_id = ?
        ORDER BY order_number
    ");
    $stmt->execute([$material_id, $material['course_id']]);
    $navigation = $stmt->fetchAll();

    // Find current position and get next/prev
    $current_index = array_search(1, array_column($navigation, 'is_current'));
    $prev_material = $current_index > 0 ? $navigation[$current_index - 1] : null;
    $next_material = $current_index < count($navigation) - 1 ? $navigation[$current_index + 1] : null;

} catch(PDOException $e) {
    die('An error occurred.');
}

// Handle progress update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['progress'])) {
    try {
        $progress = min(100, max(0, (int)$_POST['progress']));
        
        $stmt = $conn->prepare("
            INSERT INTO user_progress (user_id, material_id, progress, last_accessed)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE progress = ?, last_accessed = NOW()
        ");
        $stmt->execute([$_SESSION['user_id'], $material_id, $progress, $progress]);
        
        echo json_encode(['success' => true]);
        exit();
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update progress']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($material['title']); ?> - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <style>
    .material-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--white);
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        padding: 1rem 0;
        z-index: 1000;
    }

    .progress {
        height: 0.5rem;
    }

    .material-content {
        min-height: calc(100vh - 300px);
    }
    </style>
    <!-- Floating Chatbot CSS -->
    <link href="css/floating-chatbot.css" rel="stylesheet">
    <!-- Search Autocomplete CSS -->
    <link href="css/search-autocomplete.css" rel="stylesheet">
</head>

<body class="pb-5">
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="courses.php">Courses</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="course-details.php?id=<?php echo $material['course_id']; ?>">
                                <?php echo htmlspecialchars($material['course_title']); ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active">
                            <?php echo htmlspecialchars($material['title']); ?>
                        </li>
                    </ol>
                </nav>

                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4">
                        <h1 class="h2 mb-4"><?php echo htmlspecialchars($material['title']); ?></h1>

                        <div class="progress mb-4">
                            <div class="progress-bar" role="progressbar"
                                style="width: <?php echo $material['user_progress'] ?? 0; ?>%"
                                aria-valuenow="<?php echo $material['user_progress'] ?? 0; ?>" aria-valuemin="0"
                                aria-valuemax="100">
                                <?php echo $material['user_progress'] ?? 0; ?>%
                            </div>
                        </div>

                        <div class="material-content mb-4">
                            <?php echo $material['content']; ?>
                        </div>

                        <div class="d-flex justify-content-center">
                            <button type="button" class="btn btn-success btn-lg" onclick="markComplete()">
                                <i class="fas fa-check-circle me-2"></i>Mark as Complete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm position-sticky" style="top: 9rem; z-index: 1;">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Course Materials</h4>
                        <div class="list-group list-group-flush">
                            <?php foreach ($navigation as $nav_item): ?>
                            <a href="material-details.php?id=<?php echo $nav_item['material_id']; ?>"
                                class="list-group-item list-group-item-action <?php echo $nav_item['is_current'] ? 'active' : ''; ?>">
                                <?php if ($nav_item['is_current']): ?>
                                <i class="fas fa-play me-2"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($nav_item['title']); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="material-nav">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <?php if ($prev_material): ?>
                    <a href="?id=<?php echo $prev_material['material_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i><?php echo htmlspecialchars($prev_material['title']); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="col text-end">
                    <?php if ($next_material): ?>
                    <a href="?id=<?php echo $next_material['material_id']; ?>" class="btn btn-primary">
                        <?php echo htmlspecialchars($next_material['title']); ?><i class="fas fa-arrow-right ms-2"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Initialize progress from PHP value
    let currentProgress = <?php echo $material['user_progress'] ?? 0; ?>;

    function updateProgress(progress) {
        // Ensure we don't decrease progress that was already higher
        if (progress < currentProgress && progress < 100) {
            return;
        }

        currentProgress = progress;

        fetch('material-details.php?id=<?php echo $material_id; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `progress=${progress}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update progress bar
                    const progressBar = document.querySelector('.progress-bar');
                    progressBar.style.width = `${progress}%`;
                    progressBar.setAttribute('aria-valuenow', progress);
                    progressBar.textContent = `${progress}%`;

                    // Update button text if 100% complete
                    if (progress >= 100) {
                        const completeBtn = document.querySelector('.btn-success');
                        if (completeBtn) {
                            completeBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Completed!';
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function markComplete() {
        // Always set to 100% when button is clicked, regardless of scroll position
        updateProgress(100);

        // If there's a next material, navigate to it
        <?php if ($next_material): ?>
        setTimeout(() => {
            window.location.href = `?id=<?php echo $next_material['material_id']; ?>`;
        }, 500);
        <?php endif; ?>
    }

    // Auto-save progress as user scrolls
    let lastScrollPosition = 0;
    window.addEventListener('scroll', () => {
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPosition = window.scrollY;
        const progress = Math.round((scrollPosition / docHeight) * 100);

        // Only update if progress has changed significantly (by 5% or more)
        if (Math.abs(progress - lastScrollPosition) >= 5) {
            lastScrollPosition = progress;
            // Max 90% for scrolling, 100% requires clicking button
            // Don't decrease progress if it's already higher
            const newProgress = Math.min(90, progress);
            if (newProgress > currentProgress || currentProgress >= 100) {
                updateProgress(newProgress);
            }
        }
    });

    // Add a floating complete button for better visibility
    // document.addEventListener('DOMContentLoaded', function() {
    //     const floatingBtn = document.createElement('button');
    //     floatingBtn.className = 'btn btn-success position-fixed';
    //     floatingBtn.style.bottom = '80px';
    //     floatingBtn.style.right = '20px';
    //     floatingBtn.style.zIndex = '1001';
    //     floatingBtn.style.borderRadius = '50%';
    //     floatingBtn.style.width = '60px';
    //     floatingBtn.style.height = '60px';
    //     floatingBtn.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
    //     floatingBtn.innerHTML = '<i class="fas fa-check"></i>';
    //     floatingBtn.title = 'Mark as Complete';
    //     floatingBtn.onclick = markComplete;

    //     // Only show floating button if not already 100% complete
    //     if (currentProgress < 100) {
    //         document.body.appendChild(floatingBtn);
    //     }
    // });
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