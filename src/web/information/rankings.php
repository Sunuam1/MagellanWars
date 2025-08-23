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
    
    // Get ranking type from query parameter
    $rankType = $_GET['type'] ?? 'overall';
    
    // Get player rankings
    $playerRankings = [];
    switch ($rankType) {
        case 'production':
            $rankStmt = $pdo->query("
                SELECT p.game_id, p.name, p.race, p.production as score, p.council_id,
                       c.name as council_name
                FROM player p
                LEFT JOIN council c ON p.council_id = c.id
                ORDER BY p.production DESC
                LIMIT 100
            ");
            break;
            
        case 'military':
            $rankStmt = $pdo->query("
                SELECT p.game_id, p.name, p.race, 
                       (p.ship_production + COALESCE(fleet_power.total_power, 0)) as score,
                       p.council_id, c.name as council_name
                FROM player p
                LEFT JOIN (
                    SELECT owner, SUM(currentship * 100) as total_power
                    FROM fleet
                    GROUP BY owner
                ) fleet_power ON p.game_id = fleet_power.owner
                LEFT JOIN council c ON p.council_id = c.id
                ORDER BY score DESC
                LIMIT 100
            ");
            break;
            
        case 'research':
            $rankStmt = $pdo->query("
                SELECT p.game_id, p.name, p.race, p.research as score,
                       p.council_id, c.name as council_name
                FROM player p
                LEFT JOIN council c ON p.council_id = c.id
                ORDER BY p.research DESC
                LIMIT 100
            ");
            break;
            
        case 'honor':
            $rankStmt = $pdo->query("
                SELECT p.game_id, p.name, p.race, p.honor as score,
                       p.council_id, c.name as council_name
                FROM player p
                LEFT JOIN council c ON p.council_id = c.id
                ORDER BY p.honor DESC
                LIMIT 100
            ");
            break;
            
        case 'planets':
            $rankStmt = $pdo->query("
                SELECT p.game_id, p.name, p.race,
                       COUNT(pl.id) as score,
                       p.council_id, c.name as council_name
                FROM player p
                LEFT JOIN planet pl ON p.game_id = pl.owner
                LEFT JOIN council c ON p.council_id = c.id
                GROUP BY p.game_id, p.name, p.race, p.council_id, c.name
                ORDER BY score DESC
                LIMIT 100
            ");
            break;
            
        default: // overall
            $rankStmt = $pdo->query("
                SELECT p.game_id, p.name, p.race,
                       (p.production + p.research + p.ship_production + (p.honor * 10)) as score,
                       p.council_id, c.name as council_name
                FROM player p
                LEFT JOIN council c ON p.council_id = c.id
                ORDER BY score DESC
                LIMIT 100
            ");
            break;
    }
    
    $playerRankings = $rankStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get council rankings
    $councilRankStmt = $pdo->query("
        SELECT c.id, c.name, c.production, c.honor,
               COUNT(p.game_id) as member_count,
               SUM(p.production) as total_production
        FROM council c
        LEFT JOIN player p ON c.id = p.council_id
        GROUP BY c.id, c.name, c.production, c.honor
        ORDER BY total_production DESC
        LIMIT 50
    ");
    $councilRankings = $councilRankStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find player's rank
    $playerRank = 0;
    foreach ($playerRankings as $index => $player) {
        if ($player['game_id'] == $playerId) {
            $playerRank = $index + 1;
            break;
        }
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

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

// Ranking categories
$categories = [
    'overall' => ['name' => 'Overall Power', 'icon' => '👑', 'color' => '#ffaa00'],
    'production' => ['name' => 'Production', 'icon' => '⚙️', 'color' => '#00ff00'],
    'military' => ['name' => 'Military Might', 'icon' => '⚔️', 'color' => '#ff0000'],
    'research' => ['name' => 'Research', 'icon' => '🔬', 'color' => '#00ffff'],
    'honor' => ['name' => 'Honor', 'icon' => '🎖️', 'color' => '#ff00ff'],
    'planets' => ['name' => 'Territory', 'icon' => '🌍', 'color' => '#ff9900'],
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rankings - MagellanWars</title>
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
            background: linear-gradient(45deg, #ffaa00, #ffcc00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .category-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .category-tab {
            padding: 12px 24px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #666;
            border-radius: 25px;
            text-decoration: none;
            color: #fff;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-tab:hover {
            background: rgba(255, 170, 0, 0.2);
            border-color: #ffaa00;
        }
        
        .category-tab.active {
            background: rgba(255, 170, 0, 0.3);
            border-color: #ffaa00;
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.3);
        }
        
        .rankings-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .player-rankings {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ffaa00;
            border-radius: 10px;
            padding: 20px;
        }
        
        .council-rankings {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff00ff;
            border-radius: 10px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .player-section-title {
            color: #ffaa00;
        }
        
        .council-section-title {
            color: #ff00ff;
        }
        
        .rank-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .rank-table th {
            background: rgba(255, 170, 0, 0.2);
            padding: 12px;
            text-align: left;
            color: #ffaa00;
            border-bottom: 2px solid #ffaa00;
        }
        
        .council-table th {
            background: rgba(255, 0, 255, 0.2);
            color: #ff00ff;
            border-bottom: 2px solid #ff00ff;
        }
        
        .rank-table td {
            padding: 10px;
            border-bottom: 1px solid #333;
        }
        
        .rank-table tr:hover {
            background: rgba(255, 170, 0, 0.1);
        }
        
        .rank-number {
            font-size: 18px;
            font-weight: bold;
            color: #ffaa00;
            text-align: center;
            width: 60px;
        }
        
        .rank-1 { color: #ffaa00 !important; font-size: 24px; }
        .rank-2 { color: #cccccc !important; font-size: 22px; }
        .rank-3 { color: #cd7f32 !important; font-size: 20px; }
        
        .player-name {
            font-weight: bold;
            color: #fff;
        }
        
        .player-race {
            color: #888;
            font-size: 12px;
        }
        
        .player-council {
            color: #ff00ff;
            font-size: 12px;
        }
        
        .score-value {
            font-weight: bold;
            color: #00ff00;
            text-align: right;
        }
        
        .player-highlight {
            background: rgba(0, 255, 255, 0.1) !important;
            border-left: 3px solid #00ffff;
        }
        
        .stats-summary {
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #00ffff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            background: rgba(0, 100, 200, 0.2);
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #888;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .trophy-icon {
            font-size: 20px;
            margin-right: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        @media (max-width: 1000px) {
            .rankings-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Galactic Rankings</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>🏆 Galactic Rankings</h1>
        
        <?php if ($playerRank > 0): ?>
        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-value">#<?php echo $playerRank; ?></div>
                <div class="stat-label">Your Rank</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($playerRankings); ?></div>
                <div class="stat-label">Active Players</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($councilRankings); ?></div>
                <div class="stat-label">Active Councils</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo ucfirst($rankType); ?></div>
                <div class="stat-label">Current View</div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="category-tabs">
            <?php foreach ($categories as $catKey => $category): ?>
            <a href="?type=<?php echo $catKey; ?>" 
               class="category-tab <?php echo ($rankType == $catKey) ? 'active' : ''; ?>"
               style="<?php echo ($rankType == $catKey) ? 'border-color: ' . $category['color'] . ';' : ''; ?>">
                <span><?php echo $category['icon']; ?></span>
                <span><?php echo $category['name']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        
        <div class="rankings-container">
            <div class="player-rankings">
                <h2 class="section-title player-section-title">
                    <span>👥</span>
                    <span>Player Rankings - <?php echo $categories[$rankType]['name']; ?></span>
                </h2>
                
                <?php if (count($playerRankings) > 0): ?>
                <table class="rank-table">
                    <thead>
                        <tr>
                            <th style="text-align: center;">Rank</th>
                            <th>Commander</th>
                            <th>Race</th>
                            <th>Council</th>
                            <th style="text-align: right;">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($playerRankings as $index => $player): 
                            $rank = $index + 1;
                            $isCurrentPlayer = ($player['game_id'] == $playerId);
                        ?>
                        <tr class="<?php echo $isCurrentPlayer ? 'player-highlight' : ''; ?>">
                            <td class="rank-number rank-<?php echo $rank; ?>">
                                <?php if ($rank <= 3): ?>
                                    <span class="trophy-icon">
                                        <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                                    </span>
                                <?php endif; ?>
                                <?php echo $rank; ?>
                            </td>
                            <td>
                                <div class="player-name">
                                    <?php echo htmlspecialchars($player['name']); ?>
                                    <?php if ($isCurrentPlayer): ?>
                                        <span style="color: #00ffff; font-size: 12px;">(You)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="player-race"><?php echo $races[$player['race']] ?? 'Unknown'; ?></div>
                            </td>
                            <td>
                                <?php if ($player['council_name']): ?>
                                    <div class="player-council"><?php echo htmlspecialchars($player['council_name']); ?></div>
                                <?php else: ?>
                                    <span style="color: #666;">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="score-value">
                                <?php echo number_format($player['score']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <p>No ranking data available yet.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="council-rankings">
                <h2 class="section-title council-section-title">
                    <span>🏛️</span>
                    <span>Council Rankings</span>
                </h2>
                
                <?php if (count($councilRankings) > 0): ?>
                <table class="rank-table council-table">
                    <thead>
                        <tr>
                            <th style="text-align: center;">Rank</th>
                            <th>Council</th>
                            <th style="text-align: center;">Members</th>
                            <th style="text-align: right;">Power</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($councilRankings as $index => $council): 
                            $rank = $index + 1;
                        ?>
                        <tr>
                            <td class="rank-number" style="color: #ff00ff;">
                                <?php echo $rank; ?>
                            </td>
                            <td>
                                <div style="color: #ff00ff; font-weight: bold;">
                                    <?php echo htmlspecialchars($council['name']); ?>
                                </div>
                                <div style="color: #888; font-size: 11px;">
                                    Honor: <?php echo $council['honor']; ?>
                                </div>
                            </td>
                            <td style="text-align: center; color: #fff;">
                                <?php echo $council['member_count']; ?>
                            </td>
                            <td class="score-value" style="color: #ff00ff;">
                                <?php echo number_format($council['total_production']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <p>No councils formed yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>