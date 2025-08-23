<?php
session_start();
require_once '../db_config.php';

// Check if player is logged in
if (!isset($_SESSION['player_id'])) {
    header('Location: ../login.html');
    exit();
}

$playerId = $_SESSION['player_id'];

try {
    $pdo = getDBConnection();
    
    // Get player data
    $playerStmt = $pdo->prepare("SELECT * FROM player WHERE game_id = :id");
    $playerStmt->execute(['id' => $playerId]);
    $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get player's projects
    $projectStmt = $pdo->prepare("SELECT * FROM project WHERE owner = :owner ORDER BY type");
    $projectStmt->execute(['owner' => $playerId]);
    $projects = $projectStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Define available projects
$availableProjects = [
    'infrastructure' => [
        'name' => 'Infrastructure Projects',
        'color' => '#00ff00',
        'projects' => [
            ['id' => 1, 'name' => 'Planetary Shield', 'cost' => 5000, 'type' => 1, 'description' => 'Protects planet from attacks'],
            ['id' => 2, 'name' => 'Space Elevator', 'cost' => 3000, 'type' => 1, 'description' => 'Reduces ship building costs'],
            ['id' => 3, 'name' => 'Terraforming Station', 'cost' => 8000, 'type' => 1, 'description' => 'Improves planet environment'],
            ['id' => 4, 'name' => 'Trade Hub', 'cost' => 4000, 'type' => 1, 'description' => 'Increases trade income'],
        ]
    ],
    'military' => [
        'name' => 'Military Projects',
        'color' => '#ff0000',
        'projects' => [
            ['id' => 11, 'name' => 'Orbital Defense Platform', 'cost' => 6000, 'type' => 2, 'description' => 'Automated defense system'],
            ['id' => 12, 'name' => 'Fleet Academy', 'cost' => 7000, 'type' => 2, 'description' => 'Trains better admirals'],
            ['id' => 13, 'name' => 'Shipyard Upgrade', 'cost' => 5000, 'type' => 2, 'description' => 'Faster ship construction'],
            ['id' => 14, 'name' => 'Missile Defense System', 'cost' => 4000, 'type' => 2, 'description' => 'Anti-missile protection'],
        ]
    ],
    'special' => [
        'name' => 'Special Projects',
        'color' => '#ffff00',
        'projects' => [
            ['id' => 21, 'name' => 'Wormhole Generator', 'cost' => 15000, 'type' => 3, 'description' => 'Instant travel between systems'],
            ['id' => 22, 'name' => 'Dyson Sphere', 'cost' => 25000, 'type' => 3, 'description' => 'Massive energy production'],
            ['id' => 23, 'name' => 'Genesis Device', 'cost' => 20000, 'type' => 3, 'description' => 'Create new planets'],
            ['id' => 24, 'name' => 'Time Dilation Field', 'cost' => 18000, 'type' => 3, 'description' => 'Speeds up production'],
        ]
    ]
];

// Handle construction actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $projectId = intval($_POST['project_id'] ?? 0);
    $projectType = intval($_POST['project_type'] ?? 0);
    
    if ($action == 'build') {
        // Find the project in available projects
        $projectCost = 0;
        $projectName = '';
        
        foreach ($availableProjects as $category) {
            foreach ($category['projects'] as $proj) {
                if ($proj['id'] == $projectId) {
                    $projectCost = $proj['cost'];
                    $projectName = $proj['name'];
                    break 2;
                }
            }
        }
        
        if ($projectCost > 0 && $player['production'] >= $projectCost) {
            // Check if already built
            $checkStmt = $pdo->prepare("SELECT * FROM project WHERE owner = :owner AND project_id = :id");
            $checkStmt->execute(['owner' => $playerId, 'id' => $projectId]);
            
            if ($checkStmt->rowCount() == 0) {
                // Deduct production points
                $pdo->prepare("UPDATE player SET production = production - :cost WHERE game_id = :id")
                    ->execute(['cost' => $projectCost, 'id' => $playerId]);
                
                // Add project
                $pdo->prepare("INSERT INTO project (owner, project_id, type) VALUES (:owner, :id, :type)")
                    ->execute(['owner' => $playerId, 'id' => $projectId, 'type' => $projectType]);
                
                $successMsg = "Successfully built $projectName!";
                
                // Reload data
                $playerStmt->execute(['id' => $playerId]);
                $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
                
                $projectStmt->execute(['owner' => $playerId]);
                $projects = $projectStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $errorMsg = "Project already built!";
            }
        } else {
            $errorMsg = "Not enough production points!";
        }
    }
}

