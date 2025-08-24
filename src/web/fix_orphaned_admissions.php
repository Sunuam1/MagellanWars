<?php
// Script to fix orphaned admission requests and send notifications
require_once 'db_config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Fixing Orphaned Admission Requests</h2>";
    echo "<pre>";
    
    // Find all pending admission requests (status = 0)
    $pendingStmt = $pdo->prepare("
        SELECT a.*, p.name as player_name, c.name as council_name, c.speaker
        FROM admission a
        JOIN player p ON a.player = p.game_id
        JOIN council c ON a.council = c.id
        WHERE a.status = 0
    ");
    $pendingStmt->execute();
    $pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($pendingRequests) . " pending admission requests.\n\n";
    
    $notificationsSent = 0;
    
    foreach ($pendingRequests as $request) {
        echo "Processing request from {$request['player_name']} to {$request['council_name']}...\n";
        
        // Check if a notification already exists
        $checkNotificationStmt = $pdo->prepare("
            SELECT COUNT(*) FROM council_message 
            WHERE sender = :sender 
            AND receiver = :receiver 
            AND time >= :time
        ");
        $checkNotificationStmt->execute([
            'sender' => $request['player'],
            'receiver' => $request['speaker'],
            'time' => $request['time'] - 60 // Within a minute of the request
        ]);
        
        if ($checkNotificationStmt->fetchColumn() == 0) {
            // Send notification to council speaker
            $msgId = time() + rand(100000, 999999) + $notificationsSent;
            $notifyStmt = $pdo->prepare("
                INSERT INTO council_message (id, type, sender, receiver, time, status)
                VALUES (:id, 1, :sender, :receiver, :time, 0)
            ");
            $notifyStmt->execute([
                'id' => $msgId,
                'sender' => $request['player'],
                'receiver' => $request['speaker'],
                'time' => $request['time']
            ]);
            
            echo "  ✓ Notification sent to council speaker (ID: {$request['speaker']})\n";
            $notificationsSent++;
        } else {
            echo "  - Notification already exists\n";
        }
    }
    
    echo "\n";
    echo "Summary:\n";
    echo "- Total pending requests: " . count($pendingRequests) . "\n";
    echo "- New notifications sent: " . $notificationsSent . "\n";
    
    // Optional: Clean up very old requests (older than 7 days)
    $oneWeekAgo = time() - (7 * 24 * 60 * 60);
    $cleanupStmt = $pdo->prepare("
        DELETE FROM admission 
        WHERE status = 0 AND time < :cutoff
    ");
    $cleanupStmt->execute(['cutoff' => $oneWeekAgo]);
    $cleaned = $cleanupStmt->rowCount();
    
    if ($cleaned > 0) {
        echo "- Cleaned up $cleaned stale requests (older than 7 days)\n";
    }
    
    echo "\nDone! Council speakers should now see all pending admission requests.\n";
    echo "</pre>";
    
    echo '<br><a href="council.php">Return to Council Page</a>';
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>