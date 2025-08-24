<?php
// Simple turn processor to make the game actually playable!
session_start();
require_once 'db_config.php';

// Admin check (you might want to add proper authentication)
$isAdmin = true; // For now, anyone can run it

if (!$isAdmin) {
    die("Access denied");
}

try {
    $pdo = getDBConnection();
    echo "<h2>Turn Processing</h2><pre>";
    
    $turnTime = time();
    echo "Processing turn at: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Get all active players
    $playersStmt = $pdo->query("SELECT * FROM player WHERE game_id > 0");
    $players = $playersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalUpdates = 0;
    
    foreach ($players as $player) {
        if ($player['game_id'] == 0) continue; // Skip empire player
        
        echo "Processing player: {$player['name']}\n";
        
        // Get player's planets
        $planetsStmt = $pdo->prepare("SELECT * FROM planet WHERE owner = :owner");
        $planetsStmt->execute(['owner' => $player['game_id']]);
        $planets = $planetsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalProduction = 0;
        $totalResearch = 0;
        $totalMilitary = 0;
        
        foreach ($planets as $planet) {
            // Calculate production based on buildings
            $factories = $planet['building_factory'] ?? 0;
            $research = $planet['building_research'] ?? 0;
            $military = $planet['building_military'] ?? 0;
            
            // Base production per building
            $prodPerFactory = 10;
            $researchPerLab = 5;
            $militaryPower = $military * 2;
            
            // Apply race bonuses (simplified)
            $raceBonus = 1.0;
            if ($player['race'] == 1) $raceBonus = 1.1; // Human production bonus
            if ($player['race'] == 4) $raceBonus = 1.2; // Xeloss tech bonus for research
            
            // Calculate turn production
            $turnProduction = $factories * $prodPerFactory * $raceBonus;
            $turnResearch = $research * $researchPerLab * ($player['race'] == 4 ? 1.2 : 1.0);
            
            $totalProduction += $turnProduction;
            $totalResearch += $turnResearch;
            $totalMilitary += $militaryPower;
            
            // Update planet (simplified - just tracking output)
            echo "  Planet #{$planet['id']}: +{$turnProduction} PP, +{$turnResearch} RP\n";
        }
        
        // Update player resources
        $newPP = min($player['production'] + $totalProduction, 2000000000);
        $newRP = $player['research'] + $totalResearch;
        $newMP = min($player['military'] + $totalMilitary, 2000000000);
        
        $updateStmt = $pdo->prepare("
            UPDATE player 
            SET production = :pp, 
                research = :rp, 
                military = :mp,
                turn = turn + 1,
                tick = :tick
            WHERE game_id = :id
        ");
        
        $updateStmt->execute([
            'pp' => $newPP,
            'rp' => $newRP,
            'mp' => $newMP,
            'tick' => $turnTime,
            'id' => $player['game_id']
        ]);
        
        echo "  Updated: PP {$player['production']} -> {$newPP} (+" . $totalProduction . ")\n";
        echo "           RP {$player['research']} -> {$newRP} (+" . $totalResearch . ")\n";
        echo "           MP {$player['military']} -> {$newMP} (+" . $totalMilitary . ")\n\n";
        
        $totalUpdates++;
        
        // Process any completed projects (simplified)
        $projectsStmt = $pdo->prepare("
            SELECT * FROM project 
            WHERE owner = :owner AND type < 1000
        ");
        $projectsStmt->execute(['owner' => $player['game_id']]);
        $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($projects as $project) {
            if ($project['invest'] >= $project['cost']) {
                echo "  Project completed: {$project['name']}\n";
                // Mark as completed (type 1000+)
                $pdo->prepare("UPDATE project SET type = type + 1000 WHERE id = :id")
                    ->execute(['id' => $project['id']]);
            }
        }
    }
    
    echo "\nTurn processing complete!\n";
    echo "Updated {$totalUpdates} players.\n";
    
    // Update game time
    $pdo->exec("UPDATE game_status SET last_update = {$turnTime} WHERE id = 1");
    
    echo "\n<strong>Resources have been generated for all players!</strong>\n";
    echo "Run this every 5 minutes to simulate turns, or set up a cron job.\n";
    
    echo "</pre>";
    
    echo '<br><a href="process_turn.php">Process Another Turn</a> | ';
    echo '<a href="game_main.php">Return to Game</a>';
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>