<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 0");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = "User deleted successfully.";
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error deleting user.";
    }
    header('Location: users.php');
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';
$level_filter = isset($_GET['level']) ? sanitize_input($_GET['level']) : '';

// Prepare base query
$query = "
    SELECT u.*, up.full_name, up.proficiency_level 
    FROM users u 
    LEFT JOIN user_profiles up ON u.user_id = up.user_id 
    WHERE 1=1
";
$params = [];

// Add search condition
if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR up.full_name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

// Add role filter
if ($role_filter !== '') {
    $query .= " AND u.role = ?";
    $params[] = (int)$role_filter;
}

// Add level filter
if ($level_filter) {
    $query .= " AND up.proficiency_level = ?";
    $params[] = $level_filter;
}

// Add sorting
$query .= " ORDER BY u.created_at DESC";

// Execute query
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching users.";
    $users = [];
}

// Get available levels for filter
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - ELearning Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="css/admin-style.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="../js/lib/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/nav.php'; ?>
        
        <main class="admin-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Users Management</h1>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

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
                        <!-- Filters -->
                        <form class="row g-3 mb-4" method="GET">
                            <div class="col-md-6">
                                <select class="form-select" name="role">
                                    <option value="">All Roles</option>
                                    <option value="0" <?php echo $role_filter === '0' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="1" <?php echo $role_filter === '1' ? 'selected' : ''; ?>>Student</option>
                                    <option value="2" <?php echo $role_filter === '2' ? 'selected' : ''; ?>>Instructor</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="level">
                                    <option value="">All Levels</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level; ?>" <?php echo $level_filter === $level ? 'selected' : ''; ?>>
                                            <?php echo $level; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>

                        <!-- Users Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Level</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo (int)$user['role'] === 0 ? 'danger' : 
                                                    ((int)$user['role'] === 1 ? 'primary' : 'success'); 
                                            ?>">
                                                <?php 
                                                    echo (int)$user['role'] === 0 ? 'Admin' : 
                                                        ((int)$user['role'] === 1 ? 'Student' : 'Instructor'); 
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['proficiency_level']): ?>
                                                <span class="badge bg-success"><?php echo $user['proficiency_level']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not tested</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="user-form.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($user['role'] !== 0): ?>
                                                <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete user <span id="deleteUserName" class="fw-bold"></span>?
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete With Courses Warning Modal -->
    <div class="modal fade" id="deleteWithCoursesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Warning: Deleting Instructor <span id="instructorWarningName" class="fw-bold"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <p class="mb-1">This instructor has <span id="courseCount" class="fw-bold"></span> courses with:</p>
                        <ul class="mb-0">
                            <li><span id="totalStudents" class="fw-bold"></span> enrolled students</li>
                            <li><span id="totalMaterials" class="fw-bold"></span> course materials</li>
                            <li><span id="featuredCourses" class="fw-bold"></span> featured courses</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info">
                        <p class="mb-1">This instructor also has:</p>
                        <ul class="mb-0">
                            <li><span id="instructorEnrollments" class="fw-bold"></span> course enrollments (as a student)</li>
                            <li><span id="instructorQuizzes" class="fw-bold"></span> quiz attempts</li>
                            <li><span id="instructorLevelTests" class="fw-bold"></span> level test results</li>
                            <li><span id="instructorChatCount" class="fw-bold"></span> chat messages</li>
                        </ul>
                    </div>
                    
                    <p>Deleting this instructor will also delete <strong>all</strong> their courses, course materials, and student enrollments. This action <strong>cannot be undone</strong>.</p>
                    
                    <div class="table-responsive mb-3">
                        <h6>Courses Created by this Instructor:</h6>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Course Title</th>
                                    <th>Level</th>
                                    <th>Students</th>
                                    <th>Materials</th>
                                    <th>Featured</th>
                                </tr>
                            </thead>
                            <tbody id="coursesList">
                                <!-- Course rows will be inserted here dynamically -->
                            </tbody>
                        </table>
                    </div>
                    
                    <p class="text-danger fw-bold">Are you sure you want to proceed with deletion?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="user_id" id="deleteWithCoursesUserId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Delete Instructor & All Courses
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete With Data Warning Modal -->
    <div class="modal fade" id="deleteWithDataModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Warning: Deleting <span id="studentRoleType"></span> <span id="studentWarningName" class="fw-bold"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <p class="mb-1">This user has the following data that will be deleted:</p>
                        <ul class="mb-0">
                            <li><span id="studentEnrollments" class="fw-bold"></span> course enrollments</li>
                            <li><span id="studentQuizzes" class="fw-bold"></span> quiz attempts</li>
                            <li><span id="studentLevelTests" class="fw-bold"></span> level test results</li>
                            <li><span id="studentChatCount" class="fw-bold"></span> chat messages</li>
                            <li id="gameProgressRow"><span id="studentGameScore" class="fw-bold"></span> points in games</li>
                        </ul>
                    </div>
                    
                    <p>Deleting this user will permanently remove all their data. This action <strong>cannot be undone</strong>.</p>
                    
                    <!-- Course Enrollments Section -->
                    <div id="enrollmentSection" class="mb-3">
                        <h6>Course Enrollments:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Title</th>
                                        <th>Level</th>
                                        <th>Has Progress</th>
                                    </tr>
                                </thead>
                                <tbody id="enrollmentsList">
                                    <!-- Enrollment rows will be inserted here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quiz Attempts Section -->
                    <div id="quizSection" class="mb-3">
                        <h6>Quiz Attempts:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Quiz Title</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="quizList">
                                    <!-- Quiz rows will be inserted here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Level Test Results Section -->
                    <div id="levelTestSection" class="mb-3">
                        <h6>Level Test Results:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Assigned Level</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="levelTestList">
                                    <!-- Level test rows will be inserted here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Chat Messages Section -->
                    <div id="chatSection" class="mb-3">
                        <h6>Chat History:</h6>
                        <p>This user has <span id="chatCountDetail" class="fw-bold"></span> chat messages that will be deleted.</p>
                    </div>
                    
                    <p class="text-danger fw-bold">Are you sure you want to proceed with deletion?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="user_id" id="deleteWithDataUserId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Delete User & All Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/lib/jquery-3.7.1.min.js"></script>
    <script src="../js/lib/jquery.dataTables.min.js"></script>
    <script src="../js/lib/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 25,
                order: [[5, 'desc']], // Sort by joined date by default
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rtip'
            });
        });

        // Delete confirmation
        function confirmDelete(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            
            // Skip detailed checks for admin users (though they shouldn't be deletable)
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            const userRow = document.querySelector(`button[onclick="confirmDelete(${userId}, '${username}')"]`).closest('tr');
            const userRole = userRow.querySelector('td:nth-child(4) .badge').textContent.trim();
            
            if (userRole === 'Admin') {
                // Admin users shouldn't be deletable, but just in case
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
                return;
            }
            
            // For students and instructors, check associated data
            $.ajax({
                url: 'check_user_data.php',
                type: 'POST',
                data: { user_id: userId },
                dataType: 'json',
                success: function(response) {
                    const hasAssociatedData = 
                        response.has_enrollments || 
                        response.has_quiz_attempts || 
                        response.has_level_tests || 
                        response.has_game_progress || 
                        response.has_chat_messages ||
                        (response.user_role === 2 && response.has_courses); // Instructors with courses
                    
                    if (hasAssociatedData) {
                        // Show the appropriate warning modal based on user role
                        if (response.user_role === 2 && response.has_courses) {
                            // Instructor with courses
                            document.getElementById('deleteWithCoursesUserId').value = userId;
                            prepareInstructorWarningModal(response, username);
                            new bootstrap.Modal(document.getElementById('deleteWithCoursesModal')).show();
                        } else {
                            // Student or instructor without courses but with other data
                            document.getElementById('deleteWithDataUserId').value = userId;
                            prepareStudentWarningModal(response, username, userRole);
                            new bootstrap.Modal(document.getElementById('deleteWithDataModal')).show();
                        }
                    } else {
                        // No associated data, show regular confirmation
                        new bootstrap.Modal(document.getElementById('deleteModal')).show();
                    }
                },
                error: function() {
                    // On error, fallback to regular confirmation
                    new bootstrap.Modal(document.getElementById('deleteModal')).show();
                }
            });
            <?php else: ?>
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
            <?php endif; ?>
        }
        
        // Prepare the instructor warning modal with course data
        function prepareInstructorWarningModal(data, username) {
            document.getElementById('courseCount').textContent = data.course_count;
            document.getElementById('totalStudents').textContent = data.total_students;
            document.getElementById('totalMaterials').textContent = data.total_materials;
            document.getElementById('featuredCourses').textContent = data.featured_courses;
            document.getElementById('instructorWarningName').textContent = username;
            
            // Additional data that all users might have
            document.getElementById('instructorEnrollments').textContent = data.enrollments_count || 0;
            document.getElementById('instructorQuizzes').textContent = data.quiz_attempts_count || 0;
            document.getElementById('instructorLevelTests').textContent = data.level_tests_count || 0;
            document.getElementById('instructorChatCount').textContent = data.chat_message_count || 0;
            
            // Generate course list table rows
            const coursesList = document.getElementById('coursesList');
            coursesList.innerHTML = ''; // Clear existing rows
            
            data.courses.forEach(function(course) {
                const row = document.createElement('tr');
                
                // Add course title cell
                const titleCell = document.createElement('td');
                titleCell.textContent = course.title;
                row.appendChild(titleCell);
                
                // Add level cell
                const levelCell = document.createElement('td');
                levelCell.textContent = course.level;
                row.appendChild(levelCell);
                
                // Add students cell
                const studentsCell = document.createElement('td');
                studentsCell.textContent = course.student_count;
                studentsCell.className = course.student_count > 0 ? 'text-danger fw-bold' : '';
                row.appendChild(studentsCell);
                
                // Add materials cell
                const materialsCell = document.createElement('td');
                materialsCell.textContent = course.materials_count;
                row.appendChild(materialsCell);
                
                // Add featured cell
                const featuredCell = document.createElement('td');
                featuredCell.innerHTML = course.is_featured == 1 
                    ? '<i class="fas fa-check text-success"></i>' 
                    : '<i class="fas fa-times text-muted"></i>';
                featuredCell.className = 'text-center';
                row.appendChild(featuredCell);
                
                coursesList.appendChild(row);
            });
        }
        
        // Prepare the student warning modal with associated data
        function prepareStudentWarningModal(data, username, userRole) {
            document.getElementById('studentWarningName').textContent = username;
            document.getElementById('studentRoleType').textContent = userRole;
            
            // Set user data counts
            document.getElementById('studentEnrollments').textContent = data.enrollments_count || 0;
            document.getElementById('studentQuizzes').textContent = data.quiz_attempts_count || 0;
            document.getElementById('studentLevelTests').textContent = data.level_tests_count || 0;
            document.getElementById('studentChatCount').textContent = data.chat_message_count || 0;
            document.getElementById('chatCountDetail').textContent = data.chat_message_count || 0;
            
            // Game progress
            if (data.has_game_progress) {
                document.getElementById('gameProgressRow').style.display = '';
                document.getElementById('studentGameScore').textContent = data.game_progress.total_score || 0;
            } else {
                document.getElementById('gameProgressRow').style.display = 'none';
            }
            
            // Hide or show sections based on what data exists
            document.getElementById('enrollmentSection').style.display = data.has_enrollments ? '' : 'none';
            document.getElementById('quizSection').style.display = data.has_quiz_attempts ? '' : 'none';
            document.getElementById('levelTestSection').style.display = data.has_level_tests ? '' : 'none';
            document.getElementById('chatSection').style.display = data.has_chat_messages ? '' : 'none';
            
            // Generate course enrollments table if any exist
            if (data.has_enrollments) {
                const enrollmentsList = document.getElementById('enrollmentsList');
                enrollmentsList.innerHTML = ''; // Clear existing rows
                
                data.enrollments.forEach(function(enrollment) {
                    const row = document.createElement('tr');
                    
                    // Add course title cell
                    const titleCell = document.createElement('td');
                    titleCell.textContent = enrollment.title;
                    row.appendChild(titleCell);
                    
                    // Add level cell
                    const levelCell = document.createElement('td');
                    levelCell.textContent = enrollment.level;
                    row.appendChild(levelCell);
                    
                    // Add progress cell
                    const progressCell = document.createElement('td');
                    progressCell.textContent = enrollment.progress_count > 0 ? 'Yes' : 'No';
                    progressCell.className = enrollment.progress_count > 0 ? 'text-success' : 'text-muted';
                    row.appendChild(progressCell);
                    
                    enrollmentsList.appendChild(row);
                });
            }
            
            // Generate quiz attempts table if any exist
            if (data.has_quiz_attempts) {
                const quizList = document.getElementById('quizList');
                quizList.innerHTML = ''; // Clear existing rows
                
                data.quiz_attempts.forEach(function(quiz) {
                    const row = document.createElement('tr');
                    
                    // Add quiz title cell
                    const titleCell = document.createElement('td');
                    titleCell.textContent = quiz.title;
                    row.appendChild(titleCell);
                    
                    // Add score cell
                    const scoreCell = document.createElement('td');
                    scoreCell.textContent = quiz.score;
                    row.appendChild(scoreCell);
                    
                    // Add date cell
                    const dateCell = document.createElement('td');
                    dateCell.textContent = new Date(quiz.completion_date).toLocaleDateString();
                    row.appendChild(dateCell);
                    
                    quizList.appendChild(row);
                });
            }
            
            // Generate level test results table if any exist
            if (data.has_level_tests) {
                const levelTestList = document.getElementById('levelTestList');
                levelTestList.innerHTML = ''; // Clear existing rows
                
                data.level_tests.forEach(function(test) {
                    const row = document.createElement('tr');
                    
                    // Add level cell
                    const levelCell = document.createElement('td');
                    levelCell.textContent = test.assigned_level;
                    row.appendChild(levelCell);
                    
                    // Add score cell
                    const scoreCell = document.createElement('td');
                    scoreCell.textContent = test.score;
                    row.appendChild(scoreCell);
                    
                    // Add date cell
                    const dateCell = document.createElement('td');
                    dateCell.textContent = new Date(test.test_date).toLocaleDateString();
                    row.appendChild(dateCell);
                    
                    levelTestList.appendChild(row);
                });
            }
        }
    </script>
</body>
</html>
