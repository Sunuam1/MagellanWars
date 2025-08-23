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
    
    // Get player's fleets
    $fleetStmt = $pdo->prepare("
        SELECT f.*, a.name as admiral_name, a.level as admiral_level, a.race as admiral_race
        FROM fleet f
        LEFT JOIN admiral a ON f.admiral = a.id
        WHERE f.owner = :owner
        ORDER BY f.id
    ");
    $fleetStmt->execute(['owner' => $playerId]);
    $fleets = $fleetStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available admirals
    $admiralStmt = $pdo->prepare("
        SELECT * FROM admiral 
        WHERE owner = :owner AND fleet_number = 0
        ORDER BY level DESC, exp DESC
    ");
    $admiralStmt->execute(['owner' => $playerId]);
    $admirals = $admiralStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get docked ships
    $dockedStmt = $pdo->prepare("
        SELECT ds.*, c.name as class_name, c.cost
        FROM docked_ship ds
        JOIN class c ON ds.design_id = c.design_id AND ds.owner = c.owner
        WHERE ds.owner = :owner
    ");
    $dockedStmt->execute(['owner' => $playerId]);
    $dockedShips = $dockedStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Mission types
$missionTypes = [
    0 => ['name' => 'Idle', 'color' => '#666'],
    1 => ['name' => 'Training', 'color' => '#00ff00'],
    2 => ['name' => 'Patrol', 'color' => '#0099ff'],
    3 => ['name' => 'Attack', 'color' => '#ff0000'],
    4 => ['name' => 'Defense', 'color' => '#ffaa00'],
    5 => ['name' => 'Exploration', 'color' => '#ff00ff']
];

// Fleet status types
$statusTypes = [
    0 => ['name' => 'Ready', 'color' => '#00ff00'],
    1 => ['name' => 'In Transit', 'color' => '#ffaa00'],
    2 => ['name' => 'In Battle', 'color' => '#ff0000'],
    3 => ['name' => 'Damaged', 'color' => '#ff6666'],
    4 => ['name' => 'Training', 'color' => '#0099ff']
];

// Handle fleet actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_fleet') {
        $fleetName = trim($_POST['fleet_name'] ?? '');
        $admiralId = intval($_POST['admiral_id'] ?? 0);
        $shipDesignId = intval($_POST['ship_design'] ?? 0);
        $shipCount = intval($_POST['ship_count'] ?? 0);
        
        if ($fleetName && $shipCount > 0) {
            // Get next fleet ID
            $maxFleetId = $pdo->query("SELECT MAX(id) FROM fleet WHERE owner = $playerId")->fetchColumn();
            $newFleetId = ($maxFleetId ?? 0) + 1;
            
            // Create fleet
            $createFleetStmt = $pdo->prepare("
                INSERT INTO fleet (owner, id, name, admiral, exp, status, maxship, currentship, shipclass, mission, mission_target, mission_terminate_time, killed_ship, killed_fleet)
                VALUES (:owner, :id, :name, :admiral, 0, 0, :maxship, :currentship, :shipclass, 0, 0, 0, 0, 0)
            ");
            $createFleetStmt->execute([
                'owner' => $playerId,
                'id' => $newFleetId,
                'name' => $fleetName,
                'admiral' => $admiralId,
                'maxship' => $shipCount * 10, // Max capacity
                'currentship' => $shipCount,
                'shipclass' => $shipDesignId
            ]);
            
            // Update admiral if assigned
            if ($admiralId > 0) {
                $pdo->prepare("UPDATE admiral SET fleet_number = :fleet WHERE id = :id")
                    ->execute(['fleet' => $newFleetId, 'id' => $admiralId]);
            }
            
            // Deduct ships from docked
            $pdo->prepare("UPDATE docked_ship SET number = number - :count WHERE owner = :owner AND design_id = :design")
                ->execute(['count' => $shipCount, 'owner' => $playerId, 'design' => $shipDesignId]);
            
            // Remove docked ship entry if all used
            $pdo->exec("DELETE FROM docked_ship WHERE number <= 0");
            
            $successMsg = "Fleet '$fleetName' created successfully!";
            
            // Reload data
            $fleetStmt->execute(['owner' => $playerId]);
            $fleets = $fleetStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $admiralStmt->execute(['owner' => $playerId]);
            $admirals = $admiralStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dockedStmt->execute(['owner' => $playerId]);
            $dockedShips = $dockedStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errorMsg = "Invalid fleet parameters!";
        }
    }
    
    if ($action == 'set_mission') {
        $fleetId = intval($_POST['fleet_id'] ?? 0);
        $mission = intval($_POST['mission'] ?? 0);
        
        $updateMissionStmt = $pdo->prepare("
            UPDATE fleet SET mission = :mission, mission_terminate_time = :time 
            WHERE owner = :owner AND id = :id
        ");
        $updateMissionStmt->execute([
            'mission' => $mission,
            'time' => time() + 3600, // 1 hour mission
            'owner' => $playerId,
            'id' => $fleetId
        ]);
        
        $successMsg = "Fleet mission updated!";
        
        // Reload fleets
        $fleetStmt->execute(['owner' => $playerId]);
        $fleets = $fleetStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// If no admirals, create one
if (count($admirals) == 0 && count($fleets) == 0) {
    $admiralId = time() + rand(1000, 9999);
    $createAdmiralStmt = $pdo->prepare("
        INSERT INTO admiral (id, owner, race, type, name, exp, level, fleet_number, armada_commanding, fleet_commanding, efficiency, offense, defense, maneuver, detection)
        VALUES (:id, :owner, :race, 1, :name, 0, 1, 0, 0, 5, 5, 5, 5, 5, 5)
    ");
    $createAdmiralStmt->execute([
        'id' => $admiralId,
        'owner' => $playerId,
        'race' => $_SESSION['player_race'] ?? 1,
        'name' => 'Admiral ' . substr(md5(rand()), 0, 6)
    ]);
    
    // Reload admirals
    $admiralStmt->execute(['owner' => $playerId]);
    $admirals = $admiralStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fleet Command - MagellanWars</title>
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
            background: linear-gradient(45deg, #ff0000, #ff6600);
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
            color: #ff6600;
            font-size: 18px;
            font-weight: bold;
        }
        
        .fleet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .fleet-card {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff6600;
            border-radius: 10px;
            padding: 20px;
        }
        
        .fleet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        
        .fleet-name {
            font-size: 20px;
            color: #ff6600;
            font-weight: bold;
        }
        
        .fleet-status {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .fleet-info {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-label {
            color: #888;
        }
        
        .info-value {
            color: #fff;
        }
        
        .fleet-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            background: linear-gradient(45deg, #ff6600, #ff9900);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 100, 0, 0.4);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #0066cc, #0099ff);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #ff3333, #cc0000);
        }
        
        .create-fleet-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff6600;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 24px;
            color: #ff6600;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ff9900;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #ff6600;
            color: #fff;
            border-radius: 5px;
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
        
        .no-data {
            text-align: center;
            padding: 40px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            color: #888;
        }
        
        .mission-select {
            padding: 5px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #ff6600;
            color: #fff;
            border-radius: 5px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Fleet Command Center</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>⚔️ Fleet Command</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">Total Fleets:</span>
                <span class="stat-value"><?php echo count($fleets); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Available Admirals:</span>
                <span class="stat-value"><?php echo count($admirals); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Docked Ships:</span>
                <span class="stat-value">
                    <?php 
                    $totalDocked = 0;
                    foreach ($dockedShips as $ds) {
                        $totalDocked += $ds['number'];
                    }
                    echo $totalDocked;
                    ?>
                </span>
            </div>
        </div>
        
        <?php if (count($fleets) > 0): ?>
        <h2 style="color: #ff6600; margin-bottom: 20px;">Active Fleets</h2>
        <div class="fleet-grid">
            <?php foreach ($fleets as $fleet): ?>
            <div class="fleet-card">
                <div class="fleet-header">
                    <div class="fleet-name"><?php echo htmlspecialchars($fleet['name']); ?></div>
                    <div class="fleet-status" style="background: <?php echo $statusTypes[$fleet['status']]['color']; ?>20; color: <?php echo $statusTypes[$fleet['status']]['color']; ?>">
                        <?php echo $statusTypes[$fleet['status']]['name']; ?>
                    </div>
                </div>
                
                <div class="fleet-info">
                    <div class="info-row">
                        <span class="info-label">Admiral:</span>
                        <span class="info-value"><?php echo htmlspecialchars($fleet['admiral_name'] ?? 'None'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ships:</span>
                        <span class="info-value"><?php echo $fleet['currentship']; ?> / <?php echo $fleet['maxship']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Experience:</span>
                        <span class="info-value"><?php echo number_format($fleet['exp']); ?> XP</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Mission:</span>
                        <span class="info-value" style="color: <?php echo $missionTypes[$fleet['mission']]['color']; ?>">
                            <?php echo $missionTypes[$fleet['mission']]['name']; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Kills:</span>
                        <span class="info-value">Ships: <?php echo $fleet['killed_ship']; ?> | Fleets: <?php echo $fleet['killed_fleet']; ?></span>
                    </div>
                </div>
                
                <div class="fleet-actions">
                    <form method="POST" style="display: flex; gap: 5px;">
                        <input type="hidden" name="action" value="set_mission">
                        <input type="hidden" name="fleet_id" value="<?php echo $fleet['id']; ?>">
                        <select name="mission" class="mission-select">
                            <?php foreach ($missionTypes as $mId => $mType): ?>
                            <option value="<?php echo $mId; ?>" <?php echo $fleet['mission'] == $mId ? 'selected' : ''; ?>>
                                <?php echo $mType['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn" style="padding: 5px 10px; font-size: 12px;">Set</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-data">
            <h3>No Active Fleets</h3>
            <p>Create your first fleet below to begin your military expansion!</p>
        </div>
        <?php endif; ?>
        
        <?php if (count($dockedShips) > 0 || count($fleets) == 0): ?>
        <div class="create-fleet-section">
            <h2 class="section-title">Create New Fleet</h2>
            
            <?php if (count($dockedShips) == 0): ?>
                <p style="color: #888;">You need to build ships first! Visit the <a href="ship_design.php" style="color: #ff6600;">Ship Design</a> facility.</p>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="create_fleet">
                
                <div class="form-group">
                    <label>Fleet Name:</label>
                    <input type="text" name="fleet_name" required placeholder="Enter fleet name...">
                </div>
                
                <div class="form-group">
                    <label>Admiral:</label>
                    <select name="admiral_id">
                        <option value="0">No Admiral</option>
                        <?php foreach ($admirals as $admiral): ?>
                        <option value="<?php echo $admiral['id']; ?>">
                            <?php echo htmlspecialchars($admiral['name']); ?> (Level <?php echo $admiral['level']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Ship Class:</label>
                    <select name="ship_design" required>
                        <option value="">Select ship class...</option>
                        <?php foreach ($dockedShips as $ds): ?>
                        <option value="<?php echo $ds['design_id']; ?>">
                            <?php echo htmlspecialchars($ds['class_name']); ?> (<?php echo $ds['number']; ?> available)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Number of Ships:</label>
                    <input type="number" name="ship_count" min="1" max="100" required placeholder="Number of ships...">
                </div>
                
                <button type="submit" class="btn btn-primary">Create Fleet</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>