<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base URL - the root of the application
$base_url = '/el/';

// Define the path prefix based on current URL
$current_url = $_SERVER['REQUEST_URI'];
$document_root = $_SERVER['DOCUMENT_ROOT'];
$script_filename = $_SERVER['SCRIPT_FILENAME'];

// Get the relative path of the current script from document root
$script_path = str_replace($document_root, '', $script_filename);
$script_path = str_replace('\\', '/', $script_path); // Normalize slashes

// Calculate the directory depth
$depth = substr_count($script_path, '/') - 1;

// Set path prefix based on directory depth
$PATH_PREFIX = '';
if ($depth > 0) {
    $PATH_PREFIX = str_repeat('../', $depth);
}

// Option to use absolute URLs with base_url instead of relative paths
$use_absolute_urls = true;

// Helper function to generate URLs
function site_url($path = '') {
    global $base_url, $PATH_PREFIX, $use_absolute_urls;
    return $use_absolute_urls ? $base_url . ltrim($path, '/') : $PATH_PREFIX . $path;
}
?>
<nav class="navbar navbar-expand-lg sticky-top navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= site_url('index.php') ?>">
            <i class="fas fa-graduation-cap me-2"></i>
            ELearning
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('index.php') ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('courses.php') ?>">Course Catalog</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('chatbot.php') ?>">Chatbot</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('pronunciation.php') ?>">Pronunciation Test</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('resources.php') ?>">Resources</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('dictionary.php') ?>">Dictionary</a>
                </li>
            </ul>
            <form class="d-flex me-3 search-input-container" id="global-search-form" action="<?= site_url('search.php') ?>" method="get" role="search">
                <input class="form-control me-2" type="search" name="q" placeholder="Search all..." aria-label="Search" 
                       required style="min-width:170px;" data-autosuggest="true" autocomplete="off">
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
            </form>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                    // Get user's profile picture, full name and proficiency level
                    try {
                        $stmt = $conn->prepare("
                            SELECT up.profile_picture, up.proficiency_level, up.full_name,
                                   (SELECT test_date 
                                    FROM level_test_results 
                                    WHERE user_id = ? 
                                    ORDER BY test_date DESC 
                                    LIMIT 1) as last_test_date
                            FROM user_profiles up 
                            WHERE up.user_id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                        $profile = $stmt->fetch();
                    } catch(PDOException $e) {
                        $profile = false;
                    }
                    ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                        data-bs-toggle="dropdown">
                        <?php if ($profile && !empty($profile['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($profile['profile_picture']); ?>" alt="Profile"
                            class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                        <?php else: ?>
                        <i class="fas fa-user-circle fa-lg me-2"></i>
                        <?php endif; ?>
                        <div>
                            <span
                                class="me-2"><?php echo htmlspecialchars($profile && !empty($profile['full_name']) ? $profile['full_name'] : $_SESSION['username']); ?></span>
                            <?php if ($profile && !empty($profile['proficiency_level'])): ?>
                            <span
                                class="badge bg-primary"><?php echo htmlspecialchars($profile['proficiency_level']); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= site_url('profile.php') ?>">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                        <li><a class="dropdown-item" href="<?= site_url('my-courses.php') ?>">
                                <i class="fas fa-book me-2"></i>My Courses
                            </a></li>
                        <li><a class="dropdown-item" href="<?= site_url('level-test.php') ?>">
                                <i class="fas fa-signal me-2"></i>Level Test
                                <?php if ($profile && !empty($profile['last_test_date'])): ?>
                                <small class="text-muted d-block">
                                    Last taken: <?php echo date('M d, Y', strtotime($profile['last_test_date'])); ?>
                                </small>
                                <?php endif; ?>
                            </a></li>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li><a class="dropdown-item" href="<?= site_url('admin/') ?>">
                                <i class="fas fa-lock me-2"></i>Admin Panel
                            </a></li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="<?= site_url('quizzes.php') ?>">
                                <i class="fas fa-question-circle me-2"></i>Quizzes
                        </a></li>
                        <li><a class="dropdown-item" href="<?= site_url('games/') ?>">
                                <i class="fas fa-gamepad me-2"></i>Games
                        </a></li>
                        <li><a class="dropdown-item" href="<?= site_url('logout.php') ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('login.php') ?>">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= site_url('register.php') ?>">Register</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>