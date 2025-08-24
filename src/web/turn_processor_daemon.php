#!/usr/bin/env php
<?php
/**
 * Turn Processor Daemon
 * Runs continuously to process turns every 5 minutes
 */

// Database configuration
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'Archspace2';
$db_user = getenv('DB_USER') ?: 'archspace';
$db_pass = getenv('DB_PASSWORD') ?: 'archspace123';

echo "[TURN DAEMON] Starting MagellanWars Turn Processor\n";
echo "[TURN DAEMON] Database: $db_host/$db_name\n";

// Main loop
while (true) {
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check current state
        $stmt = $pdo->query("SELECT MIN(tick) as next_tick, MAX(turn) as max_turn, COUNT(*) as player_count 
                             FROM player WHERE game_id > 0 AND game_id != 9999999");
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentTime = time();
        $nextTick = $info['next_tick'] ?: 0;
        $currentTurn = $info['max_turn'] ?: 0;
        $playerCount = $info['player_count'] ?: 0;
        
        echo "[TURN DAEMON] " . date('Y-m-d H:i:s') . " - Turn: $currentTurn, Players: $playerCount, Next tick: " . ($nextTick - $currentTime) . " seconds\n";
        
        // Process turn if it's time (or if ticks are invalid)
        if ($nextTick <= $currentTime || $nextTick == 0 || $nextTick > $currentTime + 3600) {
            $newTurn = $currentTurn + 1;
            $newTick = $currentTime + 300; // 5 minutes
            
            // Update all players
            $stmt = $pdo->prepare("UPDATE player 
                                  SET turn = :turn, tick = :tick 
                                  WHERE game_id > 0 AND game_id != 9999999");
            $stmt->execute(['turn' => $newTurn, 'tick' => $newTick]);
            $updated = $stmt->rowCount();
            
            // Update player resources based on their planets
            $pdo->exec("UPDATE player p
                       JOIN (
                           SELECT owner, 
                                  SUM(GREATEST(0, population * (100 - tax_rate) / 100)) as total_production,
                                  SUM(GREATEST(0, population * research_investment / 100)) as total_research,
                                  SUM(GREATEST(0, population * military_investment / 100)) as total_military
                           FROM planet 
                           WHERE owner > 0
                           GROUP BY owner
                       ) planet_prod ON p.game_id = planet_prod.owner
                       SET p.production = p.production + COALESCE(planet_prod.total_production, 100),
                           p.research = p.research + COALESCE(planet_prod.total_research, 10),
                           p.military = p.military + COALESCE(planet_prod.total_military, 5)
                       WHERE p.game_id > 0 AND p.game_id != 9999999");
            
            // Update planet populations (growth)
            $pdo->exec("UPDATE planet 
                       SET population = LEAST(max_population, population * 1.01)
                       WHERE owner > 0");
            
            // Process building construction
            $pdo->exec("UPDATE planet 
                       SET building_production = GREATEST(0, building_production - 10)
                       WHERE building_production > 0");
            
            // Update fleet movements
            $pdo->exec("UPDATE fleet 
                       SET current_tick = GREATEST(0, current_tick - 1)
                       WHERE current_tick > 0");
            
            // Process research progress
            $pdo->exec("UPDATE player 
                       SET research_points = research_points + (research / 100)
                       WHERE game_id > 0 AND game_id != 9999999");
            
            echo "[TURN DAEMON] *** TURN $newTurn PROCESSED *** Updated $updated players at " . date('Y-m-d H:i:s') . "\n";
            
            // Log to file
            file_put_contents('/var/log/archspace/turns.log', 
                             date('Y-m-d H:i:s') . " - Turn $newTurn processed, $updated players updated\n", 
                             FILE_APPEND);
        }
        
    } catch (Exception $e) {
        echo "[TURN DAEMON] ERROR: " . $e->getMessage() . "\n";
    }
    
    // Check every 30 seconds
    sleep(30);
}
?>