<?php
// API endpoint to get current turn status and timing
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once 'db_config.php';

try {
    $pdo = getDBConnection();
    
    // Get the last turn update time from any active player
    $stmt = $pdo->query("
        SELECT 
            MAX(tick) as last_tick,
            MAX(turn) as current_turn,
            COUNT(DISTINCT game_id) as active_players
        FROM player 
        WHERE game_id > 0 AND tick IS NOT NULL
    ");
    $turnData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get server time settings (5 minutes = 300 seconds per turn)
    $secondsPerTurn = 300;
    $currentTime = time();
    
    // Calculate next turn time
    $lastTick = $turnData['last_tick'] ?? $currentTime;
    $timeSinceLastTurn = $currentTime - $lastTick;
    $timeUntilNextTurn = $secondsPerTurn - ($timeSinceLastTurn % $secondsPerTurn);
    $nextTurnTime = $currentTime + $timeUntilNextTurn;
    
    // Get some game statistics
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM player WHERE game_id > 0) as total_players,
            (SELECT COUNT(*) FROM planet) as total_planets,
            (SELECT COUNT(*) FROM fleet) as total_fleets,
            (SELECT COUNT(*) FROM council) as total_councils
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'current_turn' => $turnData['current_turn'] ?? 0,
        'seconds_until_next_turn' => $timeUntilNextTurn,
        'next_turn_time' => $nextTurnTime,
        'last_turn_time' => $lastTick,
        'seconds_per_turn' => $secondsPerTurn,
        'server_time' => $currentTime,
        'active_players' => $turnData['active_players'] ?? 0,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get turn status'
    ]);
}
?>