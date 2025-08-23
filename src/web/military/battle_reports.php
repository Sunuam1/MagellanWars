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
    
    // Get battle records where player was involved
    $battlesStmt = $pdo->prepare("
        SELECT * FROM battle_record 
        WHERE attacker_id = :player OR defender_id = :player2
        ORDER BY time DESC
        LIMIT 50
    ");
    $battlesStmt->execute(['player' => $playerId, 'player2' => $playerId]);
    $battles = $battlesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get damage reports
    $damageStmt = $pdo->prepare("
        SELECT d.*, p.name as attacker_name
        FROM damage d
        LEFT JOIN player p ON d.attacker = p.game_id
        WHERE d.owner = :owner
        ORDER BY d.time DESC
        LIMIT 20
    ");
    $damageStmt->execute(['owner' => $playerId]);
    $damages = $damageStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $totalBattles = count($battles);
    $victories = 0;
    $defeats = 0;
    $draws = 0;
    
    foreach ($battles as $battle) {
        if ($battle['is_draw'] == 'YES') {
            $draws++;
        } elseif ($battle['winner'] == $playerId) {
            $victories++;
        } else {
            $defeats++;
        }
    }
    
    $winRate = $totalBattles > 0 ? round(($victories / $totalBattles) * 100, 1) : 0;
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// War types
$warTypes = [
    1 => ['name' => 'Siege', 'icon' => '⚔️', 'color' => '#ff0000'],
    2 => ['name' => 'Raid', 'icon' => '🗡️', 'color' => '#ff9900'],
    3 => ['name' => 'Blockade', 'icon' => '🚫', 'color' => '#ffff00'],
    4 => ['name' => 'Defense', 'icon' => '🛡️', 'color' => '#00ff00'],
    5 => ['name' => 'Invasion', 'icon' => '🔥', 'color' => '#ff00ff'],
];

// Race names
$races = [
    1 => 'Human',
    2 => 'Targro',
    3 => 'Bukka',
    4 => 'Xeloss',
    5 => 'Agerus',
    6 => 'Bosalian',
    7 => 'Xeldorade',
    8 => 'Kreen',
    9 => 'Madness',
    10 => 'Magellan'
];

// If no battles, create some sample battles for demonstration
if (count($battles) == 0) {
    // Create a sample battle
    $sampleBattleId = time() + rand(1000, 9999);
    $createBattleStmt = $pdo->prepare("
        INSERT INTO battle_record (
            id, attacker_id, defender_id, attacker_name, defender_name,
            attacker_race, defender_race, attacker_council, defender_council,
            time, war_type, is_draw, winner, planet_id, battle_field_name,
            attacker_gain, attacker_lose_fleet, defender_lose_fleet, record_file, there_was_battle
        ) VALUES (
            :id, :att_id, :def_id, :att_name, :def_name,
            :att_race, :def_race, 0, 0,
            :time, :war_type, 'NO', :winner, 0, :field,
            '1000 PP captured', '2 Destroyers lost', '5 Frigates lost', '', 1
        )
    ");
    
    try {
        $createBattleStmt->execute([
            'id' => $sampleBattleId,
            'att_id' => $playerId,
            'def_id' => 0,
            'att_name' => $player['name'],
            'def_name' => 'Pirates',
            'att_race' => $_SESSION['player_race'] ?? 1,
            'def_race' => 9,
            'time' => time() - 3600,
            'war_type' => 2,
            'winner' => $playerId,
            'field' => 'Asteroid Belt'
        ]);
        
        // Reload battles
        $battlesStmt->execute(['player' => $playerId, 'player2' => $playerId]);
        $battles = $battlesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recalculate statistics
        $totalBattles = count($battles);
        $victories = 0;
        $defeats = 0;
        $draws = 0;
        
        foreach ($battles as $battle) {
            if ($battle['is_draw'] == 'YES') {
                $draws++;
            } elseif ($battle['winner'] == $playerId) {
                $victories++;
            } else {
                $defeats++;
            }
        }
        
        $winRate = $totalBattles > 0 ? round(($victories / $totalBattles) * 100, 1) : 0;
    } catch (PDOException $e) {
        // Ignore if already exists
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Battle Reports - MagellanWars</title>
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
        
        .stats-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff0000;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: rgba(255, 0, 0, 0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #ff3333;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #ff9999;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .victories { color: #00ff00; }
        .defeats { color: #ff0000; }
        .draws { color: #ffff00; }
        .winrate { color: #00ffff; }
        
        .battles-section {
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .battle-list {
            display: grid;
            gap: 15px;
        }
        
        .battle-card {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .battle-card:hover {
            background: rgba(255, 100, 0, 0.1);
            border-color: #ff6600;
        }
        
        .battle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .battle-type {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 14px;
        }
        
        .battle-time {
            color: #888;
            font-size: 12px;
        }
        
        .battle-participants {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 5px;
        }
        
        .participant {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .participant-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .participant-race {
            font-size: 12px;
            color: #888;
        }
        
        .vs {
            font-size: 20px;
            color: #ff6600;
            font-weight: bold;
        }
        
        .battle-result {
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .result-victory {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid #00ff00;
            color: #00ff00;
        }
        
        .result-defeat {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
        }
        
        .result-draw {
            background: rgba(255, 255, 0, 0.2);
            border: 1px solid #ffff00;
            color: #ffff00;
        }
        
        .battle-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 14px;
        }
        
        .losses {
            color: #ff6666;
        }
        
        .gains {
            color: #66ff66;
        }
        
        .damage-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff0000;
            border-radius: 10px;
            padding: 20px;
        }
        
        .damage-list {
            display: grid;
            gap: 10px;
        }
        
        .damage-item {
            background: rgba(255, 0, 0, 0.1);
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #ff0000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .damage-amount {
            color: #ff0000;
            font-weight: bold;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Battle Reports Archive</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>📊 Battle Reports</h1>
        
        <div class="stats-section">
            <h2 style="color: #ff6600; margin-bottom: 20px;">Combat Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalBattles; ?></div>
                    <div class="stat-label">Total Battles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value victories"><?php echo $victories; ?></div>
                    <div class="stat-label">Victories</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value defeats"><?php echo $defeats; ?></div>
                    <div class="stat-label">Defeats</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value draws"><?php echo $draws; ?></div>
                    <div class="stat-label">Draws</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value winrate"><?php echo $winRate; ?>%</div>
                    <div class="stat-label">Win Rate</div>
                </div>
            </div>
        </div>
        
        <?php if (count($battles) > 0): ?>
        <div class="battles-section">
            <h2 class="section-title">
                <span>⚔️</span>
                <span>Recent Battles</span>
            </h2>
            
            <div class="battle-list">
                <?php foreach ($battles as $battle): 
                    $isAttacker = ($battle['attacker_id'] == $playerId);
                    $isWinner = ($battle['winner'] == $playerId);
                    $isDraw = ($battle['is_draw'] == 'YES');
                    $warType = $warTypes[$battle['war_type']] ?? ['name' => 'Unknown', 'icon' => '❓', 'color' => '#666'];
                ?>
                <div class="battle-card">
                    <div class="battle-header">
                        <div>
                            <span class="battle-type" style="background: <?php echo $warType['color']; ?>20; color: <?php echo $warType['color']; ?>">
                                <?php echo $warType['icon'] . ' ' . $warType['name']; ?>
                            </span>
                            <?php if ($battle['battle_field_name']): ?>
                            <span style="color: #888; margin-left: 10px;">
                                @ <?php echo htmlspecialchars($battle['battle_field_name']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="battle-time">
                            <?php echo date('Y-m-d H:i', $battle['time']); ?>
                        </div>
                    </div>
                    
                    <div class="battle-participants">
                        <div class="participant">
                            <div class="participant-name" style="color: <?php echo $isAttacker ? '#00ffff' : '#fff'; ?>">
                                <?php echo htmlspecialchars($battle['attacker_name']); ?>
                                <?php if ($isAttacker): ?>
                                <span style="font-size: 12px; color: #00ffff;"> (You)</span>
                                <?php endif; ?>
                            </div>
                            <div class="participant-race">
                                <?php echo $races[$battle['attacker_race']] ?? 'Unknown'; ?>
                            </div>
                        </div>
                        
                        <div class="vs">VS</div>
                        
                        <div class="participant">
                            <div class="participant-name" style="color: <?php echo !$isAttacker ? '#00ffff' : '#fff'; ?>">
                                <?php echo htmlspecialchars($battle['defender_name']); ?>
                                <?php if (!$isAttacker): ?>
                                <span style="font-size: 12px; color: #00ffff;"> (You)</span>
                                <?php endif; ?>
                            </div>
                            <div class="participant-race">
                                <?php echo $races[$battle['defender_race']] ?? 'Unknown'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="battle-result <?php echo $isDraw ? 'result-draw' : ($isWinner ? 'result-victory' : 'result-defeat'); ?>">
                        <?php if ($isDraw): ?>
                            🤝 DRAW
                        <?php elseif ($isWinner): ?>
                            🏆 VICTORY
                        <?php else: ?>
                            💀 DEFEAT
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($battle['there_was_battle']): ?>
                    <div class="battle-details">
                        <div>
                            <?php if ($battle['attacker_gain']): ?>
                            <div class="gains">Gains: <?php echo htmlspecialchars($battle['attacker_gain']); ?></div>
                            <?php endif; ?>
                            <?php if ($isAttacker && $battle['attacker_lose_fleet']): ?>
                            <div class="losses">Your Losses: <?php echo htmlspecialchars($battle['attacker_lose_fleet']); ?></div>
                            <?php elseif (!$isAttacker && $battle['defender_lose_fleet']): ?>
                            <div class="losses">Your Losses: <?php echo htmlspecialchars($battle['defender_lose_fleet']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($isAttacker && $battle['defender_lose_fleet']): ?>
                            <div class="gains">Enemy Losses: <?php echo htmlspecialchars($battle['defender_lose_fleet']); ?></div>
                            <?php elseif (!$isAttacker && $battle['attacker_lose_fleet']): ?>
                            <div class="gains">Enemy Losses: <?php echo htmlspecialchars($battle['attacker_lose_fleet']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="no-data">
            <h3>No Battle Reports</h3>
            <p>You haven't engaged in any battles yet. Build your fleet and attack enemy planets!</p>
        </div>
        <?php endif; ?>
        
        <?php if (count($damages) > 0): ?>
        <div class="damage-section">
            <h2 class="section-title">
                <span>💥</span>
                <span>Recent Damage Reports</span>
            </h2>
            
            <div class="damage-list">
                <?php foreach ($damages as $damage): ?>
                <div class="damage-item">
                    <div>
                        <strong><?php echo htmlspecialchars($damage['attacker_name'] ?? 'Unknown'); ?></strong>
                        attacked your base
                    </div>
                    <div>
                        <span class="damage-amount">-<?php echo number_format($damage['amount']); ?> HP</span>
                        <span style="color: #888; margin-left: 10px;">
                            <?php echo date('H:i', $damage['time']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>