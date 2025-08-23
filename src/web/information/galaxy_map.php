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
    
    // Get all clusters
    $clustersStmt = $pdo->query("
        SELECT c.*, COUNT(p.id) as planet_count
        FROM cluster c
        LEFT JOIN planet p ON c.id = p.cluster
        GROUP BY c.id
        ORDER BY c.id
    ");
    $clusters = $clustersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get player's planets for highlighting
    $playerPlanetsStmt = $pdo->prepare("
        SELECT p.*, c.name as cluster_name
        FROM planet p
        JOIN cluster c ON p.cluster = c.id
        WHERE p.owner = :owner
    ");
    $playerPlanetsStmt->execute(['owner' => $playerId]);
    $playerPlanets = $playerPlanetsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get empire control info
    $empireControlStmt = $pdo->query("
        SELECT 
            p.cluster,
            COUNT(DISTINCT p.owner) as controller_count,
            COUNT(p.id) as total_planets,
            SUM(CASE WHEN p.owner = 0 THEN 1 ELSE 0 END) as neutral_planets,
            GROUP_CONCAT(DISTINCT pl.council_id) as councils
        FROM planet p
        LEFT JOIN player pl ON p.owner = pl.game_id
        GROUP BY p.cluster
    ");
    $empireControl = [];
    while ($row = $empireControlStmt->fetch(PDO::FETCH_ASSOC)) {
        $empireControl[$row['cluster']] = $row;
    }
    
    // Get strategic locations (fortresses, capitals)
    $fortressesStmt = $pdo->query("
        SELECT f.*, p.name as owner_name
        FROM fortress f
        LEFT JOIN player p ON f.owner = p.game_id
        WHERE f.owner != 0
    ");
    $fortresses = $fortressesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active fleets in space
    $fleetsStmt = $pdo->query("
        SELECT f.*, p.name as owner_name, a.name as admiral_name
        FROM fleet f
        LEFT JOIN player p ON f.owner = p.game_id
        LEFT JOIN admiral a ON f.admiral = a.id
        WHERE f.mission > 0
        LIMIT 100
    ");
    $activeFleets = $fleetsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate galaxy statistics
    $totalPlanets = 0;
    $controlledPlanets = 0;
    $neutralPlanets = 0;
    
    foreach ($empireControl as $control) {
        $totalPlanets += $control['total_planets'];
        $neutralPlanets += $control['neutral_planets'];
    }
    $controlledPlanets = $totalPlanets - $neutralPlanets;
    
    // Get selected cluster details if requested
    $selectedCluster = null;
    if (isset($_GET['cluster'])) {
        $clusterId = intval($_GET['cluster']);
        
        $clusterPlanetsStmt = $pdo->prepare("
            SELECT p.*, pl.name as owner_name, pl.race as owner_race
            FROM planet p
            LEFT JOIN player pl ON p.owner = pl.game_id
            WHERE p.cluster = :cluster
            ORDER BY p.order_
        ");
        $clusterPlanetsStmt->execute(['cluster' => $clusterId]);
        $selectedCluster = [
            'id' => $clusterId,
            'planets' => $clusterPlanetsStmt->fetchAll(PDO::FETCH_ASSOC)
        ];
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

// Generate cluster positions for visual map (simplified grid)
$mapLayout = [];
$gridSize = ceil(sqrt(count($clusters)));
$index = 0;
foreach ($clusters as $cluster) {
    $x = ($index % $gridSize) * 150 + 100;
    $y = floor($index / $gridSize) * 150 + 100;
    $mapLayout[$cluster['id']] = ['x' => $x, 'y' => $y];
    $index++;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Galaxy Map - MagellanWars</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(to bottom, #000033, #000066);
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
            max-width: 1600px;
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
        
        .stats-bar {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #0099ff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
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
            color: #00ffff;
            font-size: 18px;
            font-weight: bold;
        }
        
        .map-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .galaxy-view {
            background: rgba(0, 0, 0, 0.9);
            border: 2px solid #0099ff;
            border-radius: 10px;
            padding: 20px;
            position: relative;
            min-height: 600px;
            overflow: hidden;
        }
        
        .map-canvas {
            position: relative;
            width: 100%;
            height: 600px;
            background: radial-gradient(circle at center, #001144, #000022);
            border-radius: 8px;
            overflow: auto;
        }
        
        .cluster-node {
            position: absolute;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #fff;
            border: 2px solid #0066cc;
            background: radial-gradient(circle, rgba(0, 100, 200, 0.8), rgba(0, 50, 100, 0.8));
        }
        
        .cluster-node:hover {
            transform: scale(1.2);
            z-index: 10;
            border-color: #00ffff;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.6);
        }
        
        .cluster-name {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
        }
        
        .cluster-info {
            font-size: 9px;
            color: #aaa;
            margin-top: 2px;
        }
        
        .cluster-controlled {
            border-color: #00ff00;
            background: radial-gradient(circle, rgba(0, 200, 0, 0.8), rgba(0, 100, 0, 0.8));
        }
        
        .cluster-contested {
            border-color: #ffaa00;
            background: radial-gradient(circle, rgba(255, 170, 0, 0.8), rgba(200, 100, 0, 0.8));
        }
        
        .cluster-enemy {
            border-color: #ff0000;
            background: radial-gradient(circle, rgba(200, 0, 0, 0.8), rgba(100, 0, 0, 0.8));
        }
        
        .cluster-player {
            border-color: #00ffff;
            background: radial-gradient(circle, rgba(0, 255, 255, 0.8), rgba(0, 150, 150, 0.8));
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.4);
        }
        
        .sidebar {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #0099ff;
            border-radius: 10px;
            padding: 20px;
        }
        
        .sidebar-section {
            margin-bottom: 25px;
        }
        
        .sidebar-title {
            font-size: 18px;
            color: #00ffff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid;
        }
        
        .fleet-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .fleet-item {
            background: rgba(0, 100, 200, 0.2);
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .cluster-detail {
            background: rgba(0, 0, 0, 0.9);
            border: 2px solid #ffaa00;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .cluster-detail-title {
            font-size: 24px;
            color: #ffaa00;
            margin-bottom: 20px;
        }
        
        .planet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .planet-card {
            background: rgba(0, 50, 100, 0.3);
            border: 1px solid #0066cc;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s;
        }
        
        .planet-card:hover {
            background: rgba(0, 100, 200, 0.4);
            border-color: #0099ff;
        }
        
        .planet-name {
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 5px;
        }
        
        .planet-owner {
            font-size: 12px;
            color: #aaa;
        }
        
        .planet-stats {
            font-size: 11px;
            color: #888;
            margin-top: 5px;
        }
        
        .control-panel {
            background: rgba(0, 0, 0, 0.7);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn-zoom {
            padding: 8px 15px;
            background: linear-gradient(45deg, #0066cc, #0099ff);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-zoom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 150, 255, 0.4);
        }
        
        .strategic-marker {
            position: absolute;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 0, 0, 0.8);
            border: 2px solid #ff0000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            z-index: 5;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #888;
        }
        
        @media (max-width: 1200px) {
            .map-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Galaxy Map</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>🌌 Galaxy Map</h1>
        
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">Total Clusters:</span>
                <span class="stat-value"><?php echo count($clusters); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Planets:</span>
                <span class="stat-value"><?php echo $totalPlanets; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Your Territories:</span>
                <span class="stat-value"><?php echo count($playerPlanets); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Neutral Planets:</span>
                <span class="stat-value"><?php echo $neutralPlanets; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Active Fleets:</span>
                <span class="stat-value"><?php echo count($activeFleets); ?></span>
            </div>
        </div>
        
        <div class="map-container">
            <div class="galaxy-view">
                <div class="control-panel">
                    <button class="btn-zoom" onclick="zoomIn()">🔍 Zoom In</button>
                    <button class="btn-zoom" onclick="zoomOut()">🔍 Zoom Out</button>
                    <button class="btn-zoom" onclick="resetView()">↻ Reset View</button>
                </div>
                
                <div class="map-canvas" id="mapCanvas">
                    <?php foreach ($clusters as $cluster): 
                        $control = $empireControl[$cluster['id']] ?? null;
                        $hasPlayerPlanet = false;
                        foreach ($playerPlanets as $planet) {
                            if ($planet['cluster'] == $cluster['id']) {
                                $hasPlayerPlanet = true;
                                break;
                            }
                        }
                        
                        $clusterClass = 'cluster-node';
                        if ($hasPlayerPlanet) {
                            $clusterClass .= ' cluster-player';
                        } elseif ($control && $control['controller_count'] > 1) {
                            $clusterClass .= ' cluster-contested';
                        } elseif ($control && $control['neutral_planets'] == $control['total_planets']) {
                            // Neutral cluster (default style)
                        } else {
                            $clusterClass .= ' cluster-controlled';
                        }
                        
                        $position = $mapLayout[$cluster['id']];
                    ?>
                    <a href="?cluster=<?php echo $cluster['id']; ?>" 
                       class="<?php echo $clusterClass; ?>"
                       style="left: <?php echo $position['x']; ?>px; top: <?php echo $position['y']; ?>px;"
                       title="<?php echo htmlspecialchars($cluster['name']); ?> - <?php echo $cluster['planet_count']; ?> planets">
                        <div class="cluster-name"><?php echo htmlspecialchars($cluster['name']); ?></div>
                        <div class="cluster-info"><?php echo $cluster['planet_count']; ?> planets</div>
                        <?php if ($hasPlayerPlanet): ?>
                            <div style="position: absolute; top: -5px; right: -5px; color: #00ffff; font-size: 16px;">⭐</div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php foreach ($fortresses as $fortress): 
                        // Place fortresses near center
                        $x = 400 + $fortress['layer'] * 50;
                        $y = 300 + $fortress['sector'] * 30;
                    ?>
                    <div class="strategic-marker" 
                         style="left: <?php echo $x; ?>px; top: <?php echo $y; ?>px;"
                         title="Fortress - <?php echo htmlspecialchars($fortress['owner_name']); ?>">
                        🏰
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="sidebar">
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <span>🎨</span>
                        <span>Map Legend</span>
                    </h3>
                    <div class="legend-item">
                        <div class="legend-color" style="border-color: #00ffff; background: rgba(0, 255, 255, 0.5);"></div>
                        <span>Your Territory</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="border-color: #00ff00; background: rgba(0, 255, 0, 0.5);"></div>
                        <span>Controlled</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="border-color: #ffaa00; background: rgba(255, 170, 0, 0.5);"></div>
                        <span>Contested</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="border-color: #0066cc; background: rgba(0, 100, 200, 0.5);"></div>
                        <span>Neutral</span>
                    </div>
                </div>
                
                <?php if (count($activeFleets) > 0): ?>
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <span>🚀</span>
                        <span>Active Fleets</span>
                    </h3>
                    <div class="fleet-list">
                        <?php foreach (array_slice($activeFleets, 0, 10) as $fleet): ?>
                        <div class="fleet-item">
                            <strong><?php echo htmlspecialchars($fleet['name']); ?></strong><br>
                            Commander: <?php echo htmlspecialchars($fleet['owner_name']); ?><br>
                            Ships: <?php echo $fleet['currentship']; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <span>📊</span>
                        <span>Quick Stats</span>
                    </h3>
                    <div style="font-size: 14px; line-height: 1.8;">
                        <div>Galactic Control: <?php echo round(($controlledPlanets / max($totalPlanets, 1)) * 100); ?>%</div>
                        <div>Your Control: <?php echo round((count($playerPlanets) / max($totalPlanets, 1)) * 100, 1); ?>%</div>
                        <div>Home Cluster: #<?php echo $player['home_cluster_id']; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($selectedCluster): ?>
        <div class="cluster-detail">
            <h2 class="cluster-detail-title">
                📍 Cluster Details - 
                <?php
                    foreach ($clusters as $c) {
                        if ($c['id'] == $selectedCluster['id']) {
                            echo htmlspecialchars($c['name']);
                            break;
                        }
                    }
                ?>
            </h2>
            
            <?php if (count($selectedCluster['planets']) > 0): ?>
            <div class="planet-grid">
                <?php foreach ($selectedCluster['planets'] as $planet): ?>
                <div class="planet-card">
                    <div class="planet-name">
                        <?php echo htmlspecialchars($planet['name'] ?: 'Planet #' . $planet['id']); ?>
                    </div>
                    <div class="planet-owner">
                        <?php if ($planet['owner'] == 0): ?>
                            <span style="color: #888;">Neutral</span>
                        <?php elseif ($planet['owner'] == $playerId): ?>
                            <span style="color: #00ffff;">Your Planet</span>
                        <?php else: ?>
                            Owner: <?php echo htmlspecialchars($planet['owner_name'] ?? 'Unknown'); ?>
                            <?php if ($planet['owner_race']): ?>
                                (<?php echo $races[$planet['owner_race']] ?? 'Unknown'; ?>)
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="planet-stats">
                        Size: <?php echo $planet['size']; ?> | 
                        Pop: <?php echo number_format($planet['population']); ?><br>
                        Buildings: F:<?php echo $planet['building_factory']; ?> 
                        M:<?php echo $planet['building_military_base']; ?> 
                        R:<?php echo $planet['building_research_lab']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-data">
                <p>No planets found in this cluster.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        let zoomLevel = 1;
        const mapCanvas = document.getElementById('mapCanvas');
        
        function zoomIn() {
            if (zoomLevel < 2) {
                zoomLevel += 0.2;
                mapCanvas.style.transform = `scale(${zoomLevel})`;
                mapCanvas.style.transformOrigin = 'center center';
            }
        }
        
        function zoomOut() {
            if (zoomLevel > 0.5) {
                zoomLevel -= 0.2;
                mapCanvas.style.transform = `scale(${zoomLevel})`;
                mapCanvas.style.transformOrigin = 'center center';
            }
        }
        
        function resetView() {
            zoomLevel = 1;
            mapCanvas.style.transform = 'scale(1)';
        }
        
        // Add star field effect
        function createStars() {
            const canvas = mapCanvas;
            for (let i = 0; i < 100; i++) {
                const star = document.createElement('div');
                star.style.position = 'absolute';
                star.style.width = Math.random() * 3 + 'px';
                star.style.height = star.style.width;
                star.style.background = '#fff';
                star.style.borderRadius = '50%';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.opacity = Math.random() * 0.8;
                star.style.pointerEvents = 'none';
                canvas.appendChild(star);
            }
        }
        
        createStars();
    </script>
</body>
</html>