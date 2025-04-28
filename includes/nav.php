<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define the path prefix based on current URL
$current_url = $_SERVER['REQUEST_URI'];
$PATH_PREFIX = strpos($current_url, 'games/wordscapes') !== false ? '../../' : '';
?>
<nav class="navbar navbar-expand-lg sticky-top navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= $PATH_PREFIX ?>index.php">
            <i class="fas fa-graduation-cap me-2"></i>
            ELearning
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>courses.php">Course Catalog</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>chatbot.php">Chatbot</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>pronunciation.php">Pronunciation Test</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>resources.php">Resources</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>dictionary.php">Dictionary</a>
                </li>
            </ul>
            <form class="d-flex me-3" id="global-search-form" action="<?= $PATH_PREFIX ?>search.php" method="get" role="search">
                <input class="form-control me-2" type="search" name="q" placeholder="Search all..." aria-label="Search" required style="min-width:170px;">
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
                        <li><a class="dropdown-item" href="<?= $PATH_PREFIX ?>profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                        <li><a class="dropdown-item" href="<?= $PATH_PREFIX ?>my-courses.php">
                                <i class="fas fa-book me-2"></i>My Courses
                            </a></li>
                        <li><a class="dropdown-item" href="<?= $PATH_PREFIX ?>level-test.php">
                                <i class="fas fa-signal me-2"></i>Level Test
                                <?php if ($profile && !empty($profile['last_test_date'])): ?>
                                <small class="text-muted d-block">
                                    Last taken: <?php echo date('M d, Y', strtotime($profile['last_test_date'])); ?>
                                </small>
                                <?php endif; ?>
                            </a></li>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li><a class="dropdown-item" href="<?= $PATH_PREFIX ?>admin/">
                                <i class="fas fa-lock me-2"></i>Admin Panel
                            </a></li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="<?= $PATH_PREFIX ?>quizzes.php">
                                <i class="fas fa-question-circle me-2"></i>Quizzes
                        </a></li>
                        <li><a class="dropdown-item" href="<?= $PATH_PREFIX ?>games/wordscapes/">
                                <i class="fas fa-gamepad me-2"></i>Games
                        </a></li>
                        <li><a class="dropdown-item" href="<?= $PATH_PREFIX ?>logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>login.php">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $PATH_PREFIX ?>register.php">Register</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>