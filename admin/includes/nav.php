<?php
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Admin Sidebar -->
<aside class="admin-sidebar">
    
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-graduation-cap"></i>
            <span>ELearning Admin</span>
        </a>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'courses.php' || $current_page === 'course-materials.php' || $current_page === 'material-form.php' ? 'active' : ''; ?>" href="courses.php">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'materials.php' ? 'active' : ''; ?>" href="materials.php">
                <i class="fas fa-file-alt"></i>
                <span>Materials</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'resources.php' ? 'active' : ''; ?>" href="resources.php">
                <i class="fas fa-folder"></i>
                <span>Resources</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'level-test-questions.php' ? 'active' : ''; ?>" href="level-test-questions.php">
                <i class="fas fa-question"></i>
                <span>Level Test Questions</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'quizzes.php' ? 'active' : ''; ?>" href="quizzes.php">
                <i class="fas fa-question-circle"></i>
                <span>Quizzes</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'games.php' ? 'active' : ''; ?>" href="games.php">
                <i class="fas fa-gamepad"></i>
                <span>Games</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?php echo $current_page === 'wordscapes.php' ? 'active' : ''; ?>" href="wordscapes.php">
                <i class="fas fa-font"></i>
                <span>Wordscapes</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Top Navbar -->
<div class="admin-navbar">
    <div class="navbar-content">
        <button type="button" class="navbar-toggle" id="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-right">
            <form method="get" action="search.php" class="d-inline-block align-middle" style="margin-right: 10px;">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Search everything..." required style="min-width: 160px;">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <a href="../index.php" class="btn btn-outline-primary btn-sm" target="_blank">
                <i class="fas fa-external-link-alt me-1"></i>
                Visit Site
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                    <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add JavaScript for sidebar toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.admin-sidebar');
    const navbar = document.querySelector('.admin-navbar');
    const content = document.querySelector('.admin-content');
    const toggle = document.getElementById('sidebar-toggle');
    
    // Function to handle sidebar toggle
    function toggleSidebar() {
        if (window.innerWidth < 992) {
            // Mobile behavior
            sidebar.classList.toggle('show');
            // Don't add collapsed class on mobile
            sidebar.classList.remove('collapsed');
            content.classList.remove('expanded');
            navbar.classList.remove('expanded');
        } else {
            // Desktop behavior
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
            navbar.classList.toggle('expanded');
            
            // Store the state for desktop only
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        }
    }
    
    // Toggle button click event
    toggle.addEventListener('click', toggleSidebar);
    
    // Handle window resize
    let isMobile = window.innerWidth < 992;
    
    // Restore the state on page load for desktop only
    if (!isMobile) {
        const wasCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (wasCollapsed) {
            sidebar.classList.add('collapsed');
            content.classList.add('expanded');
            navbar.classList.add('expanded');
        }
    }
    
    // Add click outside listener for mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth < 992 && 
            !sidebar.contains(event.target) && 
            !toggle.contains(event.target) && 
            sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    });
    
    window.addEventListener('resize', function() {
        const wasCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        if (window.innerWidth < 992 && !isMobile) {
            // Switching to mobile
            isMobile = true;
            sidebar.classList.remove('collapsed');
            content.classList.remove('expanded');
            navbar.classList.remove('expanded');
            sidebar.classList.remove('show');
        } else if (window.innerWidth >= 992 && isMobile) {
            // Switching to desktop
            isMobile = false;
            sidebar.classList.remove('show');
            if (wasCollapsed) {
                sidebar.classList.add('collapsed');
                content.classList.add('expanded');
                navbar.classList.add('expanded');
            }
        }
    });
});</script> 