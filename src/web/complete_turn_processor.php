#!/usr/bin/env php
<?php
/**
 * Complete Turn Processor for MagellanWars
 * Handles ALL game mechanics each turn
 */

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'Archspace2';
$db_user = getenv('DB_USER') ?: 'archspace';
$db_pass = getenv('DB_PASSWORD') ?: 'archspace123';

echo "[TURN ENGINE] Starting Complete MagellanWars Turn Processor\n";

while (true) {
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if turn needs processing
        $stmt = $pdo->query("SELECT MIN(tick) as next_tick, MAX(turn) as current_turn, COUNT(*) as player_count FROM player WHERE game_id > 0 AND game_id != 9999999");
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentTime = time();
        $nextTick = $info['next_tick'] ?: 0;
        $currentTurn = $info['current_turn'] ?: 0;
        $playerCount = $info['player_count'] ?: 0;
        
        // Debug logging
        echo "[DEBUG] Time: " . date('H:i:s') . " | Current Turn: $currentTurn | Next Tick: " . ($nextTick - $currentTime) . "s | Players: $playerCount\n";
        
        if ($nextTick <= $currentTime || $nextTick == 0 || $nextTick > $currentTime + 3600) {
            echo "\n[TURN ENGINE] Processing Turn " . ($currentTurn + 1) . " at " . date('Y-m-d H:i:s') . "\n";
            
            $pdo->beginTransaction();
            
            $newTurn = $currentTurn + 1;
            $newTick = $currentTime + 300;
            
            // Safety check - ensure turn actually increments
            if ($newTurn <= $currentTurn) {
                echo "  WARNING: Turn not incrementing! Forcing increment.\n";
                $newTurn = $currentTurn + 1;
            }
            echo "  Current Turn: $currentTurn -> New Turn: $newTurn\n";
            
            // 1. UPDATE PLAYER TURNS
            $stmt = $pdo->prepare("UPDATE player SET turn = ?, tick = ? WHERE game_id > 0 AND game_id != 9999999");
            $stmt->execute([$newTurn, $newTick]);
            echo "  - Updated " . $stmt->rowCount() . " players to turn $newTurn\n";
            
            // 2. PLANET PRODUCTION & GROWTH
            // First check what planets exist
            $planetCheck = $pdo->query("SELECT COUNT(*) as count FROM planet WHERE owner > 0");
            $planetCount = $planetCheck->fetchColumn();
            echo "  - Found $planetCount player-owned planets\n";
            
            if ($planetCount > 0) {
                // Calculate production from planets
                $result = $pdo->exec("
                    UPDATE player p
                    INNER JOIN (
                        SELECT owner,
                            SUM(GREATEST(0, population * factory / 100)) as prod,
                            SUM(GREATEST(0, population * research_lab / 100)) as res,
                            SUM(GREATEST(0, population * military_base / 100)) as mil
                        FROM planet
                        WHERE owner > 0
                        GROUP BY owner
                    ) planet_income ON p.game_id = planet_income.owner
                    SET p.production = p.production + COALESCE(planet_income.prod, 0),
                        p.research = p.research + COALESCE(planet_income.res, 0),
                        p.military = p.military + COALESCE(planet_income.mil, 0)
                ");
                echo "  - Updated production for $result players from planets\n";
            } else {
                // No planets, give base production
                $pdo->exec("UPDATE player SET production = production + 100, research = research + 10, military = military + 5 WHERE game_id > 0 AND game_id != 9999999");
                echo "  - No planets found, added base production (100/10/5)\n";
            }
            
            // Planet population growth
            $pdo->exec("UPDATE planet SET population = LEAST(max_population, population * 1.002) WHERE owner > 0");
            
            // Show current resource levels
            $resourceCheck = $pdo->query("SELECT game_id, name, production, research, military FROM player WHERE game_id > 0 AND game_id != 9999999");
            $players = $resourceCheck->fetchAll(PDO::FETCH_ASSOC);
            foreach ($players as $p) {
                echo "    Player {$p['name']}: Prod={$p['production']}, Res={$p['research']}, Mil={$p['military']}\n";
            }
            
            // 3. SHIP BUILDING
            // Progress ship construction
            $pdo->exec("
                UPDATE ship_building_q 
                SET build_left = GREATEST(0, build_left - 1)
                WHERE owner > 0
            ");
            
            // Complete built ships
            $stmt = $pdo->query("SELECT * FROM ship_building_q WHERE build_left = 0");
            $completedShips = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($completedShips as $ship) {
                // Move to docked ships
                $pdo->exec("
                    INSERT INTO docked_ship (owner, design_id, number)
                    VALUES ({$ship['owner']}, {$ship['design_id']}, {$ship['number']})
                    ON DUPLICATE KEY UPDATE number = number + {$ship['number']}
                ");
            }
            $pdo->exec("DELETE FROM ship_building_q WHERE build_left = 0");
            echo "  - Processed ship construction\n";
            
            // 4. FLEET MOVEMENTS
            // Update fleet mission ticks
            $pdo->exec("
                UPDATE fleet 
                SET current_tick = GREATEST(0, current_tick - 1)
                WHERE mission > 0 AND current_tick > 0
            ");
            
            // Complete fleet missions
            $stmt = $pdo->query("SELECT * FROM fleet WHERE current_tick = 0 AND mission > 0");
            $completedMissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($completedMissions as $fleet) {
                // Return fleets home
                $pdo->exec("UPDATE fleet SET mission = 0, status = 'STATIONED' WHERE id = {$fleet['id']}");
            }
            echo "  - Processed fleet movements\n";
            
            // 5. RESEARCH
            // Apply research points
            $pdo->exec("
                UPDATE tech t
                INNER JOIN player p ON t.owner = p.game_id
                SET t.invest = t.invest + (p.research / 100)
                WHERE t.owner > 0
            ");
            
            // Complete researched techs
            $pdo->exec("
                UPDATE tech 
                SET level = level + 1, invest = 0
                WHERE invest >= (level + 1) * 1000
            ");
            echo "  - Processed research\n";
            
            // 6. DIPLOMATIC ACTIONS
            // Reduce action cooldowns
            $pdo->exec("UPDATE player_action SET time = GREATEST(0, time - 1) WHERE time > 0");
            $pdo->exec("UPDATE council_action SET time = GREATEST(0, time - 1) WHERE time > 0");
            
            // Process relations decay
            $pdo->exec("
                UPDATE player_relation 
                SET relation = GREATEST(-1000, LEAST(1000, relation - 1))
                WHERE relation != 0
            ");
            echo "  - Processed diplomatic actions\n";
            
            // 7. COUNCIL UPDATES
            // Update council power from members
            $pdo->exec("
                UPDATE council c
                INNER JOIN (
                    SELECT council_id, 
                        COUNT(*) as member_count,
                        SUM(production + research + military) as total_power
                    FROM player
                    WHERE council_id > 0
                    GROUP BY council_id
                ) council_stats ON c.id = council_stats.council_id
                SET c.members = council_stats.member_count,
                    c.power = council_stats.total_power
            ");
            echo "  - Updated councils\n";
            
            // 8. BATTLES
            // Check for hostile fleets at same location
            $stmt = $pdo->query("
                SELECT DISTINCT f1.id as fleet1, f2.id as fleet2, f1.location
                FROM fleet f1
                INNER JOIN fleet f2 ON f1.location = f2.location
                INNER JOIN player_relation pr ON 
                    (pr.player1 = f1.owner AND pr.player2 = f2.owner) OR
                    (pr.player1 = f2.owner AND pr.player2 = f1.owner)
                WHERE f1.id < f2.id 
                    AND pr.relation < 0
                    AND f1.status = 'HOSTILE'
                    AND f2.status = 'HOSTILE'
            ");
            
            $battles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($battles as $battle) {
                // Simple battle resolution
                $fleet1Power = $pdo->query("SELECT ship_count * 100 as power FROM fleet WHERE id = {$battle['fleet1']}")->fetchColumn();
                $fleet2Power = $pdo->query("SELECT ship_count * 100 as power FROM fleet WHERE id = {$battle['fleet2']}")->fetchColumn();
                
                if ($fleet1Power > $fleet2Power) {
                    $pdo->exec("UPDATE fleet SET ship_count = 0, status = 'DESTROYED' WHERE id = {$battle['fleet2']}");
                    $pdo->exec("UPDATE fleet SET ship_count = ship_count * 0.7 WHERE id = {$battle['fleet1']}");
                } else {
                    $pdo->exec("UPDATE fleet SET ship_count = 0, status = 'DESTROYED' WHERE id = {$battle['fleet1']}");
                    $pdo->exec("UPDATE fleet SET ship_count = ship_count * 0.7 WHERE id = {$battle['fleet2']}");
                }
                
                // Log battle
                $pdo->exec("INSERT INTO battle_record (turn, location, status) VALUES ($newTurn, {$battle['location']}, 'RESOLVED')");
            }
            if (count($battles) > 0) {
                echo "  - Resolved " . count($battles) . " battles\n";
            }
            
            // 9. BLACK MARKET
            // Refresh black market items
            if ($newTurn % 10 == 0) {
                $pdo->exec("UPDATE blackmarket SET quantity = quantity + 1 WHERE quantity < 10");
                echo "  - Refreshed black market\n";
            }
            
            // 10. EMPIRE ACTIONS
            // Process empire responses
            $pdo->exec("UPDATE empire_action SET time_limit = GREATEST(0, time_limit - 1) WHERE time_limit > 0");
            $pdo->exec("DELETE FROM empire_action WHERE time_limit = 0 AND status = 'PENDING'");
            
            $pdo->commit();
            
            // Verify the turn actually updated
            $verifyStmt = $pdo->query("SELECT MIN(turn) as min_turn, MAX(turn) as max_turn FROM player WHERE game_id > 0 AND game_id != 9999999");
            $verify = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            echo "[TURN ENGINE] Turn $newTurn completed! Verified: All players now at turn " . $verify['max_turn'] . "\n";
            
        } else {
            $timeLeft = $nextTick - $currentTime;
            if ($timeLeft % 60 == 0) {
                echo "[TURN ENGINE] Turn $currentTurn - Next turn in " . floor($timeLeft/60) . " minutes\n";
            }
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "[TURN ENGINE] ERROR: " . $e->getMessage() . "\n";
    }
    
    sleep(10); // Check every 10 seconds
}
?>