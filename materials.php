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
$category_filter = sanitize_input($_GET['category'] ?? '');
$search = sanitize_input($_GET['search'] ?? '');

// Build query
$sql = "SELECT * FROM learning_materials WHERE 1=1";
$params = [];

if ($level_filter) {
    $sql .= " AND level = ?";
    $params[] = $level_filter;
}

if ($category_filter) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

if ($search) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY level ASC, category ASC, title ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Get unique levels and categories for filters
$levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
$categories = ['Grammar', 'Vocabulary', 'Reading', 'Writing', 'Speaking', 'Listening', 'Pronunciation'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Materials - ELearning</title>
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
            <h1 class="display-4 fw-bold mb-4">Learning Materials</h1>
            <p class="lead mb-4">Access our structured learning content, interactive lessons, and practice exercises to improve your English skills.</p>
        </div>
    </section>

    <!-- Search and Filter Section -->
    <section class="search-section py-4">
        <div class="container">
            <form action="materials.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-primary"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search" 
                               placeholder="Search materials..." value="<?php echo htmlspecialchars($search); ?>">
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
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo $category; ?>
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

    <!-- Materials Section -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($materials)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h3>No materials found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($materials as $material): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm material-card">
                                <?php if ($material['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($material['thumbnail_url']); ?>" 
                                         class="card-img-top material-thumbnail" 
                                         alt="<?php echo htmlspecialchars($material['title']); ?>">
                                <?php endif; ?>
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary"><?php echo $material['level']; ?></span>
                                        <span class="badge bg-info"><?php echo $material['category']; ?></span>
                                    </div>
                                    <h5 class="card-title mb-3"><?php echo htmlspecialchars($material['title']); ?></h5>
                                    <p class="card-text text-muted mb-4"><?php echo htmlspecialchars($material['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-clock text-muted me-2"></i>
                                            <small class="text-muted"><?php echo $material['duration']; ?> min</small>
                                        </div>
                                        <div>
                                            <a href="material-details.php?id=<?php echo $material['material_id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-book-reader me-1"></i> Start Learning
                                            </a>
                                        </div>
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
    <script src="js/lib/jquery-3.6.0.min.js"></script>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html>