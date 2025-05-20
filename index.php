<?php
session_start();
require_once 'includes/db.php';

// Get featured courses with instructor names and enrollment counts
try {
    $stmt = $conn->query("
        SELECT c.*, 
               u.username as instructor_name,
               COUNT(DISTINCT e.user_id) as enrolled_count,
               COUNT(DISTINCT m.material_id) as material_count
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.user_id
        LEFT JOIN course_enrollments e ON c.course_id = e.course_id 
        LEFT JOIN course_materials m ON c.course_id = m.course_id
        WHERE c.is_featured = 1 
        GROUP BY c.course_id
    ");
    $featured_courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $featured_courses = [];
}

// Get resources from resource library
try {
    $stmt = $conn->query("
        SELECT r.*, c.title as course_title 
        FROM resource_library r
        LEFT JOIN courses c ON r.course_id = c.course_id
        ORDER BY RAND()
        LIMIT 3
    ");
    $resources = $stmt->fetchAll();
} catch(PDOException $e) {
    $resources = [];
}

// Define file type names for display
$file_types = [
    0 => 'PDF',
    1 => 'E-book',
    2 => 'Worksheet'
];

// Get latest course materials with course information
try {
    $stmt = $conn->query("
        SELECT m.*, c.title as course_title, c.level
        FROM course_materials m
        JOIN courses c ON m.course_id = c.course_id
        ORDER BY m.created_at DESC 
        LIMIT 3
    ");
    $latest_materials = $stmt->fetchAll();
} catch(PDOException $e) {
    $latest_materials = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELearning - Master English Online</title>
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
    <section class="hero-section text-white text-center position-relative overflow-hidden">
        <div class="container position-relative py-5">
            <div class="row py-5 align-items-center">
                <div class="col-lg-6 text-lg-start">
                    <h1 class="display-3 fw-bold mb-4 text-light animate-up">Master English with Confidence</h1>
                    <p class="lead mb-4 animate-up">Join our interactive online learning platform and improve your
                        English skills with expert-led courses, personalized feedback, and a supportive community.</p>
                    <div class="d-flex gap-3 justify-content-lg-start justify-content-center animate-up">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="btn btn-primary btn-lg">Get Started</a>
                        <?php else: ?>
                        <a href="my-courses.php" class="btn btn-primary btn-lg">My Courses</a>
                        <?php endif; ?>
                        <a href="courses.php" class="btn btn-outline-light btn-lg">Browse Courses</a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block animate-up">
                    <img src="https://placehold.co/600x400/0d1b2a/e0e1dd?text=Learn+English" alt="Learning Illustration"
                        class="img-fluid">
                </div>
            </div>
        </div>
        <div class="hero-shape"></div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Why Choose Us?</h2>
                <p class="lead text-muted">Experience the best way to learn English online</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm feature-card">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon mb-4">
                                <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                            </div>
                            <h3 class="h4 mb-3">Expert Teachers</h3>
                            <p class="text-muted mb-0">Learn from qualified and experienced English teachers who provide
                                personalized guidance.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm feature-card">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon mb-4">
                                <i class="fas fa-comments fa-3x text-primary"></i>
                            </div>
                            <h3 class="h4 mb-3">Interactive Learning</h3>
                            <p class="text-muted mb-0">Engage in interactive lessons, real-time discussions, and
                                practice sessions.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm feature-card">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon mb-4">
                                <i class="fas fa-chart-line fa-3x text-primary"></i>
                            </div>
                            <h3 class="h4 mb-3">Track Progress</h3>
                            <p class="text-muted mb-0">Monitor your learning progress with detailed analytics and
                                performance insights.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courses -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="display-5 fw-bold mb-3">Featured Courses</h2>
                    <p class="lead text-muted">Start your learning journey with our popular courses</p>
                </div>
                <a href="courses.php" class="btn btn-outline-primary">View All Courses</a>
            </div>
            <div class="row g-4">
                <?php foreach ($featured_courses as $course): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm course-card">
                    <a href="course-details.php?id=<?php echo $course['course_id']; ?>">
                                    <img src="<?php if($course['thumbnail_url']): ?>uploads/<?php echo $course['thumbnail_url']; ?><?php else: ?>https://placehold.co/300x200/415a77/f2f2f2<?php endif ; ?>"
                                        class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>"
                                        style="height: 300px; object-fit: cover;">
                                </a>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($course['level']); ?></span>
                                <span
                                    class="badge bg-secondary"><?php echo htmlspecialchars($course['difficulty_level']); ?></span>
                            </div>
                            <h4 class="card-title mb-3"><?php echo htmlspecialchars($course['title']); ?></h4>
                            <p class="card-text text-muted mb-3">
                                <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...</p>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($course['instructor_name']); ?>
                                </span>
                                <span class="text-muted">
                                    <i class="fas fa-users me-1"></i> <?php echo $course['enrolled_count']; ?> students
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="fas fa-book me-1"></i> <?php echo $course['material_count']; ?> materials
                                </span>
                                <span class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo htmlspecialchars($course['duration']); ?>
                                </span>
                            </div>
                            <a href="course-details.php?id=<?php echo $course['course_id']; ?>"
                                class="btn btn-outline-primary w-100 mt-3">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Resources Section -->
    <section class="py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="display-5 fw-bold mb-3">Learning Resources</h2>
                    <p class="lead text-muted">Access our helpful learning materials</p>
                </div>
                <a href="resources.php" class="btn btn-outline-primary">View All Resources</a>
            </div>
            <div class="row g-4">
                <?php foreach ($resources as $resource): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm resource-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span
                                    class="badge bg-primary"><?php echo htmlspecialchars($resource['proficiency_level']); ?></span>
                                <span
                                    class="badge bg-secondary"><?php echo $file_types[$resource['file_type']]; ?></span>
                            </div>
                            <h4 class="card-title mb-3"><?php echo htmlspecialchars($resource['title']); ?></h4>
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?>...</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('M d, Y', strtotime($resource['upload_date'])); ?>
                                </small>
                                <a href="<?php echo htmlspecialchars($resource['file_path']); ?>"
                                    class="btn btn-outline-primary">
                                    <i class="fas fa-download me-1"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white text-center">
        <div class="container py-4">
            <h2 class="display-5 fw-bold mb-4">Ready to Start Learning?</h2>
            <p class="lead mb-4">Join thousands of students who are already improving their English skills with us.</p>
            <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="register.php" class="btn btn-light btn-lg">Get Started Today</a>
            <?php else: ?>
            <a href="courses.php" class="btn btn-light btn-lg">Browse Courses</a>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>

    <!-- jQuery -->
    <script src="js/lib/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>

    <!-- Custom JS -->
    <script>
    // Add animation classes on scroll
    const animateUp = document.querySelectorAll('.animate-up');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, {
        threshold: 0.1
    });

    animateUp.forEach(element => {
        observer.observe(element);
    });
    </script>
</body>

</html>