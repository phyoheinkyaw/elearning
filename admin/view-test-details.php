<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check if result ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No test result specified.";
    header('Location: level-test-results.php');
    exit();
}

$result_id = (int) $_GET['id'];

// Get test result details - removed user personal info
$stmt = $conn->prepare("
    SELECT r.*, u.user_id 
    FROM level_test_results r 
    JOIN users u ON r.user_id = u.user_id 
    WHERE r.result_id = ?
");
$stmt->execute([$result_id]);
$result = $stmt->fetch();

if (!$result) {
    $_SESSION['error_message'] = "Test result not found.";
    header('Location: level-test-results.php');
    exit();
}

// Get user's other test results
$stmt = $conn->prepare("
    SELECT result_id, score, assigned_level, test_date 
    FROM level_test_results
    WHERE user_id = ? AND result_id != ?
    ORDER BY test_date DESC
    LIMIT 5
");
$stmt->execute([$result['user_id'], $result_id]);
$other_tests = $stmt->fetchAll();

// Calculate pass percentage based on 25 questions
$pass_percentage = round(($result['score'] / 25) * 100);

// Get distribution of levels for this user's tests
$stmt = $conn->prepare("
    SELECT assigned_level, COUNT(*) as count
    FROM level_test_results
    WHERE user_id = ?
    GROUP BY assigned_level
    ORDER BY FIELD(assigned_level, 'A1', 'A2', 'B1', 'B2', 'C1', 'C2')
");
$stmt->execute([$result['user_id']]);
$level_history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Result Details - Admin Dashboard</title>
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
    <style>
        .level-badge {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            border-radius: 50%;
            margin: 0 auto 15px;
        }
        .level-indicator {
            padding: 20px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .level-indicator:hover {
            transform: translateY(-5px);
        }
        .level-indicator.active {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .score-display {
            font-size: 3rem;
            font-weight: bold;
            color: #0d6efd;
            text-align: center;
            padding: 20px 0;
        }
        .test-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .test-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body class="admin-dashboard">
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Test Result Details</h1>
                    <a href="level-test-results.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Results
                    </a>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- Main content -->
                <div class="row g-4">
                    <!-- Test details -->
                    <div class="col-lg-12">
                        <div class="test-card card mb-4">
                            <div class="card-header bg-primary text-white py-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-clipboard-check me-2"></i> Test Result #<?php echo $result_id; ?>
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row align-items-center mb-5">
                                    <div class="col-md-4 text-center">
                                        <h6 class="text-muted mb-2">Score</h6>
                                        <div class="score-display"><?php echo $result['score']; ?></div>
                                        <div class="progress mb-2" style="height: 15px;">
                                            <div class="progress-bar 
                                                <?php 
                                                if ($pass_percentage < 40) echo 'bg-danger';
                                                else if ($pass_percentage < 70) echo 'bg-warning';
                                                else echo 'bg-success';
                                                ?>"
                                                role="progressbar"
                                                style="width: <?php echo $pass_percentage; ?>%"
                                                aria-valuenow="<?php echo $pass_percentage; ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo $pass_percentage; ?>% Score</small>
                                    </div>
                                    
                                    <div class="col-md-4 text-center">
                                        <h6 class="text-muted mb-2">Assigned Level</h6>
                                        <div class="level-badge bg-<?php 
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
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 text-center">
                                        <div class="mb-3">
                                            <h6 class="text-muted mb-2">Test Date</h6>
                                            <p class="fs-5 mb-0"><?php echo date('F d, Y', strtotime($result['test_date'])); ?></p>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($result['test_date'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <h6 class="text-muted mb-4 text-center">CEFR Level Placement</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-2">
                                        <div class="level-indicator text-center <?php echo $result['assigned_level'] == 'A1' ? 'active bg-light' : ''; ?>">
                                            <span class="badge bg-secondary d-block p-2 mb-2">A1</span>
                                            <span class="fs-5"><?php echo $result['assigned_level'] == 'A1' ? '✓' : ''; ?></span>
                                            <div class="small text-muted mt-1">Beginner</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="level-indicator text-center <?php echo $result['assigned_level'] == 'A2' ? 'active bg-light' : ''; ?>">
                                            <span class="badge bg-info d-block p-2 mb-2">A2</span>
                                            <span class="fs-5"><?php echo $result['assigned_level'] == 'A2' ? '✓' : ''; ?></span>
                                            <div class="small text-muted mt-1">Elementary</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="level-indicator text-center <?php echo $result['assigned_level'] == 'B1' ? 'active bg-light' : ''; ?>">
                                            <span class="badge bg-primary d-block p-2 mb-2">B1</span>
                                            <span class="fs-5"><?php echo $result['assigned_level'] == 'B1' ? '✓' : ''; ?></span>
                                            <div class="small text-muted mt-1">Intermediate</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="level-indicator text-center <?php echo $result['assigned_level'] == 'B2' ? 'active bg-light' : ''; ?>">
                                            <span class="badge bg-success d-block p-2 mb-2">B2</span>
                                            <span class="fs-5"><?php echo $result['assigned_level'] == 'B2' ? '✓' : ''; ?></span>
                                            <div class="small text-muted mt-1">Upper Intermediate</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="level-indicator text-center <?php echo $result['assigned_level'] == 'C1' ? 'active bg-light' : ''; ?>">
                                            <span class="badge bg-warning d-block p-2 mb-2">C1</span>
                                            <span class="fs-5"><?php echo $result['assigned_level'] == 'C1' ? '✓' : ''; ?></span>
                                            <div class="small text-muted mt-1">Advanced</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="level-indicator text-center <?php echo $result['assigned_level'] == 'C2' ? 'active bg-light' : ''; ?>">
                                            <span class="badge bg-danger d-block p-2 mb-2">C2</span>
                                            <span class="fs-5"><?php echo $result['assigned_level'] == 'C2' ? '✓' : ''; ?></span>
                                            <div class="small text-muted mt-1">Proficient</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Test History Card -->
                        <div class="test-card card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Test History</h5>
                            </div>
                            
                            <div class="card-body">
                                <?php if (count($other_tests) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Score</th>
                                                    <th>Level</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($other_tests as $test): ?>
                                                <tr>
                                                    <td><?php echo $test['result_id']; ?></td>
                                                    <td><?php echo $test['score']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($test['assigned_level']) {
                                                                case 'A1': echo 'secondary'; break;
                                                                case 'A2': echo 'info'; break;
                                                                case 'B1': echo 'primary'; break;
                                                                case 'B2': echo 'success'; break;
                                                                case 'C1': echo 'warning'; break;
                                                                case 'C2': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo $test['assigned_level']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($test['test_date'])); ?></td>
                                                    <td>
                                                        <a href="view-test-details.php?id=<?php echo $test['result_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> No other test results found for this user.
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (count($level_history) > 0): ?>
                                <div class="mt-5">
                                    <h6 class="text-muted mb-3">Level Progression</h6>
                                    <div class="card">
                                        <div class="card-body">
                                            <canvas id="levelHistoryChart" height="200"></canvas>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i> 
                                                This chart shows the distribution of CEFR levels across all tests taken by this user.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (count($level_history) > 0): ?>
            // Level History Chart
            new Chart(document.getElementById('levelHistoryChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($level_history, 'assigned_level')); ?>,
                    datasets: [{
                        label: 'Number of Tests',
                        data: <?php echo json_encode(array_column($level_history, 'count')); ?>,
                        backgroundColor: [
                            'rgba(108, 117, 125, 0.7)', // A1
                            'rgba(13, 202, 240, 0.7)',  // A2
                            'rgba(13, 110, 253, 0.7)',  // B1
                            'rgba(25, 135, 84, 0.7)',   // B2
                            'rgba(255, 193, 7, 0.7)',   // C1
                            'rgba(220, 53, 69, 0.7)'    // C2
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
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y + (context.parsed.y === 1 ? ' test' : ' tests');
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Number of Tests'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'CEFR Level'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>

</html> 