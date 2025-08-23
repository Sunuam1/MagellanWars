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
    
    // Generate random black market items if needed
    generateBlackMarketItems($pdo);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Black market items (simulated, would normally come from database)
$blackMarketItems = [
    'technologies' => [
        ['id' => 1, 'name' => 'Quantum Drive Blueprints', 'type' => 'tech', 'cost' => 5000, 'description' => 'Advanced propulsion technology', 'rarity' => 'legendary'],
        ['id' => 2, 'name' => 'Cloaking Device', 'type' => 'tech', 'cost' => 3000, 'description' => 'Makes ships invisible to sensors', 'rarity' => 'rare'],
        ['id' => 3, 'name' => 'AI Battle Computer', 'type' => 'tech', 'cost' => 2500, 'description' => '+50% accuracy in combat', 'rarity' => 'rare'],
        ['id' => 4, 'name' => 'Nano Repair Bots', 'type' => 'tech', 'cost' => 2000, 'description' => 'Auto-repair damaged ships', 'rarity' => 'uncommon'],
    ],
    'ships' => [
        ['id' => 11, 'name' => 'Shadow Cruiser', 'type' => 'ship', 'cost' => 8000, 'description' => 'Stealth battleship with cloaking', 'rarity' => 'legendary'],
        ['id' => 12, 'name' => 'Pirate Destroyer', 'type' => 'ship', 'cost' => 3500, 'description' => 'Fast attack vessel', 'rarity' => 'rare'],
        ['id' => 13, 'name' => 'Smuggler Frigate', 'type' => 'ship', 'cost' => 2000, 'description' => 'High cargo capacity', 'rarity' => 'uncommon'],
        ['id' => 14, 'name' => 'Mercenary Fighter', 'type' => 'ship', 'cost' => 1000, 'description' => 'Cheap combat ship', 'rarity' => 'common'],
    ],
    'admirals' => [
        ['id' => 21, 'name' => 'Captain Blackbeard', 'type' => 'admiral', 'cost' => 10000, 'description' => 'Level 10 Pirate Admiral', 'rarity' => 'legendary'],
        ['id' => 22, 'name' => 'Commander Vex', 'type' => 'admiral', 'cost' => 5000, 'description' => 'Level 7 Mercenary', 'rarity' => 'rare'],
        ['id' => 23, 'name' => 'Lieutenant Zara', 'type' => 'admiral', 'cost' => 2500, 'description' => 'Level 5 Tactician', 'rarity' => 'uncommon'],
    ],
    'resources' => [
        ['id' => 31, 'name' => 'Dark Matter', 'type' => 'resource', 'cost' => 1000, 'description' => '+1000 Research Points', 'rarity' => 'rare'],
        ['id' => 32, 'name' => 'Quantum Crystals', 'type' => 'resource', 'cost' => 800, 'description' => '+500 Production Points', 'rarity' => 'uncommon'],
        ['id' => 33, 'name' => 'Alien Artifacts', 'type' => 'resource', 'cost' => 1500, 'description' => 'Random technology unlock', 'rarity' => 'rare'],
        ['id' => 34, 'name' => 'Energy Cells', 'type' => 'resource', 'cost' => 500, 'description' => '+100 Ship Production', 'rarity' => 'common'],
    ],
    'information' => [
        ['id' => 41, 'name' => 'Enemy Fleet Positions', 'type' => 'info', 'cost' => 2000, 'description' => 'Reveals all enemy fleets', 'rarity' => 'rare'],
        ['id' => 42, 'name' => 'Secret Trade Routes', 'type' => 'info', 'cost' => 1500, 'description' => '+50% trade income', 'rarity' => 'uncommon'],
        ['id' => 43, 'name' => 'Council Secrets', 'type' => 'info', 'cost' => 3000, 'description' => 'Reveals council plans', 'rarity' => 'rare'],
        ['id' => 44, 'name' => 'Planet Coordinates', 'type' => 'info', 'cost' => 1000, 'description' => 'Discover new planets', 'rarity' => 'uncommon'],
    ]
];

