#!/usr/bin/env php
<?php
echo "[TURNS] Starting turn processor\n";
echo "[TURNS] PHP Version: " . PHP_VERSION . "\n";

while (true) {
    try {
        // Use Railway's environment variables
        $db_host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
        $db_name = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'Archspace2';
        $db_user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'archspace';
        $db_pass = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: 'archspace123';
        
        echo "[TURNS] Connecting to $db_host/$db_name as $db_user\n";
        
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $stmt = $pdo->query("SELECT MIN(tick) as next_tick, MAX(turn) as current_turn FROM player WHERE game_id > 0 AND game_id != 9999999");
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentTime = time();
        $nextTick = $info['next_tick'] ?: 0;
        $currentTurn = $info['current_turn'] ?: 0;
        
        echo "[TURNS] Current time: $currentTime, Next tick: $nextTick, Current turn: $currentTurn\n";
        
        // Process if tick is in the past or invalid
        if ($nextTick <= $currentTime || $nextTick == 0 || ($currentTime - $nextTick) > 600) {
            $newTurn = $currentTurn + 1;
            $newTick = $currentTime + 300;
            
            echo "[TURNS] Processing turn $currentTurn -> $newTurn\n";
            
            $stmt = $pdo->prepare("UPDATE player SET turn = ?, tick = ? WHERE game_id > 0 AND game_id != 9999999");
            $stmt->execute([$newTurn, $newTick]);
            
            $pdo->exec("UPDATE player SET production = production + 100, research = research + 10, military = military + 5 WHERE game_id > 0 AND game_id != 9999999");
            
            echo "[TURNS] Turn $newTurn complete!\n";
        }
    } catch (Exception $e) {
        echo "[TURNS] Error: " . $e->getMessage() . "\n";
        echo "[TURNS] Stack trace: " . $e->getTraceAsString() . "\n";
    }
    sleep(10); // Check every 10 seconds
}
?>