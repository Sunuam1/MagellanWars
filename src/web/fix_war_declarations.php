<?php
// Script to fix war declarations that were sent but not activated
require_once 'db_config.php';

try {
    $pdo = getDBConnection();
    echo "<h2>War Declaration Fix Script</h2><pre>";
    
    // Find all war declaration messages (type 4)
    $warMessagesStmt = $pdo->prepare("
        SELECT dm.*, 
               ps.name as sender_name, 
               pr.name as receiver_name
        FROM diplomatic_message dm
        JOIN player ps ON dm.sender = ps.game_id
        JOIN player pr ON dm.receiver = pr.game_id
        WHERE dm.type = 4
        ORDER BY dm.time DESC
    ");
    $warMessagesStmt->execute();
    $warMessages = $warMessagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($warMessages) . " war declaration messages.\n\n";
    
    $relationsCreated = 0;
    $relationsUpdated = 0;
    
    foreach ($warMessages as $war) {
        echo "Processing war declaration from {$war['sender_name']} to {$war['receiver_name']}...\n";
        
        // Check if a relation already exists
        $checkRelationStmt = $pdo->prepare("
            SELECT * FROM player_relation 
            WHERE (player1 = :p1 AND player2 = :p2) OR (player1 = :p3 AND player2 = :p4)
        ");
        $checkRelationStmt->execute([
            'p1' => $war['sender'],
            'p2' => $war['receiver'],
            'p3' => $war['receiver'],
            'p4' => $war['sender']
        ]);
        
        $existingRelation = $checkRelationStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRelation) {
            // Check if it's already at war
            if ($existingRelation['relation'] >= 0) {
                // Update to war status
                $updateRelationStmt = $pdo->prepare("
                    UPDATE player_relation 
                    SET relation = -2, time = :time
                    WHERE id = :id
                ");
                $updateRelationStmt->execute([
                    'time' => $war['time'],
                    'id' => $existingRelation['id']
                ]);
                echo "  ✓ Updated existing relation to WAR status\n";
                $relationsUpdated++;
            } else {
                echo "  - Already at war or hostile (relation: {$existingRelation['relation']})\n";
            }
        } else {
            // Create new war relation
            $relationId = time() + rand(100000, 999999) + $relationsCreated;
            $createRelationStmt = $pdo->prepare("
                INSERT INTO player_relation (id, player1, player2, relation, time)
                VALUES (:id, :p1, :p2, -2, :time)
            ");
            $createRelationStmt->execute([
                'id' => $relationId,
                'p1' => min($war['sender'], $war['receiver']),
                'p2' => max($war['sender'], $war['receiver']),
                'time' => $war['time']
            ]);
            echo "  ✓ Created new WAR relation\n";
            $relationsCreated++;
        }
    }
    
    echo "\n";
    echo "Summary:\n";
    echo "- Total war declarations: " . count($warMessages) . "\n";
    echo "- New war relations created: " . $relationsCreated . "\n";
    echo "- Relations updated to war: " . $relationsUpdated . "\n";
    
    echo "\n<strong>War declaration fix completed!</strong>\n";
    echo "\nAll war declarations are now active in the Treaties section.\n";
    echo "</pre>";
    
    echo '<br><a href="diplomacy/treaties.php">Go to Treaties</a> | ';
    echo '<a href="diplomacy/messages.php">Go to Messages</a> | ';
    echo '<a href="game_main.php">Return to Game</a>';
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>