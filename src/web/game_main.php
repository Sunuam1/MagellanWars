<?php
session_start();

// Check if player is logged in
if (!isset($_SESSION['player_id'])) {
    header('Location: login.html');
    exit();
}

$playerId = $_SESSION['player_id'];
$playerName = $_SESSION['player_name'];
$playerRace = $_SESSION['player_race'];

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

$raceName = isset($races[$playerRace]) ? $races[$playerRace] : 'Unknown';

// Database connection for player stats
require_once 'db_config.php';

try {
    $pdo = getDBConnection();
    
    // Get player data
    $stmt = $pdo->prepare("SELECT * FROM player WHERE game_id = :id");
    $stmt->execute(['id' => $playerId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        // Player not found, clear session
        session_destroy();
        header('Location: login.html');
        exit();
    }
    
    $production = $player['production'];
    $research = $player['research'];
    $shipProduction = $player['ship_production'];
    $honor = $player['honor'];
    
} catch (PDOException $e) {
    $production = 1000;
    $research = 100;
    $shipProduction = 100;
    $honor = 50;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MagellanWars - Command Center</title>
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
        
        .player-info {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .player-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 20px;
            color: #00ffff;
            font-weight: bold;
        }
        
        .logout-btn {
            padding: 8px 20px;
            background: linear-gradient(45deg, #ff3333, #cc0000);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .main-container {
            display: flex;
            gap: 20px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .sidebar {
            width: 250px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
        }
        
        .menu-section {
            margin-bottom: 30px;
        }
        
        .menu-title {
            font-size: 14px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #333;
        }
        
        .menu-item {
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            background: rgba(0, 100, 200, 0.2);
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .menu-item:hover {
            background: rgba(0, 150, 255, 0.4);
            transform: translateX(5px);
        }
        
        .content {
            flex: 1;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 30px;
        }
        
        .welcome-section {
            text-align: center;
            padding: 40px;
        }
        
        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #00ffff, #0099ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .status-card {
            background: rgba(0, 50, 100, 0.3);
            border: 1px solid #0066cc;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .status-card h3 {
            color: #00ffff;
            margin-bottom: 10px;
        }
        
        .status-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #fff;
        }
        
        .status-card .unit {
            font-size: 14px;
            color: #888;
        }
        
        .news-section {
            margin-top: 40px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
        }
        
        .news-title {
            font-size: 20px;
            color: #00ffff;
            margin-bottom: 15px;
        }
        
        .news-item {
            padding: 10px;
            margin: 10px 0;
            background: rgba(0, 50, 100, 0.2);
            border-left: 3px solid #0099ff;
        }
        
        .news-time {
            font-size: 12px;
            color: #888;
        }
    </style>
    <script src="turn_timer.js"></script>
</head>
<body>
    <div class="header">
        <div class="player-info">
            <div class="player-stat">
                <span class="stat-label">Commander</span>
                <span class="stat-value"><?php echo htmlspecialchars($playerName); ?></span>
            </div>
            <div class="player-stat">
                <span class="stat-label">Race</span>
                <span class="stat-value"><?php echo $raceName; ?></span>
            </div>
            <div class="player-stat">
                <span class="stat-label">Honor</span>
                <span class="stat-value"><?php echo $honor; ?></span>
            </div>
            <div class="player-stat">
                <span class="stat-label">Turn</span>
                <span class="stat-value">#<?php echo isset($player['turn']) ? $player['turn'] : 0; ?></span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    
    <div class="main-container">
        <div class="sidebar">
            <div class="menu-section">
                <div class="menu-title">Empire Management</div>
                <a href="empire/planet_management.php" class="menu-item">Planet Management</a>
                <a href="empire/research.php" class="menu-item">Research Lab</a>
                <a href="empire/construction.php" class="menu-item">Construction</a>
                <a href="empire/trade_routes.php" class="menu-item">Trade Routes</a>
            </div>
            
            <div class="menu-section">
                <div class="menu-title">Military</div>
                <a href="military/fleet_command.php" class="menu-item">Fleet Command</a>
                <a href="military/ship_design.php" class="menu-item">Ship Design</a>
                <a href="military/defense_plans.php" class="menu-item">Defense Plans</a>
                <a href="military/battle_reports.php" class="menu-item">Battle Reports</a>
            </div>
            
            <div class="menu-section">
                <div class="menu-title">Diplomacy</div>
                <a href="diplomacy/council.php" class="menu-item">Council</a>
                <a href="diplomacy/messages.php" class="menu-item">Messages</a>
                <a href="diplomacy/treaties.php" class="menu-item">Treaties</a>
                <a href="diplomacy/black_market.php" class="menu-item">Black Market</a>
            </div>
            
            <div class="menu-section">
                <div class="menu-title">Information</div>
                <a href="information/rankings.php" class="menu-item">Rankings</a>
                <a href="information/galaxy_map.php" class="menu-item">Galaxy Map</a>
                <a href="information/encyclopedia.php" class="menu-item">Encyclopedia</a>
                <a href="information/help.php" class="menu-item">Help</a>
            </div>
        </div>
        
        <div class="content">
            <div class="welcome-section">
                <h1>Welcome to MagellanWars</h1>
                <p>Build your galactic empire and conquer the stars!</p>
                
                <div class="status-grid">
                    <div class="status-card">
                        <h3>Production</h3>
                        <div class="value"><?php echo number_format($production); ?></div>
                        <div class="unit">PP/turn</div>
                    </div>
                    
                    <div class="status-card">
                        <h3>Research</h3>
                        <div class="value"><?php echo number_format($research); ?></div>
                        <div class="unit">RP/turn</div>
                    </div>
                    
                    <div class="status-card">
                        <h3>Military</h3>
                        <div class="value"><?php echo number_format($shipProduction); ?></div>
                        <div class="unit">MP/turn</div>
                    </div>
                    
                    <div class="status-card">
                        <h3>Planets</h3>
                        <div class="value">1</div>
                        <div class="unit">controlled</div>
                    </div>
                    
                    <div class="status-card">
                        <h3>Fleets</h3>
                        <div class="value">0</div>
                        <div class="unit">active</div>
                    </div>
                    
                    <div class="status-card">
                        <h3>Technologies</h3>
                        <div class="value">0</div>
                        <div class="unit">researched</div>
                    </div>
                </div>
                
                <div class="news-section">
                    <h2 class="news-title">Recent Events</h2>
                    <div class="news-item">
                        <div class="news-time">Just now</div>
                        <div>Welcome to the galaxy, Commander <?php echo htmlspecialchars($playerName); ?>!</div>
                    </div>
                    <div class="news-item">
                        <div class="news-time">System Message</div>
                        <div>Your empire has been established. Begin by managing your home planet and researching new technologies.</div>
                    </div>
                    <div class="news-item">
                        <div class="news-time">Tutorial</div>
                        <div>Visit the Planet Management section to begin building your infrastructure.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>