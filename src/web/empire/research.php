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
    
    // Check if tech record exists for player
    $techStmt = $pdo->prepare("SELECT * FROM tech WHERE owner = :owner");
    $techStmt->execute(['owner' => $playerId]);
    $tech = $techStmt->fetch(PDO::FETCH_ASSOC);
    
    // If no tech record, create one
    if (!$tech) {
        $createTechStmt = $pdo->prepare("
            INSERT INTO tech (owner, info, life, matter, social, upgrade, schematics, amatter)
            VALUES (:owner, '0000000000', '0000000000', '0000000000', '0000000000', '0000000000', '0000000000', '0000000000')
        ");
        $createTechStmt->execute(['owner' => $playerId]);
        
        // Reload tech data
        $techStmt->execute(['owner' => $playerId]);
        $tech = $techStmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Tech tree definitions
$techTree = [
    'info' => [
        'name' => 'Information Science',
        'color' => '#00ffff',
        'techs' => [
            ['name' => 'Computer', 'cost' => 100, 'description' => 'Basic computing systems'],
            ['name' => 'Network', 'cost' => 250, 'description' => 'Communication networks'],
            ['name' => 'Artificial Intelligence', 'cost' => 500, 'description' => 'AI systems'],
            ['name' => 'Quantum Computing', 'cost' => 1000, 'description' => 'Advanced quantum processors'],
            ['name' => 'Neural Interface', 'cost' => 2000, 'description' => 'Direct neural connections'],
        ]
    ],
    'life' => [
        'name' => 'Life Science',
        'color' => '#00ff00',
        'techs' => [
            ['name' => 'Biology', 'cost' => 100, 'description' => 'Basic biological research'],
            ['name' => 'Medicine', 'cost' => 250, 'description' => 'Medical advancement'],
            ['name' => 'Genetics', 'cost' => 500, 'description' => 'Genetic engineering'],
            ['name' => 'Cloning', 'cost' => 1000, 'description' => 'Cloning technology'],
            ['name' => 'Immortality', 'cost' => 5000, 'description' => 'Life extension'],
        ]
    ],
    'matter' => [
        'name' => 'Matter Science',
        'color' => '#ff9900',
        'techs' => [
            ['name' => 'Physics', 'cost' => 100, 'description' => 'Basic physics'],
            ['name' => 'Chemistry', 'cost' => 250, 'description' => 'Chemical engineering'],
            ['name' => 'Nanotechnology', 'cost' => 500, 'description' => 'Molecular manipulation'],
            ['name' => 'Fusion', 'cost' => 1000, 'description' => 'Fusion power'],
            ['name' => 'Antimatter', 'cost' => 3000, 'description' => 'Antimatter technology'],
        ]
    ],
    'social' => [
        'name' => 'Social Science',
        'color' => '#ff00ff',
        'techs' => [
            ['name' => 'Psychology', 'cost' => 100, 'description' => 'Understanding behavior'],
            ['name' => 'Sociology', 'cost' => 250, 'description' => 'Social structures'],
            ['name' => 'Economics', 'cost' => 500, 'description' => 'Economic systems'],
            ['name' => 'Diplomacy', 'cost' => 750, 'description' => 'Diplomatic relations'],
            ['name' => 'Telepathy', 'cost' => 2000, 'description' => 'Mind reading'],
        ]
    ]
];

// Handle research actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $field = $_POST['field'] ?? '';
    $techIndex = intval($_POST['tech_index'] ?? 0);
    
    if ($action == 'research' && isset($techTree[$field])) {
        $techData = $techTree[$field]['techs'][$techIndex] ?? null;
        
        if ($techData) {
            $cost = $techData['cost'];
            
            // Check if player has enough research points
            if ($player['research'] >= $cost) {
                // Check if already researched
                $currentTech = str_split($tech[$field]);
                
                if ($currentTech[$techIndex] == '0') {
                    // Deduct research points
                    $pdo->prepare("UPDATE player SET research = research - :cost WHERE game_id = :id")
                        ->execute(['cost' => $cost, 'id' => $playerId]);
                    
                    // Mark tech as researched
                    $currentTech[$techIndex] = '1';
                    $newTechString = implode('', $currentTech);
                    
                    $pdo->prepare("UPDATE tech SET $field = :tech WHERE owner = :owner")
                        ->execute(['tech' => $newTechString, 'owner' => $playerId]);
                    
                    $successMsg = "Successfully researched " . $techData['name'] . "!";
                    
                    // Reload data
                    $playerStmt->execute(['id' => $playerId]);
                    $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $techStmt->execute(['owner' => $playerId]);
                    $tech = $techStmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $errorMsg = "Technology already researched!";
                }
            } else {
                $errorMsg = "Not enough research points! Need " . number_format($cost) . " RP";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Research Lab - MagellanWars</title>
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
        
        .tech-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .tech-category {
            background: rgba(0, 0, 0, 0.8);
            border-radius: 10px;
            padding: 20px;
        }
        
        .category-header {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }
        
        .tech-item {
            background: rgba(0, 50, 100, 0.3);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 3px solid;
        }
        
        .tech-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .tech-desc {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 10px;
        }
        
        .tech-cost {
            font-size: 14px;
            color: #ffaa00;
            margin-bottom: 10px;
        }
        
        .tech-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .status-researched {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
        }
        
        .status-available {
            background: rgba(0, 150, 255, 0.2);
            color: #0099ff;
        }
        
        .status-locked {
            background: rgba(100, 100, 100, 0.2);
            color: #666;
        }
        
        .btn-research {
            padding: 8px 15px;
            background: linear-gradient(45deg, #0066cc, #0099ff);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .btn-research:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 150, 255, 0.4);
        }
        
        .btn-research:disabled {
            background: #555;
            cursor: not-allowed;
            opacity: 0.5;
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
    </style>
</head>
<body>
    <div class="header">
        <div>Research Laboratory</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>Research Lab</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="resource-bar">
            <div class="resource-item">
                <span class="resource-label">Research Points:</span>
                <span class="resource-value"><?php echo number_format($player['research']); ?> RP</span>
            </div>
            <div class="resource-item">
                <span class="resource-label">Research per Turn:</span>
                <span class="resource-value"><?php echo number_format($player['last_turn_research'] ?? 100); ?> RP</span>
            </div>
        </div>
        
        <div class="tech-categories">
            <?php foreach ($techTree as $fieldKey => $category): ?>
            <div class="tech-category">
                <div class="category-header" style="border-color: <?php echo $category['color']; ?>; color: <?php echo $category['color']; ?>">
                    <?php echo $category['name']; ?>
                </div>
                
                <?php 
                $currentTech = str_split($tech[$fieldKey]);
                foreach ($category['techs'] as $index => $techItem): 
                    $isResearched = ($currentTech[$index] ?? '0') == '1';
                    $previousResearched = $index == 0 || ($currentTech[$index - 1] ?? '0') == '1';
                    $canResearch = !$isResearched && $previousResearched;
                ?>
                <div class="tech-item" style="border-color: <?php echo $category['color']; ?>">
                    <div class="tech-name"><?php echo $techItem['name']; ?></div>
                    <div class="tech-desc"><?php echo $techItem['description']; ?></div>
                    <div class="tech-cost">Cost: <?php echo number_format($techItem['cost']); ?> RP</div>
                    
                    <?php if ($isResearched): ?>
                        <span class="tech-status status-researched">✓ Researched</span>
                    <?php elseif ($canResearch): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="research">
                            <input type="hidden" name="field" value="<?php echo $fieldKey; ?>">
                            <input type="hidden" name="tech_index" value="<?php echo $index; ?>">
                            <button type="submit" class="btn-research" 
                                    <?php echo ($player['research'] < $techItem['cost']) ? 'disabled' : ''; ?>>
                                Research
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="tech-status status-locked">🔒 Locked</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>