// Create lookup for built projects
$builtProjects = [];
foreach ($projects as $proj) {
    $builtProjects[$proj['project_id']] = true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Construction - MagellanWars</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(to bottom, #000428, #004e92);
            color: #fff;
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 15px 30px;
            border-bottom: 2px solid #0099ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #00ffff, #0099ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .resource-bar {
            background: rgba(0, 0, 0, 0.7);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
        }
        
        .resource-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .resource-label {
            color: #888;
            font-size: 14px;
        }
        
        .resource-value {
            color: #00ffff;
            font-size: 18px;
            font-weight: bold;
        }
        
        .project-category {
            background: rgba(0, 0, 0, 0.8);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .category-header {
            font-size: 24px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .project-card {
            background: rgba(0, 50, 100, 0.3);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid;
            position: relative;
        }
        
        .project-card.built {
            opacity: 0.6;
            background: rgba(0, 100, 0, 0.2);
        }
        
        .project-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .project-desc {
            font-size: 13px;
            color: #aaa;
            margin-bottom: 10px;
        }
        
        .project-cost {
            font-size: 14px;
            color: #ffaa00;
            margin-bottom: 10px;
        }
        
        .btn-build {
            padding: 8px 20px;
            background: linear-gradient(45deg, #00cc00, #00ff00);
            color: #000;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .btn-build:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.4);
        }
        
        .btn-build:disabled {
            background: #555;
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .status-built {
            display: inline-block;
            padding: 5px 15px;
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
            border-radius: 15px;
            font-size: 14px;
        }
        
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid #00ff00;
            color: #66ff66;
        }
        
        .error {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff3333;
            color: #ff6666;
        }
        
        .completed-projects {
            background: rgba(0, 50, 0, 0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .completed-header {
            font-size: 18px;
            color: #00ff00;
            margin-bottom: 10px;
        }
        
        .completed-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .completed-item {
            background: rgba(0, 100, 0, 0.3);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            color: #aaffaa;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Construction Yard</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>Construction Projects</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="resource-bar">
            <div class="resource-item">
                <span class="resource-label">Available Production:</span>
                <span class="resource-value"><?php echo number_format($player['production']); ?> PP</span>
            </div>
            <div class="resource-item">
                <span class="resource-label">Completed Projects:</span>
                <span class="resource-value"><?php echo count($projects); ?></span>
            </div>
        </div>
        
        <?php if (count($projects) > 0): ?>
        <div class="completed-projects">
            <div class="completed-header">Completed Projects</div>
            <div class="completed-list">
                <?php 
                foreach ($availableProjects as $category) {
                    foreach ($category['projects'] as $proj) {
                        if (isset($builtProjects[$proj['id']])) {
                            echo '<span class="completed-item">✓ ' . $proj['name'] . '</span>';
                        }
                    }
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php foreach ($availableProjects as $categoryKey => $category): ?>
        <div class="project-category">
            <div class="category-header" style="border-color: <?php echo $category['color']; ?>; color: <?php echo $category['color']; ?>">
                <?php echo $category['name']; ?>
            </div>
            
            <div class="projects-grid">
                <?php foreach ($category['projects'] as $project): 
                    $isBuilt = isset($builtProjects[$project['id']]);
                ?>
                <div class="project-card <?php echo $isBuilt ? 'built' : ''; ?>" 
                     style="border-color: <?php echo $category['color']; ?>">
                    <div class="project-name"><?php echo $project['name']; ?></div>
                    <div class="project-desc"><?php echo $project['description']; ?></div>
                    <div class="project-cost">Cost: <?php echo number_format($project['cost']); ?> PP</div>
                    
                    <?php if ($isBuilt): ?>
                        <span class="status-built">✓ Completed</span>
                    <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="build">
                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                            <input type="hidden" name="project_type" value="<?php echo $project['type']; ?>">
                            <button type="submit" class="btn-build" 
                                    <?php echo ($player['production'] < $project['cost']) ? 'disabled' : ''; ?>>
                                Build Project
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>