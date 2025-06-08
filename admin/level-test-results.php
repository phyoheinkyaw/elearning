<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Set default filter values
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$username_filter = isset($_GET['username']) ? $_GET['username'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 15;
$offset = ($page - 1) * $results_per_page;

// Build the query with filters
$sql = "
    SELECT r.*, u.username, u.email 
    FROM level_test_results r 
    JOIN users u ON r.user_id = u.user_id 
    WHERE 1=1
";

$params = [];

if (!empty($level_filter)) {
    $sql .= " AND r.assigned_level = ?";
    $params[] = $level_filter;
}

if (!empty($username_filter)) {
    $sql .= " AND u.username LIKE ?";
    $params[] = "%$username_filter%";
}

if (!empty($date_from)) {
    $sql .= " AND r.test_date >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $sql .= " AND r.test_date <= ?";
    $params[] = $date_to . ' 23:59:59';
}

// Count total results for pagination
$count_stmt = $conn->prepare(str_replace("r.*, u.username, u.email", "COUNT(*) as total", $sql));
if (!empty($params)) {
    $count_stmt->execute($params);
} else {
    $count_stmt->execute();
}
$total_results = $count_stmt->fetch()['total'];
$total_pages = ceil($total_results / $results_per_page);

// Add sorting and pagination to the main query
$sql .= " ORDER BY r.test_date DESC LIMIT $offset, $results_per_page";

// Execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$test_results = $stmt->fetchAll();

// Get available proficiency levels for filter dropdown
$levels_stmt = $conn->query("SELECT DISTINCT assigned_level FROM level_test_results ORDER BY FIELD(assigned_level, 'A1', 'A2', 'B1', 'B2', 'C1', 'C2')");
$available_levels = $levels_stmt->fetchAll();

// Get stats by level
$level_stats_stmt = $conn->query("
    SELECT assigned_level, COUNT(*) as count, AVG(score) as avg_score 
    FROM level_test_results 
    GROUP BY assigned_level 
    ORDER BY FIELD(assigned_level, 'A1', 'A2', 'B1', 'B2', 'C1', 'C2')
");
$level_stats = $level_stats_stmt->fetchAll();

// Get recent activity
$recent_activity_stmt = $conn->query("
    SELECT DATE(test_date) as test_day, COUNT(*) as count 
    FROM level_test_results 
    WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) 
    GROUP BY DATE(test_date) 
    ORDER BY test_day DESC
");
$recent_activity = $recent_activity_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level Test Results - Admin Dashboard</title>
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
    <!-- Flatpickr for date picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body class="admin-dashboard">
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <h1 class="mb-4">Level Test Results</h1>
                
                <div class="row g-4 mb-4">
                    <!-- Stats Cards -->
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Tests Taken</h6>
                                        <h2 class="card-title mb-0"><?php echo $total_results; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-clipboard-check"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Tests This Month</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $stmt = $conn->query("
                                                SELECT COUNT(*) as count 
                                                FROM level_test_results 
                                                WHERE MONTH(test_date) = MONTH(CURRENT_DATE) 
                                                AND YEAR(test_date) = YEAR(CURRENT_DATE)
                                            ");
                                            echo $stmt->fetch()['count'];
                                            ?>
                                        </h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-check"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Avg. Score</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $stmt = $conn->query("SELECT AVG(score) as avg FROM level_test_results");
                                            echo round($stmt->fetch()['avg'], 1);
                                            ?>
                                        </h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-star"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Most Common Level</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $stmt = $conn->query("
                                                SELECT assigned_level, COUNT(*) as count 
                                                FROM level_test_results 
                                                GROUP BY assigned_level 
                                                ORDER BY count DESC 
                                                LIMIT 1
                                            ");
                                            echo $stmt->fetch()['assigned_level'];
                                            ?>
                                        </h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-award"></i>
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
                                <h5 class="mb-0">Level Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="levelChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Test Activity (30 days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="activityChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filter Results</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username_filter); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="level" class="form-label">Proficiency Level</label>
                                <select class="form-select" id="level" name="level">
                                    <option value="">All Levels</option>
                                    <?php foreach ($available_levels as $level): ?>
                                    <option value="<?php echo $level['assigned_level']; ?>" <?php echo $level_filter == $level['assigned_level'] ? 'selected' : ''; ?>>
                                        <?php echo $level['assigned_level']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="text" class="form-control datepicker" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="text" class="form-control datepicker" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Results Table -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Test Results</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($test_results) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Score</th>
                                            <th>Assigned Level</th>
                                            <th>Test Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($test_results as $index => $result): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($result['username']); ?></td>
                                            <td><?php echo htmlspecialchars($result['email']); ?></td>
                                            <td><?php echo $result['score']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($result['assigned_level']) {
                                                        case 'A1': echo 'secondary'; break;
                                                        case 'A2': echo 'info'; break;
                                                        case 'B1': echo 'primary'; break;
                                                        case 'B2': echo 'success'; break;
                                                        case 'C1': echo 'warning'; break;
                                                        case 'C2': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo $result['assigned_level']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($result['test_date'])); ?></td>
                                            <td>
                                                <a href="view-test-details.php?id=<?php echo $result['result_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo (!empty($level_filter) ? "&level=$level_filter" : "") . (!empty($username_filter) ? "&username=$username_filter" : "") . (!empty($date_from) ? "&date_from=$date_from" : "") . (!empty($date_to) ? "&date_to=$date_to" : ""); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, min($page - 2, $total_pages - 4));
                                    $end_page = min($total_pages, max(5, $page + 2));
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($level_filter) ? "&level=$level_filter" : "") . (!empty($username_filter) ? "&username=$username_filter" : "") . (!empty($date_from) ? "&date_from=$date_from" : "") . (!empty($date_to) ? "&date_to=$date_to" : ""); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo (!empty($level_filter) ? "&level=$level_filter" : "") . (!empty($username_filter) ? "&username=$username_filter" : "") . (!empty($date_from) ? "&date_from=$date_from" : "") . (!empty($date_to) ? "&date_to=$date_to" : ""); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> No level test results found matching the criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
            
            // Level Distribution Chart
            new Chart(document.getElementById('levelChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($level_stats, 'assigned_level')); ?>,
                    datasets: [{
                        label: 'Number of Students',
                        data: <?php echo json_encode(array_column($level_stats, 'count')); ?>,
                        backgroundColor: [
                            'rgba(108, 117, 125, 0.7)', // A1 - Secondary
                            'rgba(13, 202, 240, 0.7)',  // A2 - Info
                            'rgba(13, 110, 253, 0.7)',  // B1 - Primary
                            'rgba(25, 135, 84, 0.7)',   // B2 - Success
                            'rgba(255, 193, 7, 0.7)',   // C1 - Warning
                            'rgba(220, 53, 69, 0.7)'    // C2 - Danger
                        ],
                        borderColor: [
                            'rgba(108, 117, 125, 1)',
                            'rgba(13, 202, 240, 1)',
                            'rgba(13, 110, 253, 1)',
                            'rgba(25, 135, 84, 1)',
                            'rgba(255, 193, 7, 1)',
                            'rgba(220, 53, 69, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Recent Activity Chart
            const activityData = <?php echo json_encode($recent_activity); ?>;
            const activityLabels = activityData.map(item => item.test_day);
            const activityCounts = activityData.map(item => item.count);
            
            new Chart(document.getElementById('activityChart'), {
                type: 'line',
                data: {
                    labels: activityLabels,
                    datasets: [{
                        label: 'Tests Taken',
                        data: activityCounts,
                        fill: false,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>

</html> 