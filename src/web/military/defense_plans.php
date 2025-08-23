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
    
    // Get player's defense plans
    $plansStmt = $pdo->prepare("
        SELECT * FROM plan 
        WHERE owner = :owner
        ORDER BY type, id
    ");
    $plansStmt->execute(['owner' => $playerId]);
    $plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get player's fleets for assignment
    $fleetsStmt = $pdo->prepare("
        SELECT f.*, a.name as admiral_name
        FROM fleet f
        LEFT JOIN admiral a ON f.admiral = a.id
        WHERE f.owner = :owner
        ORDER BY f.id
    ");
    $fleetsStmt->execute(['owner' => $playerId]);
    $fleets = $fleetsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get defense fleet assignments
    $defenseFleets = [];
    foreach ($plans as $plan) {
        $defFleetStmt = $pdo->prepare("
            SELECT df.*, f.name as fleet_name
            FROM defense_fleet df
            JOIN fleet f ON df.fleet_id = f.id AND df.owner = f.owner
            WHERE df.owner = :owner AND df.plan_id = :plan
        ");
        $defFleetStmt->execute(['owner' => $playerId, 'plan' => $plan['id']]);
        $defenseFleets[$plan['id']] = $defFleetStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get other players for enemy selection
    $playersStmt = $pdo->prepare("
        SELECT game_id, name FROM player 
        WHERE game_id != :id
        ORDER BY name
    ");
    $playersStmt->execute(['id' => $playerId]);
    $otherPlayers = $playersStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Plan types
$planTypes = [
    1 => ['name' => 'Planetary Defense', 'color' => '#00ff00', 'icon' => '🛡️'],
    2 => ['name' => 'Fleet Interception', 'color' => '#ff9900', 'icon' => '⚔️'],
    3 => ['name' => 'Capital Defense', 'color' => '#ff0000', 'icon' => '🏰'],
];

// Attack types
$attackTypes = [
    1 => 'Aggressive',
    2 => 'Defensive',
    3 => 'Balanced',
    4 => 'Hit & Run',
    5 => 'Last Stand'
];

// Handle defense plan actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_plan') {
        $planName = trim($_POST['plan_name'] ?? '');
        $planType = intval($_POST['plan_type'] ?? 1);
        $enemyId = intval($_POST['enemy_id'] ?? 0);
        $attackType = intval($_POST['attack_type'] ?? 1);
        $minFleets = intval($_POST['min_fleets'] ?? 1);
        $maxFleets = intval($_POST['max_fleets'] ?? 5);
        
        if ($planName) {
            // Get next plan ID
            $maxPlanId = $pdo->query("SELECT MAX(id) FROM plan WHERE owner = $playerId")->fetchColumn();
            $newPlanId = ($maxPlanId ?? 0) + 1;
            
            // Create plan
            $createPlanStmt = $pdo->prepare("
                INSERT INTO plan (owner, id, type, name, capital, enemy, min, max, attack_type)
                VALUES (:owner, :id, :type, :name, 0, :enemy, :min, :max, :attack)
            ");
            $createPlanStmt->execute([
                'owner' => $playerId,
                'id' => $newPlanId,
                'type' => $planType,
                'name' => $planName,
                'enemy' => $enemyId,
                'min' => $minFleets,
                'max' => $maxFleets,
                'attack' => $attackType
            ]);
            
            $successMsg = "Defense plan '$planName' created successfully!";
            
            // Reload plans
            $plansStmt->execute(['owner' => $playerId]);
            $plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    if ($action == 'assign_fleet') {
        $planId = intval($_POST['plan_id'] ?? 0);
        $fleetId = intval($_POST['fleet_id'] ?? 0);
        $command = intval($_POST['command'] ?? 1);
        $posX = intval($_POST['pos_x'] ?? 0);
        $posY = intval($_POST['pos_y'] ?? 0);
        
        if ($planId && $fleetId) {
            // Check if already assigned
            $checkStmt = $pdo->prepare("
                SELECT * FROM defense_fleet 
                WHERE owner = :owner AND plan_id = :plan AND fleet_id = :fleet
            ");
            $checkStmt->execute(['owner' => $playerId, 'plan' => $planId, 'fleet' => $fleetId]);
            
            if ($checkStmt->rowCount() == 0) {
                // Assign fleet to plan
                $assignStmt = $pdo->prepare("
                    INSERT INTO defense_fleet (owner, plan_id, fleet_id, command, x, y)
                    VALUES (:owner, :plan, :fleet, :command, :x, :y)
                ");
                $assignStmt->execute([
                    'owner' => $playerId,
                    'plan' => $planId,
                    'fleet' => $fleetId,
                    'command' => $command,
                    'x' => $posX,
                    'y' => $posY
                ]);
                
                $successMsg = "Fleet assigned to defense plan!";
                
                // Reload defense fleets
                foreach ($plans as $plan) {
                    $defFleetStmt = $pdo->prepare("
                        SELECT df.*, f.name as fleet_name
                        FROM defense_fleet df
                        JOIN fleet f ON df.fleet_id = f.id AND df.owner = f.owner
                        WHERE df.owner = :owner AND df.plan_id = :plan
                    ");
                    $defFleetStmt->execute(['owner' => $playerId, 'plan' => $plan['id']]);
                    $defenseFleets[$plan['id']] = $defFleetStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $errorMsg = "Fleet already assigned to this plan!";
            }
        }
    }
    
    if ($action == 'remove_fleet') {
        $planId = intval($_POST['plan_id'] ?? 0);
        $fleetId = intval($_POST['fleet_id'] ?? 0);
        
        $removeStmt = $pdo->prepare("
            DELETE FROM defense_fleet 
            WHERE owner = :owner AND plan_id = :plan AND fleet_id = :fleet
        ");
        $removeStmt->execute(['owner' => $playerId, 'plan' => $planId, 'fleet' => $fleetId]);
        
        $successMsg = "Fleet removed from defense plan!";
        
        // Reload defense fleets
        foreach ($plans as $plan) {
            $defFleetStmt = $pdo->prepare("
                SELECT df.*, f.name as fleet_name
                FROM defense_fleet df
                JOIN fleet f ON df.fleet_id = f.id AND df.owner = f.owner
                WHERE df.owner = :owner AND df.plan_id = :plan
            ");
            $defFleetStmt->execute(['owner' => $playerId, 'plan' => $plan['id']]);
            $defenseFleets[$plan['id']] = $defFleetStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    if ($action == 'delete_plan') {
        $planId = intval($_POST['plan_id'] ?? 0);
        
        // Delete defense fleet assignments
        $pdo->prepare("DELETE FROM defense_fleet WHERE owner = :owner AND plan_id = :plan")
            ->execute(['owner' => $playerId, 'plan' => $planId]);
        
        // Delete plan
        $pdo->prepare("DELETE FROM plan WHERE owner = :owner AND id = :plan")
            ->execute(['owner' => $playerId, 'plan' => $planId]);
        
        $successMsg = "Defense plan deleted!";
        
        // Reload plans
        $plansStmt->execute(['owner' => $playerId]);
        $plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);
        $defenseFleets = [];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Defense Plans - MagellanWars</title>
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
            background: linear-gradient(45deg, #ff3333, #ff9900);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stats-bar {
            background: rgba(0, 0, 0, 0.7);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-label {
            color: #888;
            font-size: 14px;
        }
        
        .stat-value {
            color: #ff9900;
            font-size: 18px;
            font-weight: bold;
        }
        
        .create-plan-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff6600;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 24px;
            color: #ff9900;
            margin-bottom: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #ff9900;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #ff6600;
            color: #fff;
            border-radius: 5px;
        }
        
        .btn {
            padding: 10px 20px;
            background: linear-gradient(45deg, #ff6600, #ff9900);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 100, 0, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #ff3333, #cc0000);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #00cc00, #00ff00);
            color: #000;
        }
        
        .plans-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .plan-card {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff6600;
            border-radius: 10px;
            padding: 20px;
        }
        
        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #333;
        }
        
        .plan-name {
            font-size: 22px;
            font-weight: bold;
        }
        
        .plan-type {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 14px;
        }
        
        .plan-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: rgba(255, 100, 0, 0.1);
            padding: 10px;
            border-radius: 5px;
        }
        
        .info-label {
            color: #ff9900;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .info-value {
            color: #fff;
            font-size: 16px;
            margin-top: 5px;
        }
        
        .fleets-section {
            background: rgba(0, 0, 0, 0.5);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .fleet-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .fleet-badge {
            background: rgba(255, 100, 0, 0.2);
            border: 1px solid #ff6600;
            padding: 8px 15px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .assign-fleet-form {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }
        
        .assign-fleet-form select {
            padding: 5px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #ff6600;
            color: #fff;
            border-radius: 5px;
        }
        
        .small-btn {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .position-inputs {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .position-inputs input {
            width: 50px;
            padding: 5px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #ff6600;
            color: #fff;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Defense Planning Center</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>🛡️ Defense Plans</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">Active Plans:</span>
                <span class="stat-value"><?php echo count($plans); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Available Fleets:</span>
                <span class="stat-value"><?php echo count($fleets); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Defense Rating:</span>
                <span class="stat-value"><?php echo count($plans) * 100 + count($fleets) * 50; ?></span>
            </div>
        </div>
        
        <div class="create-plan-section">
            <h2 class="section-title">Create New Defense Plan</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_plan">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Plan Name:</label>
                        <input type="text" name="plan_name" required placeholder="Enter plan name...">
                    </div>
                    
                    <div class="form-group">
                        <label>Plan Type:</label>
                        <select name="plan_type">
                            <?php foreach ($planTypes as $typeId => $type): ?>
                            <option value="<?php echo $typeId; ?>">
                                <?php echo $type['icon'] . ' ' . $type['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Target Enemy (optional):</label>
                        <select name="enemy_id">
                            <option value="0">Any Enemy</option>
                            <?php foreach ($otherPlayers as $otherPlayer): ?>
                            <option value="<?php echo $otherPlayer['game_id']; ?>">
                                <?php echo htmlspecialchars($otherPlayer['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Attack Strategy:</label>
                        <select name="attack_type">
                            <?php foreach ($attackTypes as $attackId => $attackName): ?>
                            <option value="<?php echo $attackId; ?>"><?php echo $attackName; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Min Fleets:</label>
                        <input type="number" name="min_fleets" min="1" max="10" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Max Fleets:</label>
                        <input type="number" name="max_fleets" min="1" max="20" value="5">
                    </div>
                </div>
                
                <button type="submit" class="btn">Create Defense Plan</button>
            </form>
        </div>
        
        <?php if (count($plans) > 0): ?>
        <h2 style="color: #ff9900; margin-bottom: 20px;">Your Defense Plans</h2>
        <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
            <div class="plan-card">
                <div class="plan-header">
                    <div>
                        <span class="plan-name" style="color: <?php echo $planTypes[$plan['type']]['color'] ?? '#fff'; ?>">
                            <?php echo htmlspecialchars($plan['name']); ?>
                        </span>
                        <span class="plan-type" style="background: <?php echo $planTypes[$plan['type']]['color'] ?? '#666'; ?>20; color: <?php echo $planTypes[$plan['type']]['color'] ?? '#fff'; ?>">
                            <?php echo $planTypes[$plan['type']]['icon'] ?? ''; ?> <?php echo $planTypes[$plan['type']]['name'] ?? 'Unknown'; ?>
                        </span>
                    </div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_plan">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                        <button type="submit" class="btn btn-danger small-btn" 
                                onclick="return confirm('Delete this defense plan?')">Delete</button>
                    </form>
                </div>
                
                <div class="plan-info">
                    <div class="info-item">
                        <div class="info-label">Attack Strategy</div>
                        <div class="info-value"><?php echo $attackTypes[$plan['attack_type']] ?? 'Unknown'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Fleet Range</div>
                        <div class="info-value"><?php echo $plan['min']; ?> - <?php echo $plan['max']; ?> fleets</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Target</div>
                        <div class="info-value">
                            <?php 
                            if ($plan['enemy'] > 0) {
                                $enemyName = 'Unknown';
                                foreach ($otherPlayers as $op) {
                                    if ($op['game_id'] == $plan['enemy']) {
                                        $enemyName = $op['name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($enemyName);
                            } else {
                                echo 'Any Enemy';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="fleets-section">
                    <div style="color: #ff9900; font-weight: bold;">Assigned Fleets (<?php echo count($defenseFleets[$plan['id']] ?? []); ?>)</div>
                    
                    <?php if (count($defenseFleets[$plan['id']] ?? []) > 0): ?>
                    <div class="fleet-list">
                        <?php foreach ($defenseFleets[$plan['id']] as $defFleet): ?>
                        <div class="fleet-badge">
                            <span><?php echo htmlspecialchars($defFleet['fleet_name']); ?></span>
                            <span style="color: #888; font-size: 12px;">Pos: (<?php echo $defFleet['x']; ?>, <?php echo $defFleet['y']; ?>)</span>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_fleet">
                                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                <input type="hidden" name="fleet_id" value="<?php echo $defFleet['fleet_id']; ?>">
                                <button type="submit" class="btn btn-danger small-btn">×</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (count($fleets) > 0): ?>
                    <form method="POST" class="assign-fleet-form">
                        <input type="hidden" name="action" value="assign_fleet">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                        
                        <select name="fleet_id" required>
                            <option value="">Select fleet...</option>
                            <?php foreach ($fleets as $fleet): ?>
                            <option value="<?php echo $fleet['id']; ?>">
                                <?php echo htmlspecialchars($fleet['name']); ?> (<?php echo $fleet['currentship']; ?> ships)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="command">
                            <option value="1">Attack</option>
                            <option value="2">Defend</option>
                            <option value="3">Support</option>
                        </select>
                        
                        <div class="position-inputs">
                            <label style="color: #888; font-size: 12px;">Pos:</label>
                            <input type="number" name="pos_x" value="0" min="-100" max="100" placeholder="X">
                            <input type="number" name="pos_y" value="0" min="-100" max="100" placeholder="Y">
                        </div>
                        
                        <button type="submit" class="btn small-btn">Assign</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.5); border-radius: 10px;">
            <h3 style="color: #ff9900;">No Defense Plans</h3>
            <p style="color: #888;">Create your first defense plan to protect your empire!</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>