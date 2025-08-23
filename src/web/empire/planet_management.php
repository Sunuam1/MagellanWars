<?php
session_start();
require_once '../db_config.php';

// Check if player is logged in
if (!isset($_SESSION['player_id'])) {
    header('Location: ../login.html');
    exit();
}

$playerId = $_SESSION['player_id'];
$playerName = $_SESSION['player_name'];

try {
    $pdo = getDBConnection();
    
    // Get player data
    $playerStmt = $pdo->prepare("SELECT * FROM player WHERE game_id = :id");
    $playerStmt->execute(['id' => $playerId]);
    $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get player's planets
    $planetStmt = $pdo->prepare("
        SELECT p.*, c.name as cluster_name 
        FROM planet p
        LEFT JOIN cluster c ON p.cluster = c.id
        WHERE p.owner = :owner
        ORDER BY p.order_ ASC
    ");
    $planetStmt->execute(['owner' => $playerId]);
    $planets = $planetStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no planets, create home planet
    if (count($planets) == 0) {
        // Create a home planet for new player
        $planetId = time() + rand(1000, 9999);
        $createPlanetStmt = $pdo->prepare("
            INSERT INTO planet (
                id, cluster, owner, order_, name, 
                population, building_factory, building_military_base, building_research_lab,
                ratio_factory, ratio_military_base, ratio_research_lab,
                atmosphere, temperature, size, resource, gravity
            ) VALUES (
                :id, 1, :owner, 1, :name,
                1000000, 5, 3, 2,
                40, 30, 30,
                'STANDARD', 300, 3, 3, 1.0
            )
        ");
        $createPlanetStmt->execute([
            'id' => $planetId,
            'owner' => $playerId,
            'name' => $playerName . "'s Home"
        ]);
        
        // Reload planets
        $planetStmt->execute(['owner' => $playerId]);
        $planets = $planetStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle planet management actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $planetId = $_POST['planet_id'] ?? 0;
    
    if ($action == 'update_ratios' && $planetId) {
        $factory = intval($_POST['ratio_factory'] ?? 40);
        $military = intval($_POST['ratio_military_base'] ?? 30);
        $research = intval($_POST['ratio_research_lab'] ?? 30);
        
        // Ensure ratios sum to 100
        if ($factory + $military + $research == 100) {
            $updateStmt = $pdo->prepare("
                UPDATE planet 
                SET ratio_factory = :factory,
                    ratio_military_base = :military,
                    ratio_research_lab = :research
                WHERE id = :id AND owner = :owner
            ");
            $updateStmt->execute([
                'factory' => $factory,
                'military' => $military,
                'research' => $research,
                'id' => $planetId,
                'owner' => $playerId
            ]);
            $successMsg = "Production ratios updated successfully!";
        } else {
            $errorMsg = "Ratios must sum to 100%";
        }
    }
    
    if ($action == 'invest' && $planetId) {
        $amount = intval($_POST['investment'] ?? 0);
        if ($amount > 0 && $amount <= $player['production']) {
            // Deduct from player's production
            $pdo->prepare("UPDATE player SET production = production - :amount WHERE game_id = :id")
                ->execute(['amount' => $amount, 'id' => $playerId]);
            
            // Add to planet investment
            $pdo->prepare("UPDATE planet SET investment = investment + :amount WHERE id = :id AND owner = :owner")
                ->execute(['amount' => $amount, 'id' => $planetId, 'owner' => $playerId]);
            
            $successMsg = "Invested " . number_format($amount) . " PP in planet development!";
            
            // Reload player data
            $playerStmt->execute(['id' => $playerId]);
            $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $errorMsg = "Invalid investment amount";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Planet Management - MagellanWars</title>
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
        
        .planet-card {
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
        
        .planet-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: rgba(0, 50, 100, 0.3);
            padding: 10px;
            border-radius: 5px;
        }
        
        .info-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 18px;
            color: #fff;
            margin-top: 5px;
        }
        
        .buildings-section {
            margin-top: 20px;
        }
        
        .building-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: rgba(0, 50, 100, 0.2);
            margin-bottom: 5px;
            border-radius: 5px;
        }
        
        .building-name {
            color: #00ffff;
        }
        
        .building-level {
            color: #fff;
            font-weight: bold;
        }
        
        .progress-bar {
            width: 200px;
            height: 20px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00ff00, #00cc00);
            transition: width 0.3s;
        }
        
        .ratio-controls {
            margin-top: 20px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
        }
        
        .ratio-input {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .ratio-input label {
            width: 150px;
            color: #00ffff;
        }
        
        .ratio-input input {
            width: 60px;
            padding: 5px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid #0066cc;
            color: #fff;
            border-radius: 5px;
        }
        
        .btn {
            padding: 10px 20px;
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
        
        .btn-invest {
            background: linear-gradient(45deg, #00cc00, #00ff00);
            color: #000;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0099ff;
            text-decoration: none;
        }
        
        .back-link:hover {
            color: #00ffff;
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
        <div>Planet Management System</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>Planet Management</h1>
        
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
                <span class="resource-label">Total Planets:</span>
                <span class="resource-value"><?php echo count($planets); ?></span>
            </div>
        </div>
        
        <?php foreach ($planets as $planet): ?>
        <div class="planet-card">
            <div class="planet-header">
                <div class="planet-name"><?php echo htmlspecialchars($planet['name']); ?></div>
                <div>Order #<?php echo $planet['order_']; ?></div>
            </div>
            
            <div class="planet-info">
                <div class="info-item">
                    <div class="info-label">Population</div>
                    <div class="info-value"><?php echo number_format($planet['population']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Size</div>
                    <div class="info-value">
                        <?php 
                        $sizes = ['Tiny', 'Small', 'Medium', 'Large', 'Huge'];
                        echo $sizes[$planet['size']] ?? 'Medium';
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Resource</div>
                    <div class="info-value">
                        <?php 
                        $resources = ['Poor', 'Normal', 'Rich', 'Ultra Rich'];
                        echo $resources[$planet['resource']] ?? 'Normal';
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Investment</div>
                    <div class="info-value"><?php echo number_format($planet['investment']); ?> PP</div>
                </div>
            </div>
            
            <div class="buildings-section">
                <h3 style="color: #00ffff; margin-bottom: 10px;">Buildings</h3>
                
                <div class="building-row">
                    <span class="building-name">Factories</span>
                    <span class="building-level">Level <?php echo $planet['building_factory']; ?></span>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $planet['progress_factory']; ?>%"></div>
                    </div>
                </div>
                
                <div class="building-row">
                    <span class="building-name">Military Base</span>
                    <span class="building-level">Level <?php echo $planet['building_military_base']; ?></span>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $planet['progress_military_base']; ?>%"></div>
                    </div>
                </div>
                
                <div class="building-row">
                    <span class="building-name">Research Lab</span>
                    <span class="building-level">Level <?php echo $planet['building_research_lab']; ?></span>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $planet['progress_research_lab']; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="ratio-controls">
                <h3 style="color: #00ffff; margin-bottom: 10px;">Production Ratios</h3>
                <form method="POST" style="display: flex; gap: 20px; align-items: flex-end;">
                    <input type="hidden" name="action" value="update_ratios">
                    <input type="hidden" name="planet_id" value="<?php echo $planet['id']; ?>">
                    
                    <div class="ratio-input">
                        <label>Factory:</label>
                        <input type="number" name="ratio_factory" value="<?php echo $planet['ratio_factory']; ?>" min="0" max="100">%
                    </div>
                    
                    <div class="ratio-input">
                        <label>Military:</label>
                        <input type="number" name="ratio_military_base" value="<?php echo $planet['ratio_military_base']; ?>" min="0" max="100">%
                    </div>
                    
                    <div class="ratio-input">
                        <label>Research:</label>
                        <input type="number" name="ratio_research_lab" value="<?php echo $planet['ratio_research_lab']; ?>" min="0" max="100">%
                    </div>
                    
                    <button type="submit" class="btn">Update Ratios</button>
                </form>
                
                <form method="POST" style="margin-top: 15px; display: flex; gap: 20px; align-items: center;">
                    <input type="hidden" name="action" value="invest">
                    <input type="hidden" name="planet_id" value="<?php echo $planet['id']; ?>">
                    
                    <label style="color: #00ffff;">Invest PP:</label>
                    <input type="number" name="investment" min="0" max="<?php echo $player['production']; ?>" 
                           style="width: 150px; padding: 5px; background: rgba(255,255,255,0.1); border: 1px solid #0066cc; color: #fff; border-radius: 5px;">
                    <button type="submit" class="btn btn-invest">Invest</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>