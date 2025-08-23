<?php
session_start();
require_once '../db_config.php';

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
    
    // Get player's ship designs
    $classStmt = $pdo->prepare("
        SELECT * FROM class 
        WHERE owner = :owner
        ORDER BY design_id
    ");
    $classStmt->execute(['owner' => $playerId]);
    $designs = $classStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ship building queue
    $queueStmt = $pdo->prepare("
        SELECT q.*, c.name as class_name
        FROM ship_building_q q
        JOIN class c ON q.design_id = c.design_id AND q.owner = c.owner
        WHERE q.owner = :owner
        ORDER BY q.time_order
    ");
    $queueStmt->execute(['owner' => $playerId]);
    $buildQueue = $queueStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Component definitions
$components = [
    'body' => [
        ['id' => 1, 'name' => 'Light Hull', 'cost' => 100, 'hp' => 50, 'space' => 5],
        ['id' => 2, 'name' => 'Medium Hull', 'cost' => 250, 'hp' => 100, 'space' => 10],
        ['id' => 3, 'name' => 'Heavy Hull', 'cost' => 500, 'hp' => 200, 'space' => 20],
        ['id' => 4, 'name' => 'Battleship Hull', 'cost' => 1000, 'hp' => 400, 'space' => 40],
        ['id' => 5, 'name' => 'Dreadnought Hull', 'cost' => 2000, 'hp' => 800, 'space' => 80],
    ],
    'armor' => [
        ['id' => 1, 'name' => 'Titanium Armor', 'cost' => 50, 'defense' => 5],
        ['id' => 2, 'name' => 'Neutronium Armor', 'cost' => 100, 'defense' => 10],
        ['id' => 3, 'name' => 'Quantum Armor', 'cost' => 200, 'defense' => 20],
    ],
    'engine' => [
        ['id' => 1, 'name' => 'Ion Drive', 'cost' => 75, 'speed' => 5],
        ['id' => 2, 'name' => 'Fusion Drive', 'cost' => 150, 'speed' => 10],
        ['id' => 3, 'name' => 'Warp Drive', 'cost' => 300, 'speed' => 20],
    ],
    'weapon' => [
        ['id' => 1, 'name' => 'Laser Cannon', 'cost' => 100, 'damage' => 10, 'space' => 1],
        ['id' => 2, 'name' => 'Plasma Cannon', 'cost' => 200, 'damage' => 20, 'space' => 2],
        ['id' => 3, 'name' => 'Photon Torpedo', 'cost' => 300, 'damage' => 30, 'space' => 3],
        ['id' => 4, 'name' => 'Antimatter Missile', 'cost' => 500, 'damage' => 50, 'space' => 5],
    ],
    'shield' => [
        ['id' => 1, 'name' => 'Deflector Shield', 'cost' => 100, 'shield' => 25],
        ['id' => 2, 'name' => 'Force Shield', 'cost' => 200, 'shield' => 50],
        ['id' => 3, 'name' => 'Quantum Shield', 'cost' => 400, 'shield' => 100],
    ],
    'computer' => [
        ['id' => 1, 'name' => 'Basic Computer', 'cost' => 50, 'accuracy' => 5],
        ['id' => 2, 'name' => 'Advanced Computer', 'cost' => 100, 'accuracy' => 10],
        ['id' => 3, 'name' => 'Quantum Computer', 'cost' => 200, 'accuracy' => 20],
    ]
];

// Handle ship design actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_design') {
        $designName = trim($_POST['design_name'] ?? '');
        $body = intval($_POST['body'] ?? 1);
        $armor = intval($_POST['armor'] ?? 1);
        $engine = intval($_POST['engine'] ?? 1);
        $shield = intval($_POST['shield'] ?? 1);
        $computer = intval($_POST['computer'] ?? 1);
        $weapon1 = intval($_POST['weapon1'] ?? 0);
        $weaponNum1 = intval($_POST['weapon_num1'] ?? 0);
        
        if ($designName) {
            // Calculate cost
            $totalCost = 0;
            $totalCost += $components['body'][$body-1]['cost'] ?? 0;
            $totalCost += $components['armor'][$armor-1]['cost'] ?? 0;
            $totalCost += $components['engine'][$engine-1]['cost'] ?? 0;
            $totalCost += $components['shield'][$shield-1]['cost'] ?? 0;
            $totalCost += $components['computer'][$computer-1]['cost'] ?? 0;
            if ($weapon1 > 0) {
                $totalCost += ($components['weapon'][$weapon1-1]['cost'] ?? 0) * $weaponNum1;
            }
            
            // Get next design ID
            $maxDesignId = $pdo->query("SELECT MAX(design_id) FROM class WHERE owner = $playerId")->fetchColumn();
            $newDesignId = ($maxDesignId ?? 0) + 1;
            
            // Create design
            $createDesignStmt = $pdo->prepare("
                INSERT INTO class (
                    owner, design_id, name, body, armor, engine, computer, shield,
                    weapon1, weapon_number1, weapon2, weapon_number2, weapon3, weapon_number3,
                    weapon4, weapon_number4, weapon5, weapon_number5, weapon6, weapon_number6,
                    weapon7, weapon_number7, weapon8, weapon_number8, weapon9, weapon_number9,
                    weapon10, weapon_number10, device1, device2, device3, device4, device5,
                    device6, device7, device8, time, cost
                ) VALUES (
                    :owner, :design_id, :name, :body, :armor, :engine, :computer, :shield,
                    :w1, :wn1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                    0, 0, 0, 0, 0, 0, 0, 0, :time, :cost
                )
            ");
            $createDesignStmt->execute([
                'owner' => $playerId,
                'design_id' => $newDesignId,
                'name' => $designName,
                'body' => $body,
                'armor' => $armor,
                'engine' => $engine,
                'computer' => $computer,
                'shield' => $shield,
                'w1' => $weapon1,
                'wn1' => $weaponNum1,
                'time' => 100, // Build time
                'cost' => $totalCost
            ]);
            
            $successMsg = "Ship design '$designName' created successfully! Cost: " . number_format($totalCost) . " PP";
            
            // Reload designs
            $classStmt->execute(['owner' => $playerId]);
            $designs = $classStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    if ($action == 'build_ships') {
        $designId = intval($_POST['design_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        
        if ($designId > 0 && $quantity > 0) {
            // Get design cost
            $designCostStmt = $pdo->prepare("SELECT cost FROM class WHERE owner = :owner AND design_id = :design");
            $designCostStmt->execute(['owner' => $playerId, 'design' => $designId]);
            $designCost = $designCostStmt->fetchColumn();
            
            $totalCost = $designCost * $quantity;
            
            if ($player['ship_production'] >= $totalCost) {
                // Deduct production
                $pdo->prepare("UPDATE player SET ship_production = ship_production - :cost WHERE game_id = :id")
                    ->execute(['cost' => $totalCost, 'id' => $playerId]);
                
                // Add to build queue
                $timeOrder = time();
                $pdo->prepare("INSERT INTO ship_building_q (owner, design_id, number, time_order) VALUES (:owner, :design, :num, :time)")
                    ->execute(['owner' => $playerId, 'design' => $designId, 'num' => $quantity, 'time' => $timeOrder]);
                
                // Add to docked ships (instant build for now)
                $checkDockedStmt = $pdo->prepare("SELECT * FROM docked_ship WHERE owner = :owner AND design_id = :design");
                $checkDockedStmt->execute(['owner' => $playerId, 'design' => $designId]);
                
                if ($checkDockedStmt->rowCount() > 0) {
                    $pdo->prepare("UPDATE docked_ship SET number = number + :num WHERE owner = :owner AND design_id = :design")
                        ->execute(['num' => $quantity, 'owner' => $playerId, 'design' => $designId]);
                } else {
                    $pdo->prepare("INSERT INTO docked_ship (owner, design_id, number) VALUES (:owner, :design, :num)")
                        ->execute(['owner' => $playerId, 'design' => $designId, 'num' => $quantity]);
                }
                
                $successMsg = "Building $quantity ships! Total cost: " . number_format($totalCost) . " MP";
                
                // Reload data
                $playerStmt->execute(['id' => $playerId]);
                $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
                
                $queueStmt->execute(['owner' => $playerId]);
                $buildQueue = $queueStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $errorMsg = "Not enough Military Production! Need " . number_format($totalCost) . " MP";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ship Design - MagellanWars</title>
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
            background: linear-gradient(45deg, #00ff00, #00cc00);
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
            color: #00ff00;
            font-size: 18px;
            font-weight: bold;
        }
        
        .design-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #00cc00;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 24px;
            color: #00ff00;
            margin-bottom: 20px;
        }
        
        .component-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .component-group {
            background: rgba(0, 50, 0, 0.2);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #006600;
        }
        
        .component-label {
            color: #00ff00;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        select {
            width: 100%;
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #00cc00;
            color: #fff;
            border-radius: 5px;
        }
        
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #00cc00;
            color: #fff;
            border-radius: 5px;
        }
        
        .btn {
            padding: 10px 20px;
            background: linear-gradient(45deg, #00cc00, #00ff00);
            color: #000;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.4);
        }
        
        .designs-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .design-card {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #00cc00;
            border-radius: 10px;
            padding: 15px;
        }
        
        .design-name {
            font-size: 18px;
            color: #00ff00;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .design-stats {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 15px;
        }
        
        .design-cost {
            color: #ffaa00;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .build-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .build-form input {
            width: 80px;
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
        
        .queue-section {
            background: rgba(0, 50, 0, 0.1);
            border: 1px solid #006600;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .queue-item {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            background: rgba(0, 0, 0, 0.3);
            margin-bottom: 5px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Ship Design & Construction</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>🚀 Ship Design Facility</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="resource-bar">
            <div class="resource-item">
                <span class="resource-label">Military Production:</span>
                <span class="resource-value"><?php echo number_format($player['ship_production']); ?> MP</span>
            </div>
            <div class="resource-item">
                <span class="resource-label">Active Designs:</span>
                <span class="resource-value"><?php echo count($designs); ?></span>
            </div>
            <div class="resource-item">
                <span class="resource-label">Ships in Queue:</span>
                <span class="resource-value"><?php echo count($buildQueue); ?></span>
            </div>
        </div>
        
        <?php if (count($buildQueue) > 0): ?>
        <div class="queue-section">
            <h3 style="color: #00ff00; margin-bottom: 10px;">Build Queue</h3>
            <?php foreach ($buildQueue as $item): ?>
            <div class="queue-item">
                <span><?php echo htmlspecialchars($item['class_name']); ?> x<?php echo $item['number']; ?></span>
                <span style="color: #00ff00;">Building...</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="design-section">
            <h2 class="section-title">Create New Ship Design</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_design">
                
                <div style="margin-bottom: 20px;">
                    <label style="color: #00ff00;">Design Name:</label>
                    <input type="text" name="design_name" required placeholder="Enter ship class name..." style="max-width: 400px;">
                </div>
                
                <div class="component-grid">
                    <div class="component-group">
                        <div class="component-label">Hull Type</div>
                        <select name="body">
                            <?php foreach ($components['body'] as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>">
                                <?php echo $comp['name']; ?> (<?php echo $comp['cost']; ?> PP, <?php echo $comp['hp']; ?> HP)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="component-group">
                        <div class="component-label">Armor</div>
                        <select name="armor">
                            <?php foreach ($components['armor'] as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>">
                                <?php echo $comp['name']; ?> (<?php echo $comp['cost']; ?> PP, +<?php echo $comp['defense']; ?> DEF)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="component-group">
                        <div class="component-label">Engine</div>
                        <select name="engine">
                            <?php foreach ($components['engine'] as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>">
                                <?php echo $comp['name']; ?> (<?php echo $comp['cost']; ?> PP, Speed <?php echo $comp['speed']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="component-group">
                        <div class="component-label">Shield</div>
                        <select name="shield">
                            <?php foreach ($components['shield'] as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>">
                                <?php echo $comp['name']; ?> (<?php echo $comp['cost']; ?> PP, <?php echo $comp['shield']; ?> Shield)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="component-group">
                        <div class="component-label">Computer</div>
                        <select name="computer">
                            <?php foreach ($components['computer'] as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>">
                                <?php echo $comp['name']; ?> (<?php echo $comp['cost']; ?> PP, +<?php echo $comp['accuracy']; ?> ACC)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="component-group">
                        <div class="component-label">Primary Weapon</div>
                        <select name="weapon1">
                            <option value="0">None</option>
                            <?php foreach ($components['weapon'] as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>">
                                <?php echo $comp['name']; ?> (<?php echo $comp['cost']; ?> PP, <?php echo $comp['damage']; ?> DMG)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="margin-top: 5px;">
                            <label style="color: #aaa; font-size: 12px;">Quantity:</label>
                            <input type="number" name="weapon_num1" min="0" max="10" value="1" style="width: 60px;">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn">Create Design</button>
            </form>
        </div>
        
        <?php if (count($designs) > 0): ?>
        <h2 style="color: #00ff00; margin-bottom: 20px;">Your Ship Designs</h2>
        <div class="designs-list">
            <?php foreach ($designs as $design): ?>
            <div class="design-card">
                <div class="design-name"><?php echo htmlspecialchars($design['name']); ?></div>
                <div class="design-stats">
                    Hull: Level <?php echo $design['body']; ?><br>
                    Armor: Level <?php echo $design['armor']; ?><br>
                    Engine: Level <?php echo $design['engine']; ?><br>
                    Shield: Level <?php echo $design['shield']; ?><br>
                    Computer: Level <?php echo $design['computer']; ?><br>
                    <?php if ($design['weapon1'] > 0): ?>
                    Weapons: <?php echo $design['weapon_number1']; ?>x Level <?php echo $design['weapon1']; ?>
                    <?php endif; ?>
                </div>
                <div class="design-cost">Cost: <?php echo number_format($design['cost']); ?> MP per ship</div>
                
                <form method="POST" class="build-form">
                    <input type="hidden" name="action" value="build_ships">
                    <input type="hidden" name="design_id" value="<?php echo $design['design_id']; ?>">
                    <input type="number" name="quantity" min="1" max="100" value="1" placeholder="Qty">
                    <button type="submit" class="btn" style="padding: 8px 15px; font-size: 14px;">Build</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>