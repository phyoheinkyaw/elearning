<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get the absolute path to the includes directory
$includes_path = dirname(__DIR__) . '/includes';
require_once $includes_path . '/db.php';
require_once $includes_path . '/functions.php';

// Function to safely get game stats with fallback
function safeGetGameStats($folder_name, $metadata, $conn) {
    // Default stats if no database or no stats defined
    $default_stats = [
        'wordscapes' => [
            ['icon' => 'fas fa-layer-group', 'label' => 'Levels', 'value' => '10+'],
            ['icon' => 'fas fa-clock', 'label' => 'Play Time', 'value' => '5-10 min'],
            ['icon' => 'fas fa-brain', 'label' => 'Difficulty', 'value' => 'Medium']
        ],
        // Add defaults for future games here
    ];
    
    // Check if we have stats for this game
    if (isset($metadata[$folder_name]['stats'])) {
        try {
            // Try to get dynamic stats from database
            $game_stats = [];
            foreach ($metadata[$folder_name]['stats'] as $stat) {
                if (is_callable($stat['value'])) {
                    try {
                        $value = $stat['value']();
                    } catch (Exception $e) {
                        // If database query fails, use a fallback value
                        $value = '-';
                    }
                } else {
                    $value = $stat['value'];
                }
                
                $game_stats[] = [
                    'icon' => $stat['icon'],
                    'label' => $stat['label'],
                    'value' => $value
                ];
            }
            return $game_stats;
        } catch (Exception $e) {
            // If anything fails, fall back to default stats
            return isset($default_stats[$folder_name]) ? $default_stats[$folder_name] : [];
        }
    } else {
        // No stats defined, use defaults if available
        return isset($default_stats[$folder_name]) ? $default_stats[$folder_name] : [];
    }
}

// Helper function to format numbers with k/M/B suffixes
function formatNumber($number) {
    if ($number < 1000) {
        return $number;
    } else if ($number < 1000000) {
        return number_format($number / 1000, $number % 1000 < 100 ? 0 : 1) . 'k';
    } else if ($number < 1000000000) {
        return number_format($number / 1000000, $number % 1000000 < 100000 ? 0 : 1) . 'M';
    } else {
        return number_format($number / 1000000000, $number % 1000000000 < 100000000 ? 0 : 1) . 'B';
    }
}

// Get current directory
$games_dir = __DIR__;

// Scan directory for game folders
$game_folders = array_filter(glob($games_dir . '/*'), 'is_dir');

// Get game metadata from database
$game_metadata = [];
try {
    $stmt = $conn->prepare("SELECT * FROM games_info WHERE is_active = 1");
    $stmt->execute();
    $games_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($games_from_db as $game) {
        $game_metadata[$game['game_folder']] = [
            'title' => $game['title'],
            'description' => $game['description'],
            'icon' => $game['icon'],
            'background' => $game['background'],
            'stats' => [
                ['icon' => 'fas fa-layer-group', 'label' => 'Levels', 'value' => function() use ($conn, $game) {
                    try {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM wordscapes_levels WHERE game_folder = ?");
                        $stmt->execute([$game['game_folder']]);
                        return $stmt->fetchColumn() ?: '10+';
                    } catch (Exception $e) {
                        return $game['difficulty'] ?? 'Medium';
                    }
                }],
                ['icon' => 'fas fa-users', 'label' => 'Players', 'value' => function() use ($conn, $game) {
                    try {
                        $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) FROM wordscapes_user_progress");
                        $stmt->execute();
                        return $stmt->fetchColumn() ?: '0';
                    } catch (Exception $e) {
                        return '0';
                    }
                }],
                ['icon' => 'fas fa-star', 'label' => 'Top Score', 'value' => function() use ($conn, $game) {
                    try {
                        $stmt = $conn->prepare("SELECT MAX(total_score) FROM wordscapes_user_progress");
                        $stmt->execute();
                        $score = $stmt->fetchColumn() ?: '0';
                        return formatNumber($score);
                    } catch (Exception $e) {
                        return '0';
                    }
                }]
            ]
        ];
    }
} catch (Exception $e) {
    // Log the error but continue with empty game_metadata
    error_log("Error fetching games: " . $e->getMessage());
}

// Page title
$page_title = 'Games Library';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/custom.css" rel="stylesheet">
    <style>
        .games-heading {
            text-align: center;
            margin: 30px 0;
            color: #333;
            position: relative;
        }
        
        .games-heading::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #3f51b5;
        }
        
        .game-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        
        .game-header {
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
        
        .game-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .game-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .game-body {
            padding: 20px;
            background: white;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .game-description {
            margin-bottom: 20px;
            color: #555;
            flex-grow: 1;
        }
        
        .game-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .game-stat {
            text-align: center;
            flex: 1;
        }
        
        .stat-icon {
            color: #3f51b5;
            margin-bottom: 5px;
            font-size: 18px;
        }
        
        .stat-value {
            font-weight: bold;
            margin-bottom: 0;
            font-size: 16px;
            color: #333;
        }
        
        .stat-label {
            font-size: 12px;
            color: #777;
        }
        
        .play-button {
            display: inline-block;
            padding: 10px 25px;
            background: #3f51b5;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
        }
        
        .play-button:hover {
            background: #303f9f;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin: 30px 0;
        }
        
        .empty-icon {
            font-size: 60px;
            color: #adb5bd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include $includes_path . '/nav.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Games</li>
                    </ol>
                </nav>
                
                <h1 class="games-heading">Educational Games</h1>
                
                <div class="row mt-5">
                    <?php 
                    $found_games = false;
                    
                    foreach ($game_folders as $folder): 
                        $folder_name = basename($folder);
                        
                        // Skip hidden folders
                        if (substr($folder_name, 0, 1) === '.') continue;
                        
                        // Skip folders that don't have entries in game_metadata (inactive or not in DB)
                        if (!isset($game_metadata[$folder_name])) continue;
                        
                        $found_games = true;
                        
                        // Get metadata from the database (we know it exists now)
                        $title = $game_metadata[$folder_name]['title'];
                        $description = $game_metadata[$folder_name]['description'];
                        $icon = $game_metadata[$folder_name]['icon'];
                        $background = $game_metadata[$folder_name]['background'];
                    ?>
                    <div class="col-md-4 mb-4">
                        <div class="game-card">
                            <div class="game-header" style="background: <?php echo $background; ?>">
                                <div class="game-icon">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <h2 class="game-title"><?php echo $title; ?></h2>
                            </div>
                            <div class="game-body">
                                <p class="game-description"><?php echo $description; ?></p>
                                
                                <?php 
                                $game_stats = safeGetGameStats($folder_name, $game_metadata, $conn);
                                if (!empty($game_stats)): 
                                ?>
                                <div class="game-stats">
                                    <?php foreach ($game_stats as $stat): ?>
                                    <div class="game-stat">
                                        <div class="stat-icon">
                                            <i class="<?php echo $stat['icon']; ?>"></i>
                                        </div>
                                        <p class="stat-value"><?php echo $stat['value']; ?></p>
                                        <p class="stat-label"><?php echo $stat['label']; ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <a href="<?php echo $folder_name; ?>" class="play-button">
                                    <i class="fas fa-play me-2"></i> Play Now
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (!$found_games): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-puzzle-piece"></i>
                            </div>
                            <h3>No Games Available</h3>
                            <p>There are currently no games in the library. Check back later for exciting educational games!</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include $includes_path . '/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 