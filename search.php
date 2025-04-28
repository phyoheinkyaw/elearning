<?php
session_start();
require_once 'includes/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [
    'courses' => [],
    'quizzes' => [],
    'resources' => []
];

// Helper: map CEFR level to quiz difficulty
function levelToDifficulty($level) {
    if (in_array($level, ['A1', 'A2'])) return 0;
    if (in_array($level, ['B1', 'B2'])) return 1;
    if (in_array($level, ['C1', 'C2'])) return 2;
    return null;
}

// Get user's proficiency level if logged in
$user_level = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT proficiency_level FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
    $user_level = $profile['proficiency_level'] ?? null;
}

if ($q !== '') {
    // Courses (filter by user level)
    if ($user_level) {
        $stmt = $conn->prepare("SELECT course_id AS id, title, description FROM courses WHERE (title LIKE ? OR description LIKE ?) AND level = ? LIMIT 10");
        $stmt->execute(["%$q%", "%$q%", $user_level]);
    } else {
        $stmt = $conn->prepare("SELECT course_id AS id, title, description FROM courses WHERE title LIKE ? OR description LIKE ? LIMIT 10");
        $stmt->execute(["%$q%", "%$q%"]);
    }
    $results['courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Quizzes (filter by mapped difficulty)
    $difficulty = $user_level ? levelToDifficulty($user_level) : null;
    if ($difficulty !== null) {
        $stmt = $conn->prepare("SELECT quiz_id AS id, title, description FROM quizzes WHERE (title LIKE ? OR description LIKE ?) AND difficulty_level = ? LIMIT 10");
        $stmt->execute(["%$q%", "%$q%", $difficulty]);
    } else {
        $stmt = $conn->prepare("SELECT quiz_id AS id, title, description FROM quizzes WHERE title LIKE ? OR description LIKE ? LIMIT 10");
        $stmt->execute(["%$q%", "%$q%"]);
    }
    $results['quizzes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resources (filter by user level)
    if ($user_level) {
        $stmt = $conn->prepare("SELECT resource_id AS id, title, description FROM resource_library WHERE (title LIKE ? OR description LIKE ?) AND proficiency_level = ? LIMIT 10");
        $stmt->execute(["%$q%", "%$q%", $user_level]);
    } else {
        $stmt = $conn->prepare("SELECT resource_id AS id, title, description FROM resource_library WHERE title LIKE ? OR description LIKE ? LIMIT 10");
        $stmt->execute(["%$q%", "%$q%"]);
    }
    $results['resources'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - ELearning</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/chatbot.css">
    <link rel="stylesheet" href="css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    // Dictionary API search handler
    function searchDictionary(q) {
        if (!q) return;
        const dictResults = document.getElementById('dictionary-results');
        dictResults.innerHTML = '<div class="text-info">Searching dictionary...</div>';
        fetch(`https://api.dictionaryapi.dev/api/v2/entries/en/${encodeURIComponent(q)}`)
            .then(response => response.json())
            .then(data => {
                if (data.title && data.title === 'No Definitions Found') {
                    dictResults.innerHTML = `<div class="alert alert-warning">No definitions found for "${q}".</div>`;
                } else {
                    let html = '';
                    data.forEach(entry => {
                        html += `<div class='card mb-3'><div class='card-body'>`;
                        html += `<h5 class='card-title'>${entry.word}</h5>`;
                        if (entry.phonetic) html += `<p class='text-muted'>Phonetic: ${entry.phonetic}</p>`;
                        entry.meanings.forEach(meaning => {
                            html += `<h6>${meaning.partOfSpeech}</h6>`;
                            meaning.definitions.forEach((def, i) => {
                                html += `<p><b>${i+1}.</b> ${def.definition}`;
                                if (def.example) html += `<br><em>Example: ${def.example}</em>`;
                                html += `</p>`;
                            });
                        });
                        html += `</div></div>`;
                    });
                    dictResults.innerHTML = html;
                }
            })
            .catch(() => {
                dictResults.innerHTML = `<div class="alert alert-danger">Failed to fetch dictionary results.</div>`;
            });
    }
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const q = urlParams.get('q');
        if (q) searchDictionary(q);
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    });
    </script>
</head>
<body>
<?php include 'includes/nav.php'; ?>
<div class="container py-4">
    <form id="searchForm" class="mb-4" method="get" action="search.php">
        <div class="input-group input-group-lg">
            <input type="text" name="q" id="searchInput" class="form-control" placeholder="Search courses, quizzes, resources, dictionary..." value="<?= htmlspecialchars($q) ?>" required>
            <button class="btn btn-primary" type="submit"><i class="fas fa-search me-2"></i>Search</button>
        </div>
    </form>
    <h2 class="mb-4">Search Results for "<?= htmlspecialchars($q) ?>"</h2>
    <?php if ($q === ''): ?>
        <div class="alert alert-info">Please enter a search term.</div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-6 mb-4">
                <h4><i class="fas fa-book me-2"></i>Courses</h4>
                <?php if (count($results['courses'])): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($results['courses'] as $item): ?>
                            <li class="list-group-item">
                                <a href="course.php?id=<?= $item['id'] ?>"><b><?= htmlspecialchars($item['title']) ?></b></a><br>
                                <span class="text-muted small"><?= htmlspecialchars($item['description']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">No courses found.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-4">
                <h4><i class="fas fa-question-circle me-2"></i>Quizzes</h4>
                <?php if (count($results['quizzes'])): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($results['quizzes'] as $item): ?>
                            <li class="list-group-item">
                                <a href="quiz.php?id=<?= $item['id'] ?>"><b><?= htmlspecialchars($item['title']) ?></b></a><br>
                                <span class="text-muted small"><?= htmlspecialchars($item['description']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">No quizzes found.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-4">
                <h4><i class="fas fa-link me-2"></i>Resources</h4>
                <?php if (count($results['resources'])): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($results['resources'] as $item): ?>
                            <li class="list-group-item">
                                <a href="resource.php?id=<?= $item['id'] ?>"><b><?= htmlspecialchars($item['title']) ?></b></a><br>
                                <span class="text-muted small"><?= htmlspecialchars($item['description']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">No resources found.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-4">
                <h4><i class="fas fa-book me-2"></i>Dictionary</h4>
                <div id="dictionary-results"></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
</html>
