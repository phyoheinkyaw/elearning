<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/profiles';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Update user table
        $stmt = $conn->prepare("
            UPDATE users 
            SET email = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([
            $_POST['email'],
            $_SESSION['user_id']
        ]);

        // Handle password update if provided
        if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch();
            
            if (password_verify($_POST['current_password'], $user_data['password'])) {
                // Update password
                $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
                $_SESSION['success_message'] = 'Profile and password updated successfully!';
            } else {
                throw new Exception('Current password is incorrect');
            }
        }

        // Update user_profiles table
        $stmt = $conn->prepare("
            INSERT INTO user_profiles (user_id, full_name) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE full_name = ?
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['full_name'],
            $_POST['full_name']
        ]);

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_picture']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                // Get current profile picture to delete later
                $stmt = $conn->prepare("SELECT profile_picture FROM user_profiles WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $current_profile = $stmt->fetch();

                $file_name = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $upload_path = $upload_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    $stmt = $conn->prepare("
                        UPDATE user_profiles 
                        SET profile_picture = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$upload_path, $_SESSION['user_id']]);

                    // Delete old profile picture if exists
                    if (!empty($current_profile['profile_picture']) && file_exists($current_profile['profile_picture'])) {
                        unlink($current_profile['profile_picture']);
                    }
                }
            }
        }

        $conn->commit();
        if (!isset($_SESSION['success_message'])) {
            $_SESSION['success_message'] = 'Profile updated successfully!';
        }
    } catch(Exception $e) {
        $conn->rollBack();
        error_log('Profile update error: ' . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage() === 'Current password is incorrect' 
            ? 'Current password is incorrect' 
            : 'Failed to update profile. Please try again.';
    }
    
    header('Location: profile.php');
    exit();
}

