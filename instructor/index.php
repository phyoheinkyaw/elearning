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

// Get instructor's courses count
$stmt = $conn->prepare("
    SELECT COUNT(*) as course_count 
    FROM courses 
    WHERE instructor_id = ?
");
$stmt->execute([$instructor_id]);
$course_count = $stmt->fetch(PDO::FETCH_ASSOC)['course_count'];

// Get total enrollments in instructor's courses
$stmt = $conn->prepare("
    SELECT COUNT(*) as enrollment_count 
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.course_id
    WHERE c.instructor_id = ?
");
$stmt->execute([$instructor_id]);
$enrollment_count = $stmt->fetch(PDO::FETCH_ASSOC)['enrollment_count'];

// Get average student progress
$stmt = $conn->prepare("
    SELECT AVG(up.progress) as avg_progress
    FROM user_progress up
    JOIN course_materials cm ON up.material_id = cm.material_id
    JOIN courses c ON cm.course_id = c.course_id
    WHERE c.instructor_id = ?
");
$stmt->execute([$instructor_id]);
$avg_progress = $stmt->fetch(PDO::FETCH_ASSOC)['avg_progress'];
$avg_progress = $avg_progress ? round($avg_progress) : 0;

// Get courses with enrollment counts
$stmt = $conn->prepare("
    SELECT 
        c.course_id, 
        c.title, 
        c.level, 
        COUNT(ce.enrollment_id) as student_count,
        c.created_at
    FROM courses c
    LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
    WHERE c.instructor_id = ?
    GROUP BY c.course_id
    ORDER BY student_count DESC
");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent enrollments
$stmt = $conn->prepare("
    SELECT 
        u.username,
        u.user_id,
        c.title as course_title,
        c.course_id,
        ce.enrolled_at
    FROM course_enrollments ce
    JOIN users u ON ce.user_id = u.user_id
    JOIN courses c ON ce.course_id = c.course_id
    WHERE c.instructor_id = ?
    ORDER BY ce.enrolled_at DESC
    LIMIT 10
");
$stmt->execute([$instructor_id]);
$recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$page_title = "Instructor Dashboard";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ELearning</title>
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
        
        .course-card {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
                            <a href="index.php" class="nav-link active">
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
                    <h1 class="mb-4">Instructor Dashboard</h1>
                    
                    <!-- Stats Overview -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stat-card text-center mb-4">
                                <div class="card-body">
                                    <div class="stat-icon text-primary">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="stat-number"><?php echo $course_count; ?></div>
                                    <div class="stat-label">Courses</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card text-center mb-4">
                                <div class="card-body">
                                    <div class="stat-icon text-success">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-number"><?php echo $enrollment_count; ?></div>
                                    <div class="stat-label">Total Enrollments</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card text-center mb-4">
                                <div class="card-body">
                                    <div class="stat-icon text-info">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-number"><?php echo $avg_progress; ?>%</div>
                                    <div class="stat-label">Average Progress</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enrollment Chart -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Course Enrollments</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="enrollmentChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Courses and Recent Enrollments -->
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">My Courses</h5>
                                    <a href="courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($courses)): ?>
                                        <p class="text-center text-muted my-4">You haven't created any courses yet.</p>
                                        <div class="text-center">
                                            <a href="course-form.php" class="btn btn-primary">Create Course</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Course</th>
                                                        <th>Level</th>
                                                        <th>Students</th>
                                                        <th>Created</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($courses as $course): ?>
                                                        <tr>
                                                            <td>
                                                                <a href="course-details.php?id=<?php echo $course['course_id']; ?>">
                                                                    <?php echo htmlspecialchars($course['title']); ?>
                                                                </a>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($course['level']); ?></td>
                                                            <td>
                                                                <span class="badge bg-primary">
                                                                    <?php echo $course['student_count']; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Recent Enrollments</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_enrollments)): ?>
                                        <p class="text-center text-muted my-4">No recent enrollments.</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($recent_enrollments as $enrollment): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($enrollment['username']); ?></div>
                                                        <div class="small text-muted">
                                                            Enrolled in 
                                                            <a href="course-details.php?id=<?php echo $enrollment['course_id']; ?>">
                                                                <?php echo htmlspecialchars($enrollment['course_title']); ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?>
                                                    </small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enrollment Chart
            const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
            
            // Course data
            const courseData = <?php 
                $chartLabels = array_map(function($course) { 
                    return $course['title']; 
                }, $courses);
                
                $chartData = array_map(function($course) { 
                    return $course['student_count']; 
                }, $courses);
                
                echo json_encode([
                    'labels' => $chartLabels,
                    'data' => $chartData
                ]); 
            ?>;
            
            new Chart(enrollmentCtx, {
                type: 'bar',
                data: {
                    labels: courseData.labels,
                    datasets: [{
                        label: 'Student Enrollments',
                        data: courseData.data,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
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
                                text: 'Number of Students'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Courses'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 