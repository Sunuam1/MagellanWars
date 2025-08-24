<?php
/**
 * Emergency Turn Fix
 * Run this to immediately fix turns
 */
require_once 'db_config.php';

header('Content-Type: text/plain');

echo "=== MAGELLANWARS TURN FIX ===\n\n";

try {
    $pdo = getDBConnection();
    
    // Force update turns
    $currentTime = time();
    $nextTick = $currentTime + 300;
    
    echo "Current time: " . date('Y-m-d H:i:s', $currentTime) . "\n";
    echo "Next tick: " . date('Y-m-d H:i:s', $nextTick) . "\n\n";
    
    // Get current state
    $stmt = $pdo->query("SELECT game_id, name, turn, tick FROM player WHERE game_id > 0 AND game_id != 9999999");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current players:\n";
    foreach ($players as $player) {
        echo sprintf("  - %s (ID: %d): Turn %d, Tick %d\n", 
            $player['name'], 
            $player['game_id'], 
            $player['turn'],
            $player['tick']
        );
    }
    
    // Fix turns
    $stmt = $pdo->prepare("UPDATE player SET turn = 1, tick = ? WHERE game_id > 0 AND game_id != 9999999 AND turn = 0");
    $stmt->execute([$nextTick]);
    echo "\nFixed " . $stmt->rowCount() . " players with turn = 0\n";
    
    // Force all ticks to be valid
    $stmt = $pdo->prepare("UPDATE player SET tick = ? WHERE game_id > 0 AND game_id != 9999999");
    $stmt->execute([$nextTick]);
    echo "Updated all player ticks to: $nextTick\n";
    
    // Verify
    echo "\nAfter fix:\n";
    $stmt = $pdo->query("SELECT game_id, name, turn, tick FROM player WHERE game_id > 0 AND game_id != 9999999");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($players as $player) {
        echo sprintf("  - %s: Turn %d, Next turn in %d seconds\n", 
            $player['name'], 
            $player['turn'],
            $player['tick'] - $currentTime
        );
    }
    
    echo "\n✅ TURNS FIXED! The turn processor daemon will take over from here.\n";
    echo "Check /monitor.html to verify turns are incrementing.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>