// Get user data
try {
    // Get user profile data with proper joins and field selection
    $stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            u.created_at,
            COALESCE(up.full_name, '') as full_name,
            COALESCE(up.profile_picture, '') as profile_picture,
            COALESCE(
                (SELECT assigned_level 
                 FROM level_test_results 
                 WHERE user_id = u.user_id 
                 ORDER BY test_date DESC 
                 LIMIT 1),
                'Not tested'
            ) as proficiency_level,
            (SELECT test_date 
             FROM level_test_results 
             WHERE user_id = u.user_id 
             ORDER BY test_date DESC 
             LIMIT 1) as last_test_date
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User ID ' . $_SESSION['user_id'] . ' not found in database');
    }

    // Update the user's proficiency level in user_profiles if it's different
    if ($user['proficiency_level'] !== 'Not tested') {
        try {
            $stmt = $conn->prepare("
                INSERT INTO user_profiles (user_id, proficiency_level)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE proficiency_level = ?
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $user['proficiency_level'],
                $user['proficiency_level']
            ]);
        } catch (PDOException $e) {
            error_log('Failed to update proficiency level: ' . $e->getMessage());
            // Continue execution as this is not a critical error
        }
    }

    // Get enrolled courses with progress
    try {
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                ce.enrolled_at,
                COUNT(DISTINCT cm.material_id) as total_materials,
                COUNT(DISTINCT CASE WHEN up.progress = 100 THEN up.material_id END) as completed_materials
            FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.course_id
            LEFT JOIN course_materials cm ON c.course_id = cm.course_id
            LEFT JOIN user_progress up ON cm.material_id = up.material_id AND up.user_id = ?
            WHERE ce.user_id = ?
            GROUP BY c.course_id, ce.enrolled_at
            ORDER BY ce.enrolled_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $enrolled_courses = $stmt->fetchAll();
    } catch (PDOException $e) {
        $enrolled_courses = [];
        if ($e->getCode() == '42S02') {
            $_SESSION['warning_message'] = 'Database schema error: One or more required tables are missing.';
        } else if ($e->getCode() == '42S22') {
            $_SESSION['warning_message'] = 'Database schema error: One or more required columns are missing.';
        } else {
            $_SESSION['warning_message'] = 'Unable to load enrolled courses. Please try again later.';
        }
    }

    // Get test history with pagination
    try {
        // Get total count of test results
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM level_test_results
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $total_results = $stmt->fetch()['total'];
        
        // Calculate pagination
        $results_per_page = 5;
        $total_pages = ceil($total_results / $results_per_page);
        $current_page = isset($_GET['page']) ? max(1, min($total_pages, intval($_GET['page']))) : 1;
        $offset = ($current_page - 1) * $results_per_page;

        // Get paginated results (will be used for both table and chart)
        $stmt = $conn->prepare("
            SELECT 
                result_id,
                score,
                assigned_level,
                test_date
            FROM level_test_results
            WHERE user_id = ?
            ORDER BY test_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$_SESSION['user_id'], $results_per_page, $offset]);
        $test_history = $stmt->fetchAll();

    } catch (PDOException $e) {
        $test_history = [];
        $total_pages = 0;
        $current_page = 1;
        $_SESSION['warning_message'] = 'Unable to load test history. Please try again later.';
    }

} catch(Exception $e) {
    $_SESSION['error_message'] = 'An error occurred while loading your profile. Please try again.';
    
    // Set default values for the user array
    $user = [
        'user_id' => $_SESSION['user_id'],
        'username' => '',
        'email' => '',
        'full_name' => '',
        'profile_picture' => '',
        'proficiency_level' => 'Not tested',
        'created_at' => date('Y-m-d H:i:s'),
        'last_test_date' => null
    ];
    $enrolled_courses = [];
    $test_history = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .profile-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        .profile-header .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-picture-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            display: none;
        }
        .progress {
            height: 0.5rem;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .course-card {
            transition: all 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .profile-info {
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .profile-info .lead {
            color: rgba(255, 255, 255, 0.9);
        }
        .profile-stats {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            margin-right: 1rem;
        }
        .test-history-chart {
            min-height: 300px;
            position: relative;
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?php echo h($user['profile_picture']); ?>" 
                             alt="Profile Picture" 
                             class="profile-picture rounded-circle">
                    <?php else: ?>
                        <div class="profile-picture rounded-circle bg-white d-flex align-items-center justify-content-center">
                            <i class="fas fa-user fa-4x text-primary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col profile-info">
                    <h1 class="display-4 mb-0"><?php echo h($user['full_name'] ?: 'Update Your Profile'); ?></h1>
                    <p class="lead mb-3"><?php echo h($user['email'] ?: 'Add your email'); ?></p>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="profile-stats">
                            <i class="fas fa-signal me-2"></i>
                            Level: <?php echo h($user['proficiency_level']); ?>
                        </div>
                        <?php if (!empty($user['last_test_date'])): ?>
                            <div class="profile-stats">
                                <i class="fas fa-calendar me-2"></i>
                                Last Test: <?php echo date('M d, Y', strtotime($user['last_test_date'])); ?>
                            </div>
                        <?php endif; ?>
                        <div class="profile-stats">
                            <i class="fas fa-clock me-2"></i>
                            Member since: <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['warning_message'];
                unset($_SESSION['warning_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- My Courses -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">My Courses</h4>
                            <a href="my-courses.php" class="btn btn-outline-primary btn-sm">View All</a>
                        </div>

                        <?php if (empty($enrolled_courses)): ?>
                            <p class="text-muted">You haven't enrolled in any courses yet.</p>
                            <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <div class="col-md-6">
                                        <div class="card h-100 course-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($course['level']); ?></span>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($course['difficulty_level']); ?></span>
                                                </div>
                                                <h5 class="card-title mb-3"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                <?php
                                                $progress = $course['total_materials'] > 0 
                                                    ? round(($course['completed_materials'] / $course['total_materials']) * 100) 
                                                    : 0;
                                                ?>
                                                <div class="progress mb-2">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $progress; ?>%" 
                                                         aria-valuenow="<?php echo $progress; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $course['completed_materials']; ?> of <?php echo $course['total_materials']; ?> materials completed
                                                </small>
                                                <div class="mt-3">
                                                    <a href="course-details.php?id=<?php echo $course['course_id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        Continue Learning
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

                <!-- Test History -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">Level Test History</h4>
                            <a href="level-test.php" class="btn btn-outline-primary btn-sm">Take Another Test</a>
                        </div>
                        <?php if (empty($test_history)): ?>
                            <p class="text-muted">You haven't taken any level tests yet.</p>
                            <a href="level-test.php" class="btn btn-primary">Take Level Test</a>
                        <?php else: ?>
                            <div class="test-history-chart mb-4">
                                <canvas id="testHistoryChart"></canvas>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Score</th>
                                            <th>Level</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($test_history as $test): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y h:i A', strtotime($test['test_date'])); ?></td>
                                                <td><?php echo $test['score']; ?>%</td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($test['assigned_level']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Test history pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Profile Settings -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Profile Settings</h4>
                        <form action="profile.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Profile Picture</label>
                                <div class="d-flex flex-column align-items-center gap-3">
                                    <img id="profilePreview" 
                                         src="<?php echo !empty($user['profile_picture']) ? h($user['profile_picture']) : 'assets/images/default-profile.jpg'; ?>" 
                                         class="profile-picture-preview rounded-circle">
                                    <input type="file" 
                                           class="form-control" 
                                           name="profile_picture" 
                                           accept="image/*"
                                           onchange="previewImage(this)">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="full_name" 
                                       value="<?php echo h($user['full_name']); ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       class="form-control" 
                                       name="email" 
                                       value="<?php echo h($user['email']); ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Current Level</label>
                                <input type="text" 
                                       class="form-control" 
                                       value="<?php echo h($user['proficiency_level']); ?>" 
                                       readonly>
                                <div class="form-text">
                                    <?php if ($user['proficiency_level'] === 'Not tested'): ?>
                                        Take a level test to determine your proficiency level.
                                    <?php else: ?>
                                        Take another level test to update your proficiency level.
                                    <?php endif; ?>
                                    <a href="level-test.php">Take Test</a>
                                </div>
                            </div>

                            <div class="card mt-4 mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Change Password</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" 
                                               class="form-control" 
                                               name="current_password">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" 
                                               class="form-control" 
                                               name="new_password"
                                               pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                               title="Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters">
                                    </div>
                                    <div class="form-text mb-3">
                                        Leave password fields empty if you don't want to change it.
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function previewImage(input) {
            const preview = document.getElementById('profilePreview');
            preview.style.display = 'block';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Show profile picture preview on page load if exists
        document.addEventListener('DOMContentLoaded', function() {
            const preview = document.getElementById('profilePreview');
            if (preview.src) {
                preview.style.display = 'block';
            }
        });

        <?php if (!empty($test_history)): ?>
        // Test History Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('testHistoryChart').getContext('2d');
            
            // Prepare data
            const testData = <?php 
                $chartData = array_reverse($test_history); // Reverse to show oldest to newest
                echo json_encode([
                    'labels' => array_map(function($test) { 
                        return date('M d, h:i A', strtotime($test['test_date'])); 
                    }, $chartData),
                    'scores' => array_map(function($test) { 
                        return $test['score']; 
                    }, $chartData),
                    'levels' => array_map(function($test) { 
                        return $test['assigned_level']; 
                    }, $chartData)
                ]); 
            ?>;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: testData.labels,
                    datasets: [{
                        label: 'Test Score (%)',
                        data: testData.scores,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#0d6efd',
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    return [
                                        'Level: ' + testData.levels[context.dataIndex],
                                        'Page: ' + <?php echo $current_page; ?> + ' of ' + <?php echo $total_pages; ?>
                                    ];
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Test Scores (Page ' + <?php echo $current_page; ?> + ' of ' + <?php echo $total_pages; ?> + ')'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Score (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Test Date'
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>
    </script>
</body>

</html> 