// Handle purchases
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'purchase') {
        $itemId = intval($_POST['item_id'] ?? 0);
        $itemType = $_POST['item_type'] ?? '';
        $itemCost = intval($_POST['item_cost'] ?? 0);
        $itemName = $_POST['item_name'] ?? '';
        
        if ($player['production'] >= $itemCost) {
            // Deduct cost
            $pdo->prepare("UPDATE player SET production = production - :cost WHERE game_id = :id")
                ->execute(['cost' => $itemCost, 'id' => $playerId]);
            
            // Apply item effects based on type
            switch ($itemType) {
                case 'tech':
                    // Add to player's tech (simplified)
                    $successMsg = "Technology '$itemName' acquired! Check your Research Lab.";
                    break;
                    
                case 'ship':
                    // Add ship to docked ships
                    $designId = 100 + $itemId; // Special black market design IDs
                    
                    // Check if black market design exists, if not create it
                    $checkDesignStmt = $pdo->prepare("SELECT * FROM class WHERE owner = :owner AND design_id = :design");
                    $checkDesignStmt->execute(['owner' => $playerId, 'design' => $designId]);
                    
                    if ($checkDesignStmt->rowCount() == 0) {
                        // Create black market ship design
                        $createDesignStmt = $pdo->prepare("
                            INSERT INTO class (owner, design_id, name, body, armor, engine, computer, shield,
                                             weapon1, weapon_number1, time, cost, black_market_design)
                            VALUES (:owner, :design, :name, 3, 3, 3, 3, 3, 3, 5, 100, :cost, 1)
                        ");
                        $createDesignStmt->execute([
                            'owner' => $playerId,
                            'design' => $designId,
                            'name' => $itemName,
                            'cost' => $itemCost
                        ]);
                    }
                    
                    // Add to docked ships
                    $checkDockedStmt = $pdo->prepare("SELECT * FROM docked_ship WHERE owner = :owner AND design_id = :design");
                    $checkDockedStmt->execute(['owner' => $playerId, 'design' => $designId]);
                    
                    if ($checkDockedStmt->rowCount() > 0) {
                        $pdo->prepare("UPDATE docked_ship SET number = number + 1 WHERE owner = :owner AND design_id = :design")
                            ->execute(['owner' => $playerId, 'design' => $designId]);
                    } else {
                        $pdo->prepare("INSERT INTO docked_ship (owner, design_id, number) VALUES (:owner, :design, 1)")
                            ->execute(['owner' => $playerId, 'design' => $designId]);
                    }
                    
                    $successMsg = "Ship '$itemName' added to your docked ships!";
                    break;
                    
                case 'admiral':
                    // Create new admiral
                    $admiralId = time() + rand(1000, 9999);
                    $level = rand(5, 10);
                    
                    $createAdmiralStmt = $pdo->prepare("
                        INSERT INTO admiral (id, owner, race, type, name, exp, level, fleet_number,
                                           armada_commanding, fleet_commanding, efficiency,
                                           offense, defense, maneuver, detection)
                        VALUES (:id, :owner, :race, 2, :name, :exp, :level, 0, 0, 10, 10, 10, 10, 10, 10)
                    ");
                    $createAdmiralStmt->execute([
                        'id' => $admiralId,
                        'owner' => $playerId,
                        'race' => rand(1, 10),
                        'name' => $itemName,
                        'exp' => $level * 1000,
                        'level' => $level
                    ]);
                    
                    $successMsg = "Admiral '$itemName' hired! Check Fleet Command.";
                    break;
                    
                case 'resource':
                    // Add resources
                    if (strpos($itemName, 'Research') !== false) {
                        $pdo->prepare("UPDATE player SET research = research + 1000 WHERE game_id = :id")
                            ->execute(['id' => $playerId]);
                    } elseif (strpos($itemName, 'Production') !== false) {
                        $pdo->prepare("UPDATE player SET production = production + 500 WHERE game_id = :id")
                            ->execute(['id' => $playerId]);
                    } elseif (strpos($itemName, 'Ship') !== false) {
                        $pdo->prepare("UPDATE player SET ship_production = ship_production + 100 WHERE game_id = :id")
                            ->execute(['id' => $playerId]);
                    }
                    $successMsg = "Resource '$itemName' acquired!";
                    break;
                    
                case 'info':
                    $successMsg = "Information '$itemName' purchased! Intelligence reports updated.";
                    break;
            }
            
            // Reload player data
            $playerStmt->execute(['id' => $playerId]);
            $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
            
        } else {
            $errorMsg = "Not enough Production Points!";
        }
    }
}

function generateBlackMarketItems($pdo) {
    // This would normally generate random items daily
    // For now, we use the static array above
}

// Rarity colors
$rarityColors = [
    'common' => '#888888',
    'uncommon' => '#00ff00',
    'rare' => '#0099ff',
    'epic' => '#ff00ff',
    'legendary' => '#ffaa00'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Black Market - MagellanWars</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(to bottom, #000000, #1a001a);
            color: #fff;
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 15px 30px;
            border-bottom: 2px solid #ff00ff;
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
            background: linear-gradient(45deg, #ff00ff, #ffaa00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .warning-banner {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            color: #ff9999;
        }
        
        .resources-bar {
            background: rgba(0, 0, 0, 0.8);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            border: 1px solid #ff00ff;
        }
        
        .resource-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .resource-label {
            color: #ff99ff;
            font-size: 14px;
        }
        
        .resource-value {
            color: #ffaa00;
            font-size: 18px;
            font-weight: bold;
        }
        
        .market-section {
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid #ff00ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 24px;
            color: #ff00ff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .item-card {
            background: rgba(50, 0, 50, 0.3);
            border: 1px solid #660066;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .item-card:hover {
            background: rgba(100, 0, 100, 0.4);
            border-color: #ff00ff;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 0, 255, 0.3);
        }
        
        .item-rarity {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .item-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .item-desc {
            color: #ccc;
            font-size: 14px;
            margin-bottom: 15px;
            min-height: 40px;
        }
        
        .item-cost {
            font-size: 20px;
            color: #ffaa00;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .btn-purchase {
            width: 100%;
            padding: 10px;
            background: linear-gradient(45deg, #ff00ff, #ff66ff);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .btn-purchase:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 0, 255, 0.4);
        }
        
        .btn-purchase:disabled {
            background: #333;
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
        
        .dealer-quote {
            text-align: center;
            padding: 20px;
            color: #ff99ff;
            font-style: italic;
            margin-bottom: 20px;
        }
        
        .legendary { background: linear-gradient(45deg, #ffaa00, #ffcc00); color: #000; }
        .epic { background: linear-gradient(45deg, #ff00ff, #ff66ff); }
        .rare { background: linear-gradient(45deg, #0099ff, #00ccff); }
        .uncommon { background: linear-gradient(45deg, #00ff00, #66ff66); color: #000; }
        .common { background: linear-gradient(45deg, #888888, #aaaaaa); color: #000; }
    </style>
</head>
<body>
    <div class="header">
        <div>🏴‍☠️ Black Market</div>
        <a href="../game_main.php" style="color: #ff00ff; text-decoration: none;">← Leave Market</a>
    </div>
    
    <div class="container">
        <h1>💀 Underground Black Market</h1>
        
        <div class="warning-banner">
            ⚠️ WARNING: All transactions are final. No refunds. Trade at your own risk!
        </div>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="resources-bar">
            <div class="resource-item">
                <span class="resource-label">Available Funds:</span>
                <span class="resource-value"><?php echo number_format($player['production']); ?> PP</span>
            </div>
            <div class="resource-item">
                <span class="resource-label">Reputation:</span>
                <span class="resource-value"><?php echo $player['honor']; ?> Honor</span>
            </div>
            <div class="resource-item">
                <span class="resource-label">Market Status:</span>
                <span class="resource-value" style="color: #00ff00;">OPEN</span>
            </div>
        </div>
        
        <div class="dealer-quote">
            "Welcome, Commander. I have... special items that might interest you. No questions asked."
        </div>
        
        <!-- Technologies Section -->
        <div class="market-section">
            <h2 class="section-title">
                <span>🔬</span>
                <span>Forbidden Technologies</span>
            </h2>
            
            <div class="items-grid">
                <?php foreach ($blackMarketItems['technologies'] as $item): ?>
                <div class="item-card">
                    <div class="item-rarity <?php echo $item['rarity']; ?>"><?php echo $item['rarity']; ?></div>
                    <div class="item-name" style="color: <?php echo $rarityColors[$item['rarity']]; ?>">
                        <?php echo $item['name']; ?>
                    </div>
                    <div class="item-desc"><?php echo $item['description']; ?></div>
                    <div class="item-cost"><?php echo number_format($item['cost']); ?> PP</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $item['type']; ?>">
                        <input type="hidden" name="item_cost" value="<?php echo $item['cost']; ?>">
                        <input type="hidden" name="item_name" value="<?php echo $item['name']; ?>">
                        <button type="submit" class="btn-purchase" 
                                <?php echo ($player['production'] < $item['cost']) ? 'disabled' : ''; ?>>
                            Purchase
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Ships Section -->
        <div class="market-section">
            <h2 class="section-title">
                <span>🚀</span>
                <span>Illegal Warships</span>
            </h2>
            
            <div class="items-grid">
                <?php foreach ($blackMarketItems['ships'] as $item): ?>
                <div class="item-card">
                    <div class="item-rarity <?php echo $item['rarity']; ?>"><?php echo $item['rarity']; ?></div>
                    <div class="item-name" style="color: <?php echo $rarityColors[$item['rarity']]; ?>">
                        <?php echo $item['name']; ?>
                    </div>
                    <div class="item-desc"><?php echo $item['description']; ?></div>
                    <div class="item-cost"><?php echo number_format($item['cost']); ?> PP</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $item['type']; ?>">
                        <input type="hidden" name="item_cost" value="<?php echo $item['cost']; ?>">
                        <input type="hidden" name="item_name" value="<?php echo $item['name']; ?>">
                        <button type="submit" class="btn-purchase" 
                                <?php echo ($player['production'] < $item['cost']) ? 'disabled' : ''; ?>>
                            Purchase
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Admirals Section -->
        <div class="market-section">
            <h2 class="section-title">
                <span>👤</span>
                <span>Mercenary Admirals</span>
            </h2>
            
            <div class="items-grid">
                <?php foreach ($blackMarketItems['admirals'] as $item): ?>
                <div class="item-card">
                    <div class="item-rarity <?php echo $item['rarity']; ?>"><?php echo $item['rarity']; ?></div>
                    <div class="item-name" style="color: <?php echo $rarityColors[$item['rarity']]; ?>">
                        <?php echo $item['name']; ?>
                    </div>
                    <div class="item-desc"><?php echo $item['description']; ?></div>
                    <div class="item-cost"><?php echo number_format($item['cost']); ?> PP</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $item['type']; ?>">
                        <input type="hidden" name="item_cost" value="<?php echo $item['cost']; ?>">
                        <input type="hidden" name="item_name" value="<?php echo $item['name']; ?>">
                        <button type="submit" class="btn-purchase" 
                                <?php echo ($player['production'] < $item['cost']) ? 'disabled' : ''; ?>>
                            Hire
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Resources Section -->
        <div class="market-section">
            <h2 class="section-title">
                <span>💎</span>
                <span>Rare Resources</span>
            </h2>
            
            <div class="items-grid">
                <?php foreach ($blackMarketItems['resources'] as $item): ?>
                <div class="item-card">
                    <div class="item-rarity <?php echo $item['rarity']; ?>"><?php echo $item['rarity']; ?></div>
                    <div class="item-name" style="color: <?php echo $rarityColors[$item['rarity']]; ?>">
                        <?php echo $item['name']; ?>
                    </div>
                    <div class="item-desc"><?php echo $item['description']; ?></div>
                    <div class="item-cost"><?php echo number_format($item['cost']); ?> PP</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $item['type']; ?>">
                        <input type="hidden" name="item_cost" value="<?php echo $item['cost']; ?>">
                        <input type="hidden" name="item_name" value="<?php echo $item['name']; ?>">
                        <button type="submit" class="btn-purchase" 
                                <?php echo ($player['production'] < $item['cost']) ? 'disabled' : ''; ?>>
                            Purchase
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Information Section -->
        <div class="market-section">
            <h2 class="section-title">
                <span>🔍</span>
                <span>Secret Intelligence</span>
            </h2>
            
            <div class="items-grid">
                <?php foreach ($blackMarketItems['information'] as $item): ?>
                <div class="item-card">
                    <div class="item-rarity <?php echo $item['rarity']; ?>"><?php echo $item['rarity']; ?></div>
                    <div class="item-name" style="color: <?php echo $rarityColors[$item['rarity']]; ?>">
                        <?php echo $item['name']; ?>
                    </div>
                    <div class="item-desc"><?php echo $item['description']; ?></div>
                    <div class="item-cost"><?php echo number_format($item['cost']); ?> PP</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $item['type']; ?>">
                        <input type="hidden" name="item_cost" value="<?php echo $item['cost']; ?>">
                        <input type="hidden" name="item_name" value="<?php echo $item['name']; ?>">
                        <button type="submit" class="btn-purchase" 
                                <?php echo ($player['production'] < $item['cost']) ? 'disabled' : ''; ?>>
                            Purchase
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>