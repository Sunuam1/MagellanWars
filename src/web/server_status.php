<?php
/**
 * Server Status Monitor
 * Shows if the C++ game server is actually running and processing turns
 */

header('Content-Type: application/json');
require_once 'db_config.php';

function checkServerStatus() {
    $status = [
        'timestamp' => time(),
        'checks' => []
    ];
    
    // Check 1: Database connection
    try {
        $pdo = getDBConnection();
        $status['checks']['database'] = [
            'status' => 'OK',
            'message' => 'Database connection successful'
        ];
    } catch (Exception $e) {
        $status['checks']['database'] = [
            'status' => 'FAILED',
            'message' => $e->getMessage()
        ];
        return $status;
    }
    
    // Check 2: Game server process (check if port 5000 is listening)
    $socket = @fsockopen('localhost', 5000, $errno, $errstr, 1);
    if ($socket) {
        fclose($socket);
        $status['checks']['gameserver_port'] = [
            'status' => 'OK',
            'message' => 'Game server port 5000 is open'
        ];
    } else {
        $status['checks']['gameserver_port'] = [
            'status' => 'FAILED',
            'message' => "Port 5000 not responding: $errstr"
        ];
    }
    
    // Check 3: Player turn updates (most important check)
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as player_count,
                MAX(turn) as max_turn,
                MIN(turn) as min_turn,
                MAX(tick) as last_tick,
                AVG(turn) as avg_turn
            FROM player 
            WHERE game_id > 0 AND game_id != 9999999
        ");
        $turnData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $timeSinceLastTick = time() - ($turnData['last_tick'] ?? 0);
        
        $status['checks']['turn_processing'] = [
            'status' => $timeSinceLastTick < 400 ? 'OK' : 'WARNING',
            'message' => "Last tick was {$timeSinceLastTick} seconds ago",
            'data' => [
                'player_count' => $turnData['player_count'],
                'max_turn' => $turnData['max_turn'],
                'min_turn' => $turnData['min_turn'],
                'avg_turn' => round($turnData['avg_turn'], 2),
                'last_tick_seconds_ago' => $timeSinceLastTick
            ]
        ];
    } catch (Exception $e) {
        $status['checks']['turn_processing'] = [
            'status' => 'FAILED',
            'message' => $e->getMessage()
        ];
    }
    
    // Check 4: Recent turn changes
    try {
        $stmt = $pdo->query("
            SELECT 
                game_id,
                name,
                turn,
                tick,
                (UNIX_TIMESTAMP() - tick) as seconds_since_tick
            FROM player 
            WHERE game_id > 0 AND game_id != 9999999
            ORDER BY tick DESC
            LIMIT 5
        ");
        $recentPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $status['checks']['recent_updates'] = [
            'status' => 'INFO',
            'message' => 'Most recently updated players',
            'data' => $recentPlayers
        ];
    } catch (Exception $e) {
        $status['checks']['recent_updates'] = [
            'status' => 'FAILED',
            'message' => $e->getMessage()
        ];
    }
    
    // Check 5: Server logs (if accessible)
    $logFile = '/var/log/archspace/server.log';
    if (file_exists($logFile)) {
        $lastLines = shell_exec("tail -20 $logFile 2>&1");
        $status['checks']['server_logs'] = [
            'status' => 'INFO',
            'message' => 'Last 20 lines of server log',
            'data' => $lastLines
        ];
    } else {
        $status['checks']['server_logs'] = [
            'status' => 'WARNING',
            'message' => 'Server log file not found'
        ];
    }
    
    // Check 6: Check if update thread is mentioned in logs
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as update_count
            FROM player 
            WHERE tick > (UNIX_TIMESTAMP() - 600)
            AND game_id > 0 AND game_id != 9999999
        ");
        $updateCount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $status['checks']['recent_activity'] = [
            'status' => $updateCount['update_count'] > 0 ? 'OK' : 'CRITICAL',
            'message' => $updateCount['update_count'] > 0 
                ? "{$updateCount['update_count']} players updated in last 10 minutes" 
                : "NO PLAYERS UPDATED IN LAST 10 MINUTES - SERVER MAY BE DOWN!",
            'data' => ['updated_count' => $updateCount['update_count']]
        ];
    } catch (Exception $e) {
        $status['checks']['recent_activity'] = [
            'status' => 'FAILED',
            'message' => $e->getMessage()
        ];
    }
    
    // Overall status
    $hasFailure = false;
    $hasCritical = false;
    foreach ($status['checks'] as $check) {
        if ($check['status'] === 'FAILED') $hasFailure = true;
        if ($check['status'] === 'CRITICAL') $hasCritical = true;
    }
    
    $status['overall'] = [
        'status' => $hasCritical ? 'CRITICAL' : ($hasFailure ? 'FAILED' : 'OK'),
        'message' => $hasCritical ? 'C++ GAME SERVER NOT PROCESSING TURNS!' : 
                    ($hasFailure ? 'Some checks failed' : 'All systems operational')
    ];
    
    return $status;
}

// Output status
echo json_encode(checkServerStatus(), JSON_PRETTY_PRINT);
?>