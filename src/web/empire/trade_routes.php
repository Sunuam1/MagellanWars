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
    
    // Get player's planets with trade routes
    $planetStmt = $pdo->prepare("
        SELECT p.*, 
               c.name as cluster_name,
               p1.name as trade1_name,
               p2.name as trade2_name,
               p3.name as trade3_name
        FROM planet p
        LEFT JOIN cluster c ON p.cluster = c.id
        LEFT JOIN planet p1 ON p.commerce_with_1 = p1.id
        LEFT JOIN planet p2 ON p.commerce_with_2 = p2.id
        LEFT JOIN planet p3 ON p.commerce_with_3 = p3.id
        WHERE p.owner = :owner
        ORDER BY p.order_ ASC
    ");
    $planetStmt->execute(['owner' => $playerId]);
    $planets = $planetStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all potential trade partners (other players' planets)
    $tradePlanetsStmt = $pdo->prepare("
        SELECT p.*, pl.name as owner_name, c.name as cluster_name
        FROM planet p
        JOIN player pl ON p.owner = pl.game_id
        LEFT JOIN cluster c ON p.cluster = c.id
        WHERE p.owner != :owner
        ORDER BY p.owner, p.name
    ");
    $tradePlanetsStmt->execute(['owner' => $playerId]);
    $tradePlanets = $tradePlanetsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle trade route actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $planetId = intval($_POST['planet_id'] ?? 0);
    $slot = intval($_POST['slot'] ?? 0);
    $targetPlanetId = intval($_POST['target_planet'] ?? 0);
    
    if ($action == 'establish' && $planetId && $slot >= 1 && $slot <= 3) {
        // Verify planet ownership
        $verifyStmt = $pdo->prepare("SELECT * FROM planet WHERE id = :id AND owner = :owner");
        $verifyStmt->execute(['id' => $planetId, 'owner' => $playerId]);
        
        if ($verifyStmt->rowCount() > 0) {
            $columnName = "commerce_with_$slot";
            
            // Update trade route
            $updateStmt = $pdo->prepare("UPDATE planet SET $columnName = :target WHERE id = :id");
            $updateStmt->execute(['target' => $targetPlanetId, 'id' => $planetId]);
            
            $successMsg = "Trade route established successfully!";
            
            // Reload planets
            $planetStmt->execute(['owner' => $playerId]);
            $planets = $planetStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errorMsg = "Invalid planet selection!";
        }
    }
    
    if ($action == 'cancel' && $planetId && $slot >= 1 && $slot <= 3) {
        $verifyStmt = $pdo->prepare("SELECT * FROM planet WHERE id = :id AND owner = :owner");
        $verifyStmt->execute(['id' => $planetId, 'owner' => $playerId]);
        
        if ($verifyStmt->rowCount() > 0) {
            $columnName = "commerce_with_$slot";
            
            // Clear trade route
            $updateStmt = $pdo->prepare("UPDATE planet SET $columnName = 0 WHERE id = :id");
            $updateStmt->execute(['id' => $planetId]);
            
            $successMsg = "Trade route cancelled!";
            
            // Reload planets
            $planetStmt->execute(['owner' => $playerId]);
            $planets = $planetStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Calculate trade income
function calculateTradeIncome($planet, $tradePlanet) {
    if (!$tradePlanet) return 0;
    
    // Base income based on planet sizes and resources
    $baseIncome = 100;
    $sizeBonus = ($planet['size'] + $tradePlanet['size']) * 20;
    $resourceBonus = ($planet['resource'] + $tradePlanet['resource']) * 30;
    
    return $baseIncome + $sizeBonus + $resourceBonus;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Trade Routes - MagellanWars</title>
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
        
        .info-bar {
            background: rgba(0, 0, 0, 0.7);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-label {
            color: #888;
            font-size: 14px;
        }
        
        .info-value {
            color: #ffaa00;
            font-size: 18px;
            font-weight: bold;
        }
        
        .planet-trade-card {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #0066cc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .planet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #333;
        }
        
        .planet-name {
            font-size: 24px;
            color: #00ffff;
        }
        
        .trade-routes {
            display: grid;
            gap: 15px;
        }
        
        .trade-route {
            background: rgba(0, 50, 100, 0.3);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #ffaa00;
        }
        
        .route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .route-slot {
            color: #ffaa00;
            font-weight: bold;
        }
        
        .route-status {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .route-active {
            color: #00ff00;
        }
        
        .route-empty {
            color: #666;
        }
        
        .route-income {
            color: #ffaa00;
            font-size: 14px;
        }
        
        .route-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        select {
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #0066cc;
            color: #fff;
            border-radius: 5px;
            min-width: 250px;
        }
        
        .btn {
            padding: 8px 20px;
            background: linear-gradient(45deg, #0066cc, #0099ff);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 150, 255, 0.4);
        }
        
        .btn-establish {
            background: linear-gradient(45deg, #ffaa00, #ffcc00);
            color: #000;
        }
        
        .btn-cancel {
            background: linear-gradient(45deg, #ff3333, #cc0000);
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
        
        .trade-summary {
            background: rgba(255, 170, 0, 0.1);
            border: 1px solid #ffaa00;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .summary-title {
            font-size: 18px;
            color: #ffaa00;
            margin-bottom: 10px;
        }
        
        .trade-tips {
            background: rgba(0, 0, 0, 0.5);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .tips-title {
            color: #00ffff;
            margin-bottom: 10px;
        }
        
        .tip {
            color: #aaa;
            font-size: 14px;
            margin-bottom: 5px;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Trade Routes Management</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>Trade Routes</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <?php
        $totalTradeIncome = 0;
        $activeRoutes = 0;
        foreach ($planets as $planet) {
            if ($planet['commerce_with_1'] > 0) $activeRoutes++;
            if ($planet['commerce_with_2'] > 0) $activeRoutes++;
            if ($planet['commerce_with_3'] > 0) $activeRoutes++;
        }
        $maxRoutes = count($planets) * 3;
        ?>
        
        <div class="info-bar">
            <div class="info-item">
                <span class="info-label">Active Trade Routes:</span>
                <span class="info-value"><?php echo $activeRoutes; ?> / <?php echo $maxRoutes; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Total Trade Income:</span>
                <span class="info-value"><?php echo number_format($totalTradeIncome); ?> PP/turn</span>
            </div>
        </div>
        
        <?php if (count($tradePlanets) == 0): ?>
        <div class="trade-summary">
            <div class="summary-title">⚠️ No Trade Partners Available</div>
            <p>There are no other players with planets to trade with yet. Trade routes will become available when other players join the game.</p>
        </div>
        <?php endif; ?>
        
        <?php foreach ($planets as $planet): ?>
        <div class="planet-trade-card">
            <div class="planet-header">
                <div class="planet-name"><?php echo htmlspecialchars($planet['name']); ?></div>
                <div>
                    Size: <?php echo ['Tiny', 'Small', 'Medium', 'Large', 'Huge'][$planet['size']] ?? 'Medium'; ?> | 
                    Resources: <?php echo ['Poor', 'Normal', 'Rich', 'Ultra Rich'][$planet['resource']] ?? 'Normal'; ?>
                </div>
            </div>
            
            <div class="trade-routes">
                <?php for ($slot = 1; $slot <= 3; $slot++): 
                    $tradeColumn = "commerce_with_$slot";
                    $tradeNameColumn = "trade{$slot}_name";
                    $hasRoute = $planet[$tradeColumn] > 0;
                ?>
                <div class="trade-route">
                    <div class="route-header">
                        <span class="route-slot">Trade Route <?php echo $slot; ?></span>
                        <?php if ($hasRoute): ?>
                            <div class="route-status">
                                <span class="route-active">● Active: <?php echo htmlspecialchars($planet[$tradeNameColumn] ?? 'Unknown'); ?></span>
                                <span class="route-income">+<?php echo number_format(calculateTradeIncome($planet, ['size' => 2, 'resource' => 2])); ?> PP/turn</span>
                            </div>
                        <?php else: ?>
                            <span class="route-empty">○ Empty Slot</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($hasRoute): ?>
                        <form method="POST" class="route-form">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="planet_id" value="<?php echo $planet['id']; ?>">
                            <input type="hidden" name="slot" value="<?php echo $slot; ?>">
                            <button type="submit" class="btn btn-cancel">Cancel Route</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" class="route-form">
                            <input type="hidden" name="action" value="establish">
                            <input type="hidden" name="planet_id" value="<?php echo $planet['id']; ?>">
                            <input type="hidden" name="slot" value="<?php echo $slot; ?>">
                            
                            <select name="target_planet" required>
                                <option value="">Select trade partner...</option>
                                <?php foreach ($tradePlanets as $tradePlanet): ?>
                                <option value="<?php echo $tradePlanet['id']; ?>">
                                    <?php echo htmlspecialchars($tradePlanet['name']); ?> 
                                    (Owner: <?php echo htmlspecialchars($tradePlanet['owner_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" class="btn btn-establish" <?php echo count($tradePlanets) == 0 ? 'disabled' : ''; ?>>
                                Establish Route
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="trade-tips">
            <div class="tips-title">📚 Trade Tips</div>
            <div class="tip">• Each planet can maintain up to 3 trade routes</div>
            <div class="tip">• Trade income is based on planet size and resource richness</div>
            <div class="tip">• Trading with distant clusters yields higher income</div>
            <div class="tip">• Trade routes are automatically protected by your fleet strength</div>
            <div class="tip">• Cancelled trade routes can be re-established next turn</div>
        </div>
    </div>
</body>
</html>