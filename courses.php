<?php
session_start();
require_once 'includes/db.php';

// Get user's level if logged in
$user_level = null;
if (is_logged_in()) {
    try {
        $stmt = $conn->prepare("SELECT proficiency_level FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch();
        $user_level = $profile['proficiency_level'] ?? null;
    } catch(PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Get filter parameters
$level_filter = sanitize_input($_GET['level'] ?? '');
$search = sanitize_input($_GET['search'] ?? '');

// Build query
$sql = "SELECT * FROM courses WHERE 1=1";
$params = [];

if ($level_filter) {
    $sql .= " AND level = ?";
    $params[] = $level_filter;
}

if ($search) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY level ASC, title ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Get unique levels for filter
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Floating Chatbot CSS -->
    <link href="css/floating-chatbot.css" rel="stylesheet">
    <!-- Search Autocomplete CSS -->
    <link href="css/search-autocomplete.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section text-white text-center py-5">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4 text-light">Our Courses</h1>
            <p class="lead mb-4">Explore our comprehensive range of English language courses designed for all proficiency levels.</p>
        </div>
    </section>

    <!-- Search and Filter Section -->
    <section class="py-4 bg-light">
        <div class="container">
            <form action="courses.php" method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group search-input-container">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-primary"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search" 
                               placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>"
                               data-autosuggest="true" autocomplete="off">
                    </div>
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
        </div>
    </section>

    <!-- Courses Section -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($courses)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h3>No courses found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <a href="course-details.php?id=<?php echo $course['course_id']; ?>">
                                    <img src="<?php if($course['thumbnail_url']): ?>uploads/<?php echo $course['thumbnail_url']; ?><?php else: ?>https://placehold.co/300x200/415a77/f2f2f2<?php endif ; ?>"
                                        class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>"
                                        style="height: 300px; object-fit: cover;">
                                </a>
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary"><?php echo $course['level']; ?></span>
                                        <?php if ($user_level && $user_level === $course['level']): ?>
                                            <span class="badge bg-success">Recommended</span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="card-title mb-3"><?php echo htmlspecialchars($course['title']); ?></h5>
                                    <p class="card-text text-muted mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                                        </small>
                                        <a href="course-details.php?id=<?php echo $course['course_id']; ?>" 
                                           class="btn btn-outline-primary">
                                            Learn More
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html>