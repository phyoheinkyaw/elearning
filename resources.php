<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get user's level if logged in
$user_level = null;
if (is_logged_in()) {
    try {
        $stmt = $conn->prepare("SELECT proficiency_level FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch();
        $user_level = $profile['proficiency_level'] ?? null;
    } catch(PDOException $e) {
        error_log('Error fetching user level: ' . $e->getMessage());
    }
}

// Get filter parameters
$level_filter = sanitize_input($_GET['level'] ?? '');
$type_filter = sanitize_input($_GET['type'] ?? '');
$search = sanitize_input($_GET['search'] ?? '');

try {
    // Build query
    $sql = "
        SELECT r.* 
        FROM resource_library r 
        WHERE 1=1
    ";
    $params = [];

    if ($level_filter) {
        $sql .= " AND r.proficiency_level = ?";
        $params[] = $level_filter;
    }

    if ($type_filter !== '') {
        $sql .= " AND r.file_type = ?";
        $params[] = $type_filter;
    }

    if ($search) {
        $sql .= " AND (r.title LIKE ? OR r.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY r.proficiency_level ASC, r.title ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log('Error in resources.php: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while loading resources. Please try again later.';
    $resources = [];
}

// Get unique levels and types for filters
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
$types = [0 => 'PDF', 1 => 'E-book', 2 => 'Worksheet'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources - ELearning</title>
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
            <h1 class="display-4 fw-bold mb-4 text-light">Learning Resources</h1>
            <p class="lead mb-4">Access our comprehensive collection of study materials, guides, and practice exercises
                to enhance your English learning journey.</p>
        </div>
    </section>

    <!-- Search and Filter Section -->
    <section class="search-section py-4">
        <div class="container">
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form action="resources.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-primary"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search"
                            placeholder="Search resources..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="level">
                        <option value="">All Levels</option>
                        <?php foreach ($levels as $level): ?>
                        <option value="<?php echo $level; ?>" <?php echo $level_filter === $level ? 'selected' : ''; ?>>
                            <?php echo $level; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <?php foreach ($types as $value => $type): ?>
                        <option value="<?php echo $value; ?>"
                            <?php echo $type_filter === (string)$value ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
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

    <!-- Resources Section -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($resources)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3>No resources found</h3>
                <p class="text-muted">Try adjusting your search or filter criteria</p>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($resources as $resource): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-primary"><?php echo $resource['proficiency_level']; ?></span>
                                <span class="badge bg-info"><?php echo $types[$resource['file_type']]; ?></span>
                            </div>
                            <h5 class="card-title mb-3"><?php echo htmlspecialchars($resource['title']); ?></h5>
                            <p class="card-text text-muted mb-4">
                                <?php echo htmlspecialchars($resource['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('M d, Y', strtotime($resource['upload_date'])); ?>
                                </small>
                                <a href="<?php echo htmlspecialchars($resource['file_path']); ?>"
                                    class="btn btn-outline-primary" target="_blank">
                                    <i class="fas fa-download me-1"></i> Download
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

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- jQuery -->
    <script src="/js/lib/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html>