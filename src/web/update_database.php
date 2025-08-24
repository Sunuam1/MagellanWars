<?php
// Database update script to add message content tables
require_once 'db_config.php';

try {
    $pdo = getDBConnection();
    echo "<h2>Database Update Script</h2><pre>";
    
    // Check if diplomatic_message_content table exists
    $checkTable1 = $pdo->query("SHOW TABLES LIKE 'diplomatic_message_content'");
    if ($checkTable1->rowCount() == 0) {
        echo "Creating diplomatic_message_content table...\n";
        $pdo->exec("
            CREATE TABLE diplomatic_message_content (
                message_id INT UNSIGNED NOT NULL,
                content TEXT,
                PRIMARY KEY(message_id)
            )
        ");
        echo "✓ diplomatic_message_content table created\n";
    } else {
        echo "- diplomatic_message_content table already exists\n";
    }
    
    // Check if council_message_content table exists
    $checkTable2 = $pdo->query("SHOW TABLES LIKE 'council_message_content'");
    if ($checkTable2->rowCount() == 0) {
        echo "Creating council_message_content table...\n";
        $pdo->exec("
            CREATE TABLE council_message_content (
                message_id INT UNSIGNED NOT NULL,
                content TEXT,
                PRIMARY KEY(message_id)
            )
        ");
        echo "✓ council_message_content table created\n";
    } else {
        echo "- council_message_content table already exists\n";
    }
    
    // Migrate any existing messages to have placeholder content
    echo "\nChecking for messages without content...\n";
    
    // Get diplomatic messages without content
    $orphanedMessages = $pdo->query("
        SELECT dm.id, dm.type, dm.time 
        FROM diplomatic_message dm
        LEFT JOIN diplomatic_message_content dmc ON dm.id = dmc.message_id
        WHERE dmc.message_id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($orphanedMessages) > 0) {
        echo "Found " . count($orphanedMessages) . " diplomatic messages without content.\n";
        
        $messageTypes = [
            1 => "Greetings. I have an important matter to discuss with you.",
            2 => "I propose we establish trade relations for mutual benefit.",
            3 => "Our empires could achieve greatness together. Will you consider an alliance?",
            4 => "Your actions have left me no choice. This means war!",
            5 => "Let us end this conflict. I offer peace between our nations.",
            6 => "Consider this a warning. Your current course of action will have consequences.",
            7 => "Intelligence suggests developments you should be aware of.",
            8 => "Congratulations on your recent achievements!"
        ];
        
        foreach ($orphanedMessages as $msg) {
            $content = $messageTypes[$msg['type']] ?? "Message from another empire.";
            $pdo->prepare("
                INSERT INTO diplomatic_message_content (message_id, content) 
                VALUES (:id, :content)
            ")->execute(['id' => $msg['id'], 'content' => $content]);
        }
        echo "✓ Added default content to orphaned messages\n";
    } else {
        echo "- All diplomatic messages have content\n";
    }
    
    // Get council messages without content
    $orphanedCouncilMessages = $pdo->query("
        SELECT cm.id, cm.type 
        FROM council_message cm
        LEFT JOIN council_message_content cmc ON cm.id = cmc.message_id
        WHERE cmc.message_id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($orphanedCouncilMessages) > 0) {
        echo "Found " . count($orphanedCouncilMessages) . " council messages without content.\n";
        
        foreach ($orphanedCouncilMessages as $msg) {
            $content = "Council admission request pending your approval.";
            $pdo->prepare("
                INSERT INTO council_message_content (message_id, content) 
                VALUES (:id, :content)
            ")->execute(['id' => $msg['id'], 'content' => $content]);
        }
        echo "✓ Added default content to council messages\n";
    } else {
        echo "- All council messages have content\n";
    }
    
    echo "\n<strong>Database update completed successfully!</strong>\n";
    echo "</pre>";
    
    echo '<br><a href="diplomacy/messages.php">Go to Messages</a> | ';
    echo '<a href="diplomacy/council.php">Go to Council</a> | ';
    echo '<a href="game_main.php">Return to Game</a>';
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>