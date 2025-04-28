<?php
require_once __DIR__ . '/includes/db.php';
session_start();
require_login();

$search_query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
$results = [
    'users' => [],
    'courses' => [],
    'materials' => [],
    'resources' => [],
    'quizzes' => [],
    // Add more categories as needed
];

if ($search_query !== '') {
    // Search Users
    $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE username LIKE ? OR email LIKE ?");
    $like = "%$search_query%";
    $stmt->execute([$like, $like]);
    $results['users'] = $stmt->fetchAll();

    // Search Courses
    $stmt = $conn->prepare("SELECT course_id, title, description FROM courses WHERE title LIKE ? OR description LIKE ?");
    $stmt->execute([$like, $like]);
    $results['courses'] = $stmt->fetchAll();

    // Search Materials
    $stmt = $conn->prepare("SELECT material_id, title, description FROM course_materials WHERE title LIKE ? OR description LIKE ?");
    $stmt->execute([$like, $like]);
    $results['materials'] = $stmt->fetchAll();

    // Search Resources
    $stmt = $conn->prepare("SELECT resource_id, title, description FROM resource_library WHERE title LIKE ? OR description LIKE ?");
    $stmt->execute([$like, $like]);
    $results['resources'] = $stmt->fetchAll();

    // Search Quizzes
    $stmt = $conn->prepare("SELECT quiz_id, title FROM quizzes WHERE title LIKE ?");
    $stmt->execute([$like]);
    $results['quizzes'] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Search Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="../css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-style.css">
    <link rel="stylesheet" href="css/custom-search-responsive.css">
    <link href="css/search-highlight.css" rel="stylesheet">
</head>
<body class="admin-dashboard">
<div class="admin-wrapper">
    <?php include 'includes/nav.php'; ?>
    <main class="admin-content">
        <div class="container-fluid py-4">
            <h1 class="mb-4">Search Results for &quot;<?php echo htmlspecialchars($search_query); ?>&quot;</h1>
            <form method="get" action="search.php" class="mb-4 d-flex" style="max-width:400px;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" class="form-control me-2" placeholder="Search everything..." required>
                <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Search</button>
            </form>
            <?php if ($search_query !== ''): ?>
                <div class="row g-4">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 search-category-card">
                            <div class="card-header bg-info text-white d-flex align-items-center">
                                <i class="fas fa-users me-2"></i><h5 class="mb-0">Users</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($results['users'])): ?>
                                    <input type="text" class="form-control mb-2 filter-input" data-target="user-list" placeholder="Filter users...">
                                    <ul class="list-group list-group-flush user-list">
                                        <?php foreach ($results['users'] as $user): ?>
                                            <li class="list-group-item d-flex align-items-center gap-2">
                                                <i class="fas fa-user text-info"></i>
                                                <a href="user-form.php?id=<?php echo $user['user_id']; ?>" class="fw-semibold text-decoration-none" style="color:#2b6cb0;"><?php echo htmlspecialchars($user['username']); ?></a>
                                                <span class="text-muted small ms-1">(<?php echo htmlspecialchars($user['email']); ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted">No users found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 search-category-card">
                            <div class="card-header bg-success text-white d-flex align-items-center">
                                <i class="fas fa-book me-2"></i><h5 class="mb-0">Courses</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($results['courses'])): ?>
                                    <input type="text" class="form-control mb-2 filter-input" data-target="course-list" placeholder="Filter courses...">
                                    <ul class="list-group list-group-flush course-list">
                                        <?php foreach ($results['courses'] as $course): ?>
                                            <li class="list-group-item">
                                                <a href="course-form.php?id=<?php echo $course['course_id']; ?>" class="fw-semibold text-decoration-none" style="color:#38a169;"><?php echo htmlspecialchars($course['title']); ?></a>
                                                <div class="small text-muted"> <?php echo htmlspecialchars($course['description']); ?> </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted">No courses found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 search-category-card">
                            <div class="card-header bg-warning text-dark d-flex align-items-center">
                                <i class="fas fa-file-alt me-2"></i><h5 class="mb-0">Materials</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($results['materials'])): ?>
                                    <input type="text" class="form-control mb-2 filter-input" data-target="material-list" placeholder="Filter materials...">
                                    <ul class="list-group list-group-flush material-list">
                                        <?php foreach ($results['materials'] as $material): ?>
                                            <li class="list-group-item">
                                                <a href="material-form.php?id=<?php echo $material['material_id']; ?>" class="fw-semibold text-decoration-none" style="color:#b7791f;"><?php echo htmlspecialchars($material['title']); ?></a>
                                                <div class="small text-muted"> <?php echo htmlspecialchars($material['description']); ?> </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted">No materials found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 search-category-card">
                            <div class="card-header bg-secondary text-white d-flex align-items-center">
                                <i class="fas fa-folder me-2"></i><h5 class="mb-0">Resources</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($results['resources'])): ?>
                                    <input type="text" class="form-control mb-2 filter-input" data-target="resource-list" placeholder="Filter resources...">
                                    <ul class="list-group list-group-flush resource-list">
                                        <?php foreach ($results['resources'] as $resource): ?>
                                            <li class="list-group-item">
                                                <a href="resource-form.php?id=<?php echo $resource['resource_id']; ?>" class="fw-semibold text-decoration-none" style="color:#718096;"><?php echo htmlspecialchars($resource['title']); ?></a>
                                                <div class="small text-muted"> <?php echo htmlspecialchars($resource['description']); ?> </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted">No resources found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 search-category-card">
                            <div class="card-header bg-danger text-white d-flex align-items-center">
                                <i class="fas fa-question-circle me-2"></i><h5 class="mb-0">Quizzes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($results['quizzes'])): ?>
                                    <input type="text" class="form-control mb-2 filter-input" data-target="quiz-list" placeholder="Filter quizzes...">
                                    <ul class="list-group list-group-flush quiz-list">
                                        <?php foreach ($results['quizzes'] as $quiz): ?>
                                            <li class="list-group-item">
                                                <a href="quiz-form.php?id=<?php echo $quiz['quiz_id']; ?>" class="fw-semibold text-decoration-none" style="color:#e53e3e;"><?php echo htmlspecialchars($quiz['title']); ?></a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted">No quizzes found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Improved filtering for each category with dynamic no result message
function setupFilters() {
    document.querySelectorAll('.filter-input').forEach(input => {
        input.addEventListener('input', function() {
            const targetClass = input.getAttribute('data-target');
            const list = input.parentElement.querySelector('.' + targetClass);
            if (!list) return;
            const filter = input.value.toLowerCase();
            let matchCount = 0;
            list.querySelectorAll('li').forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(filter)) {
                    item.style.display = '';
                    matchCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            let noResultMsg = input.parentElement.querySelector('.no-filter-result');
            if (!noResultMsg) {
                noResultMsg = document.createElement('div');
                noResultMsg.className = 'no-filter-result text-muted mt-2';
                input.parentElement.appendChild(noResultMsg);
            }
            if (matchCount === 0) {
                noResultMsg.style.display = '';
                noResultMsg.textContent = 'No results found.';
            } else {
                noResultMsg.style.display = 'none';
            }
        });
    });
}
document.addEventListener('DOMContentLoaded', setupFilters);
</script>
</body>
</html>
