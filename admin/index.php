<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get total users
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_users = $stmt->fetch()['total'];

// Get total courses
$stmt = $conn->query("SELECT COUNT(*) as total FROM courses");
$total_courses = $stmt->fetch()['total'];

// Get total enrollments
$stmt = $conn->query("SELECT COUNT(*) as total FROM course_enrollments");
$total_enrollments = $stmt->fetch()['total'];

// Get recent users with last login
$stmt = $conn->query("
    SELECT u.*, p.proficiency_level 
    FROM users u 
    LEFT JOIN user_profiles p ON u.user_id = p.user_id 
    WHERE u.role != 'admin' 
    ORDER BY u.last_login DESC 
    LIMIT 5
");
$recent_users = $stmt->fetchAll();

// Get recent courses
$stmt = $conn->query("SELECT * FROM courses ORDER BY created_at DESC LIMIT 5");
$recent_courses = $stmt->fetchAll();

// Get login activity for the last 7 days
$stmt = $conn->query("
    SELECT DATE(last_login) as login_date, COUNT(*) as login_count 
    FROM users 
    WHERE last_login >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) 
    GROUP BY DATE(last_login) 
    ORDER BY login_date
");
$login_activity = $stmt->fetchAll();

// Get enrollment trends by month
$stmt = $conn->query("
    SELECT DATE_FORMAT(enrolled_at, '%Y-%m') as month, COUNT(*) as enrollment_count 
    FROM course_enrollments 
    WHERE enrolled_at >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH) 
    GROUP BY DATE_FORMAT(enrolled_at, '%Y-%m') 
    ORDER BY month
");
$enrollment_trends = $stmt->fetchAll();

// Get level test results distribution
$stmt = $conn->query("
    SELECT assigned_level, COUNT(*) as count 
    FROM level_test_results 
    GROUP BY assigned_level 
    ORDER BY FIELD(assigned_level, 'A1', 'A2', 'B1', 'B2', 'C1', 'C2')
");
$level_distribution = $stmt->fetchAll();

// Get recent level test results
$stmt = $conn->query("
    SELECT r.*, u.username 
    FROM level_test_results r 
    JOIN users u ON r.user_id = u.user_id 
    ORDER BY r.test_date DESC 
    LIMIT 5
");
$recent_level_tests = $stmt->fetchAll();

// Get user roles distribution
$stmt = $conn->query("
    SELECT 
        CASE 
            WHEN role = 1 THEN 'Student'
            WHEN role = 2 THEN 'Instructor'
            ELSE 'Admin'
        END as role_name,
        COUNT(*) as count
    FROM users 
    GROUP BY role
");
$role_distribution = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="admin-dashboard">
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <h1 class="mb-4">Dashboard</h1>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Users</h6>
                                        <h2 class="card-title mb-0"><?php echo $total_users; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Courses</h6>
                                        <h2 class="card-title mb-0"><?php echo $total_courses; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Enrollments</h6>
                                        <h2 class="card-title mb-0"><?php echo $total_enrollments; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Active Users Today</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $stmt = $conn->query("
                                                SELECT COUNT(DISTINCT user_id) as active 
                                                FROM users 
                                                WHERE DATE(last_login) = CURRENT_DATE
                                            ");
                                            echo $stmt->fetch()['active'];
                                            ?>
                                        </h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-user-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Login Activity (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="loginChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Enrollment Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="enrollmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Distribution Charts -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Level Test Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="levelChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">User Roles Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="roleChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Tables -->
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Users</h5>
                                <a href="users.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Level</th>
                                                <th>Last Login</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['proficiency_level'] ?? 'N/A'); ?></td>
                                                <td><?php echo $user['last_login'] ? date('M d, H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Courses</h5>
                                <a href="courses.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Level</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_courses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                                <td><?php echo htmlspecialchars($course['level']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Level Tests</h5>
                                <a href="level-test-results.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Level</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_level_tests as $test): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($test['username']); ?></td>
                                                <td><?php echo htmlspecialchars($test['assigned_level']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($test['test_date'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Login Activity Chart
        new Chart(document.getElementById('loginChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($login_activity, 'login_date')); ?>,
                datasets: [{
                    label: 'Daily Logins',
                    data: <?php echo json_encode(array_column($login_activity, 'login_count')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Enrollment Trends Chart
        new Chart(document.getElementById('enrollmentChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($enrollment_trends, 'month')); ?>,
                datasets: [{
                    label: 'Monthly Enrollments',
                    data: <?php echo json_encode(array_column($enrollment_trends, 'enrollment_count')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Level Distribution Chart
        new Chart(document.getElementById('levelChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($level_distribution, 'assigned_level')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($level_distribution, 'count')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });

        // Role Distribution Chart
        new Chart(document.getElementById('roleChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($role_distribution, 'role_name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($role_distribution, 'count')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
</body>

</html> 