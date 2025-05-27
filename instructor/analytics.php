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

// Get instructor's courses
$stmt = $conn->prepare("
    SELECT course_id, title, level, difficulty_level
    FROM courses 
    WHERE instructor_id = ?
    ORDER BY title
");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if a specific course is selected
$selected_course_id = isset($_GET['course']) ? (int)$_GET['course'] : null;

// Validate that the course belongs to the instructor
if ($selected_course_id) {
    $stmt = $conn->prepare("
        SELECT course_id, title, level, difficulty_level, description
        FROM courses 
        WHERE course_id = ? AND instructor_id = ?
    ");
    $stmt->execute([$selected_course_id, $instructor_id]);
    $selected_course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$selected_course) {
        $selected_course_id = null;
    }
}

// Get total student count
$student_count = get_instructor_student_count($instructor_id);

// Get enrollment trend for past 30 days
$enrollment_trend = get_enrollment_trend($instructor_id, 30);

// If a course is selected, get detailed analytics
if ($selected_course_id) {
    // Get student progress for this course
    $student_progress = get_course_student_progress($selected_course_id);
    
    // Get quiz performance for this course
    $quiz_performance = get_course_quiz_performance($selected_course_id);
    
    // Get material engagement data
    $material_engagement = get_material_engagement($selected_course_id);
}

// Page title
$page_title = "Analytics";
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
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <link href="css/instructor-style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .instructor-sidebar {
            background-color: #343a40;
            min-height: 100vh;
            color: white;
            padding-top: 2rem;
        }
        
        .instructor-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        
        .instructor-sidebar .nav-link:hover,
        .instructor-sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .instructor-sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .instructor-main {
            padding: 2rem;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .progress-circle {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e9ecef;
        }
        
        .progress-circle::after {
            content: attr(data-progress) '%';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: bold;
        }
        
        .progress-circle-fill {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            clip: rect(0, 60px, 60px, 30px);
        }
        
        .progress-circle-value {
            position: absolute;
            top: 5px;
            left: 5px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: white;
            text-align: center;
            line-height: 50px;
            font-size: 18px;
            font-weight: bold;
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
                            <a href="analytics.php" class="nav-link active">
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
                <div class="container-fluid">
                    <h1 class="mb-4"><?php echo $page_title; ?></h1>
                    
                    <!-- Course Selector -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Select a Course for Detailed Analytics</h5>
                            <?php if (empty($courses)): ?>
                                <p class="text-muted">You haven't created any courses yet.</p>
                                <a href="course-form.php" class="btn btn-primary">Create Course</a>
                            <?php else: ?>
                                <form method="GET" action="analytics.php" class="row g-3">
                                    <div class="col-md-6">
                                        <select name="course" class="form-select" onchange="this.form.submit()">
                                            <option value="">-- Select a course --</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo $course['course_id']; ?>" 
                                                        <?php echo $selected_course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course['title']); ?> 
                                                    (<?php echo htmlspecialchars($course['level']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Overall Analytics -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Overall Enrollment Trend (Last 30 Days)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="enrollmentTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Total Students</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="stat-icon text-primary">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="stat-number"><?php echo $student_count; ?></div>
                                            <div class="text-muted">Students enrolled in your courses</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($selected_course_id && isset($selected_course)): ?>
                    <!-- Course-specific Analytics -->
                    <h2 class="mt-5 mb-4"><?php echo htmlspecialchars($selected_course['title']); ?> Analytics</h2>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Student Progress</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="studentProgressChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Quiz Performance</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($quiz_performance)): ?>
                                        <p class="text-center text-muted my-4">No quizzes found for this course.</p>
                                    <?php else: ?>
                                        <div class="chart-container">
                                            <canvas id="quizPerformanceChart"></canvas>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Material Engagement</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($material_engagement)): ?>
                                        <p class="text-center text-muted my-4">No materials found for this course.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Material</th>
                                                        <th>Order</th>
                                                        <th>Viewed By</th>
                                                        <th>Avg. Progress</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($material_engagement as $material): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($material['title']); ?></td>
                                                            <td><?php echo $material['order_number']; ?></td>
                                                            <td><?php echo $material['viewed_by']; ?> students</td>
                                                            <td>
                                                                <div class="progress" style="height: 10px; width: 100px;">
                                                                    <div class="progress-bar" role="progressbar" 
                                                                         style="width: <?php echo $material['avg_progress']; ?>%;" 
                                                                         aria-valuenow="<?php echo $material['avg_progress']; ?>" 
                                                                         aria-valuemin="0" 
                                                                         aria-valuemax="100">
                                                                    </div>
                                                                </div>
                                                                <small class="ms-2"><?php echo $material['avg_progress']; ?>%</small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Student List & Progress</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($student_progress)): ?>
                                        <p class="text-center text-muted my-4">No students enrolled in this course.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Progress</th>
                                                        <th>Materials Completed</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($student_progress as $student): ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($student['student_name']); ?>
                                                                <small class="text-muted d-block"><?php echo htmlspecialchars($student['username']); ?></small>
                                                            </td>
                                                            <td>
                                                                <div class="progress" style="height: 10px; width: 100px;">
                                                                    <div class="progress-bar" role="progressbar" 
                                                                         style="width: <?php echo $student['avg_progress']; ?>%;" 
                                                                         aria-valuenow="<?php echo $student['avg_progress']; ?>" 
                                                                         aria-valuemin="0" 
                                                                         aria-valuemax="100">
                                                                    </div>
                                                                </div>
                                                                <small class="ms-2"><?php echo $student['avg_progress']; ?>%</small>
                                                            </td>
                                                            <td>
                                                                <?php echo $student['completed_materials']; ?> / <?php echo $student['total_materials']; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Select a course from the dropdown above to view detailed analytics.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enrollment Trend Chart
            const enrollmentTrendCtx = document.getElementById('enrollmentTrendChart')?.getContext('2d');
            
            if (enrollmentTrendCtx) {
                // Enrollment trend data
                const enrollmentData = <?php 
                    // Create array of dates for the last 30 days
                    $dates = [];
                    $counts = [];
                    $currentDate = new DateTime();
                    
                    // Build an array of the last 30 days
                    for ($i = 29; $i >= 0; $i--) {
                        $date = clone $currentDate;
                        $date->modify("-$i days");
                        $dateStr = $date->format('Y-m-d');
                        $dates[] = $date->format('M d');
                        
                        // Find if we have enrollment data for this date
                        $found = false;
                        foreach ($enrollment_trend as $trend) {
                            if ($trend['date'] === $dateStr) {
                                $counts[] = (int)$trend['count'];
                                $found = true;
                                break;
                            }
                        }
                        
                        // If no data for this date, add 0
                        if (!$found) {
                            $counts[] = 0;
                        }
                    }
                    
                    echo json_encode([
                        'labels' => $dates,
                        'data' => $counts
                    ]); 
                ?>;
                
                new Chart(enrollmentTrendCtx, {
                    type: 'line',
                    data: {
                        labels: enrollmentData.labels,
                        datasets: [{
                            label: 'New Enrollments',
                            data: enrollmentData.data,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Enrollments'
                                },
                                ticks: {
                                    precision: 0
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date'
                                }
                            }
                        }
                    }
                });
            }
            
            <?php if ($selected_course_id && isset($selected_course) && !empty($student_progress)): ?>
            // Student Progress Chart
            const studentProgressCtx = document.getElementById('studentProgressChart')?.getContext('2d');
            
            if (studentProgressCtx) {
                // Create student progress data for chart
                const progressData = <?php 
                    // Create buckets for progress ranges
                    $ranges = [
                        '0-20%' => 0,
                        '21-40%' => 0,
                        '41-60%' => 0,
                        '61-80%' => 0,
                        '81-100%' => 0
                    ];
                    
                    // Count students in each range
                    foreach ($student_progress as $student) {
                        $progress = (int)$student['avg_progress'];
                        
                        if ($progress <= 20) {
                            $ranges['0-20%']++;
                        } elseif ($progress <= 40) {
                            $ranges['21-40%']++;
                        } elseif ($progress <= 60) {
                            $ranges['41-60%']++;
                        } elseif ($progress <= 80) {
                            $ranges['61-80%']++;
                        } else {
                            $ranges['81-100%']++;
                        }
                    }
                    
                    echo json_encode([
                        'labels' => array_keys($ranges),
                        'data' => array_values($ranges)
                    ]); 
                ?>;
                
                new Chart(studentProgressCtx, {
                    type: 'pie',
                    data: {
                        labels: progressData.labels,
                        datasets: [{
                            data: progressData.data,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(255, 205, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(54, 162, 235, 0.7)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(255, 159, 64, 1)',
                                'rgba(255, 205, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(54, 162, 235, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} students (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
            
            <?php if ($selected_course_id && isset($selected_course) && !empty($quiz_performance)): ?>
            // Quiz Performance Chart
            const quizPerformanceCtx = document.getElementById('quizPerformanceChart')?.getContext('2d');
            
            if (quizPerformanceCtx) {
                // Quiz performance data
                const quizData = <?php 
                    $quizLabels = array_map(function($quiz) { 
                        return $quiz['title']; 
                    }, $quiz_performance);
                    
                    $avgScores = array_map(function($quiz) { 
                        return $quiz['avg_score']; 
                    }, $quiz_performance);
                    
                    $minScores = array_map(function($quiz) { 
                        return $quiz['min_score']; 
                    }, $quiz_performance);
                    
                    $maxScores = array_map(function($quiz) { 
                        return $quiz['max_score']; 
                    }, $quiz_performance);
                    
                    echo json_encode([
                        'labels' => $quizLabels,
                        'avg' => $avgScores,
                        'min' => $minScores,
                        'max' => $maxScores
                    ]); 
                ?>;
                
                new Chart(quizPerformanceCtx, {
                    type: 'bar',
                    data: {
                        labels: quizData.labels,
                        datasets: [{
                            label: 'Average Score',
                            data: quizData.avg,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }, {
                            label: 'Min Score',
                            data: quizData.min,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }, {
                            label: 'Max Score',
                            data: quizData.max,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
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
                                    text: 'Quizzes'
                                }
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html> 