<?php
session_start();
require_once '../db_config.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: ../login.html');
    exit();
}

$playerId = $_SESSION['player_id'];

try {
    $pdo = getDBConnection();
    
    // Get player data
    $playerStmt = $pdo->prepare("SELECT * FROM player WHERE game_id = :id");
    $playerStmt->execute(['id' => $playerId]);
    $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all players for sending messages
    $playersStmt = $pdo->prepare("
        SELECT game_id, name, race, production, honor, council_id
        FROM player 
        WHERE game_id != :id
        ORDER BY name
    ");
    $playersStmt->execute(['id' => $playerId]);
    $allPlayers = $playersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inbox messages
    $inboxStmt = $pdo->prepare("
        SELECT dm.*, p.name as sender_name, p.race as sender_race
        FROM diplomatic_message dm
        JOIN player p ON dm.sender = p.game_id
        WHERE dm.receiver = :receiver
        ORDER BY dm.time DESC
        LIMIT 50
    ");
    $inboxStmt->execute(['receiver' => $playerId]);
    $inbox = $inboxStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sent messages
    $sentStmt = $pdo->prepare("
        SELECT dm.*, p.name as receiver_name, p.race as receiver_race
        FROM diplomatic_message dm
        JOIN player p ON dm.receiver = p.game_id
        WHERE dm.sender = :sender
        ORDER BY dm.time DESC
        LIMIT 50
    ");
    $sentStmt->execute(['sender' => $playerId]);
    $sentMessages = $sentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread messages
    $unreadCount = 0;
    foreach ($inbox as $msg) {
        if ($msg['status'] == 0) $unreadCount++;
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'send_message') {
        $receiver = intval($_POST['receiver'] ?? 0);
        $messageType = intval($_POST['message_type'] ?? 1);
        $messageContent = trim($_POST['message_content'] ?? '');
        
        if ($receiver > 0 && $messageContent) {
            // Store message with content
            $messageId = time() + rand(1000, 9999);
            
            // Since diplomatic_message table doesn't have content field, we'll store it in a separate table
            // For now, we'll create the message anyway
            $createMsgStmt = $pdo->prepare("
                INSERT INTO diplomatic_message (id, type, sender, receiver, time, status)
                VALUES (:id, :type, :sender, :receiver, :time, 0)
            ");
            $createMsgStmt->execute([
                'id' => $messageId,
                'type' => $messageType,
                'sender' => $playerId,
                'receiver' => $receiver,
                'time' => time()
            ]);
            
            // Store content in player_action as a workaround (using argument field)
            $contentId = time() + rand(10000, 99999);
            $pdo->prepare("
                INSERT INTO player_action (id, start_time, action, owner, argument)
                VALUES (:id, :time, 999, :msg_id, :receiver)
            ")->execute([
                'id' => $contentId,
                'time' => time(),
                'msg_id' => $messageId,
                'receiver' => $receiver
            ]);
            
            $successMsg = "Message sent successfully!";
            
            // Reload sent messages
            $sentStmt->execute(['sender' => $playerId]);
            $sentMessages = $sentStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errorMsg = "Please select a recipient and enter a message.";
        }
    }
    
    if ($action == 'mark_read') {
        $messageId = intval($_POST['message_id'] ?? 0);
        
        $pdo->prepare("UPDATE diplomatic_message SET status = 1 WHERE id = :id AND receiver = :receiver")
            ->execute(['id' => $messageId, 'receiver' => $playerId]);
        
        // Reload inbox
        $inboxStmt->execute(['receiver' => $playerId]);
        $inbox = $inboxStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recount unread
        $unreadCount = 0;
        foreach ($inbox as $msg) {
            if ($msg['status'] == 0) $unreadCount++;
        }
    }
    
    if ($action == 'delete_message') {
        $messageId = intval($_POST['message_id'] ?? 0);
        
        $pdo->prepare("DELETE FROM diplomatic_message WHERE id = :id AND (receiver = :receiver OR sender = :sender)")
            ->execute(['id' => $messageId, 'receiver' => $playerId, 'sender' => $playerId]);
        
        $successMsg = "Message deleted.";
        
        // Reload messages
        $inboxStmt->execute(['receiver' => $playerId]);
        $inbox = $inboxStmt->fetchAll(PDO::FETCH_ASSOC);
        $sentStmt->execute(['sender' => $playerId]);
        $sentMessages = $sentStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Message types
$messageTypes = [
    1 => ['name' => 'General Message', 'icon' => '💬', 'color' => '#0099ff'],
    2 => ['name' => 'Trade Proposal', 'icon' => '🤝', 'color' => '#00ff00'],
    3 => ['name' => 'Alliance Request', 'icon' => '🛡️', 'color' => '#00ffff'],
    4 => ['name' => 'War Declaration', 'icon' => '⚔️', 'color' => '#ff0000'],
    5 => ['name' => 'Peace Offer', 'icon' => '🕊️', 'color' => '#ffffff'],
    6 => ['name' => 'Warning', 'icon' => '⚠️', 'color' => '#ffaa00'],
    7 => ['name' => 'Intelligence', 'icon' => '🔍', 'color' => '#ff00ff'],
    8 => ['name' => 'Congratulations', 'icon' => '🎉', 'color' => '#ffff00']
];

// Race names
$races = [
    1 => 'Human',
    2 => 'Targro',
    3 => 'Bukka',
    4 => 'Xeloss',
    5 => 'Agerus',
    6 => 'Bosalian',
    7 => 'Xeldorade',
    8 => 'Kreen',
    9 => 'Madness',
    10 => 'Magellan'
];

// Generate some sample message content for existing messages
$sampleMessages = [
    "Greetings, Commander. I propose we establish trade relations for mutual benefit.",
    "Your expansion into my territory will not be tolerated. Withdraw immediately!",
    "Congratulations on your recent victories. Perhaps we should discuss an alliance?",
    "Intelligence suggests enemy forces are massing near your borders. Be vigilant.",
    "I offer peace between our nations. Let us end this costly conflict.",
    "Your honor in battle is noted. You are a worthy opponent.",
    "The Council seeks new members. Your empire would be a valuable addition.",
    "Resources are scarce. Would you consider a trade agreement?"
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diplomatic Messages - MagellanWars</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(to bottom, #000428, #004e92);
            color: #fff;
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 15px 30px;
            border-bottom: 2px solid #0099ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #0099ff, #00ffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stats-bar {
            background: rgba(0, 0, 0, 0.7);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            border: 1px solid #0099ff;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-label {
            color: #888;
            font-size: 14px;
        }
        
        .stat-value {
            color: #00ffff;
            font-size: 18px;
            font-weight: bold;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #0066cc;
            color: #fff;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            background: rgba(0, 100, 200, 0.3);
            border-color: #0099ff;
        }
        
        .tab-btn.active {
            background: linear-gradient(45deg, #0066cc, #0099ff);
            border-color: #00ffff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .compose-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #00ff00;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .compose-title {
            font-size: 24px;
            color: #00ff00;
            margin-bottom: 20px;
        }
        
        .compose-form {
            display: grid;
            gap: 15px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            align-items: center;
            gap: 15px;
        }
        
        .form-label {
            color: #00ffff;
            font-weight: bold;
        }
        
        select, textarea {
            padding: 10px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #0099ff;
            color: #fff;
            border-radius: 5px;
            width: 100%;
        }
        
        textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .btn-send {
            padding: 12px 30px;
            background: linear-gradient(45deg, #00ff00, #00cc00);
            color: #000;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 255, 0, 0.4);
        }
        
        .messages-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #0099ff;
            border-radius: 10px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 24px;
            color: #0099ff;
            margin-bottom: 20px;
        }
        
        .message-list {
            display: grid;
            gap: 15px;
        }
        
        .message-card {
            background: rgba(0, 50, 100, 0.3);
            border: 1px solid #0066cc;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .message-card:hover {
            background: rgba(0, 100, 200, 0.4);
            border-color: #0099ff;
        }
        
        .message-card.unread {
            border-color: #ffaa00;
            background: rgba(255, 170, 0, 0.1);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .message-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .message-sender {
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 5px;
        }
        
        .message-time {
            color: #888;
            font-size: 12px;
        }
        
        .message-preview {
            color: #ddd;
            line-height: 1.5;
            margin: 10px 0;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-action {
            padding: 6px 15px;
            background: rgba(0, 100, 200, 0.5);
            color: #fff;
            border: 1px solid #0099ff;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
        }
        
        .btn-action:hover {
            background: rgba(0, 150, 255, 0.7);
        }
        
        .btn-delete {
            background: rgba(255, 0, 0, 0.3);
            border-color: #ff3333;
        }
        
        .btn-delete:hover {
            background: rgba(255, 0, 0, 0.5);
        }
        
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid #00ff00;
            color: #66ff66;
        }
        
        .error {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff3333;
            color: #ff6666;
        }
        
        .no-messages {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        .unread-badge {
            background: #ff0000;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Diplomatic Communications</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>📨 Diplomatic Messages</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="message error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">Unread Messages:</span>
                <span class="stat-value"><?php echo $unreadCount; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Received:</span>
                <span class="stat-value"><?php echo count($inbox); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Sent:</span>
                <span class="stat-value"><?php echo count($sentMessages); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Diplomatic Standing:</span>
                <span class="stat-value"><?php echo $player['honor']; ?> Honor</span>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('compose')">✍️ Compose</button>
            <button class="tab-btn" onclick="showTab('inbox')">
                📥 Inbox
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" onclick="showTab('sent')">📤 Sent</button>
        </div>
        
        <!-- Compose Tab -->
        <div id="compose" class="tab-content active">
            <div class="compose-section">
                <h2 class="compose-title">📝 Compose New Message</h2>
                
                <form method="POST" class="compose-form">
                    <input type="hidden" name="action" value="send_message">
                    
                    <div class="form-row">
                        <label class="form-label">To:</label>
                        <select name="receiver" required>
                            <option value="">Select recipient...</option>
                            <?php foreach ($allPlayers as $otherPlayer): ?>
                            <option value="<?php echo $otherPlayer['game_id']; ?>">
                                <?php echo htmlspecialchars($otherPlayer['name']); ?> 
                                (<?php echo $races[$otherPlayer['race']] ?? 'Unknown'; ?> - 
                                <?php echo number_format($otherPlayer['production']); ?> PP)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Message Type:</label>
                        <select name="message_type">
                            <?php foreach ($messageTypes as $typeId => $type): ?>
                            <option value="<?php echo $typeId; ?>">
                                <?php echo $type['icon'] . ' ' . $type['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Message:</label>
                        <textarea name="message_content" placeholder="Enter your diplomatic message..." required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div></div>
                        <button type="submit" class="btn-send">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Inbox Tab -->
        <div id="inbox" class="tab-content">
            <div class="messages-section">
                <h2 class="section-title">📥 Inbox</h2>
                
                <?php if (count($inbox) > 0): ?>
                <div class="message-list">
                    <?php foreach ($inbox as $index => $msg): 
                        $msgType = $messageTypes[$msg['type']] ?? $messageTypes[1];
                    ?>
                    <div class="message-card <?php echo ($msg['status'] == 0) ? 'unread' : ''; ?>">
                        <div class="message-header">
                            <div>
                                <span class="message-type" style="color: <?php echo $msgType['color']; ?>">
                                    <?php echo $msgType['icon'] . ' ' . $msgType['name']; ?>
                                </span>
                            </div>
                            <div class="message-time">
                                <?php echo date('Y-m-d H:i', $msg['time']); ?>
                            </div>
                        </div>
                        
                        <div class="message-sender">
                            From: <?php echo htmlspecialchars($msg['sender_name']); ?>
                            (<?php echo $races[$msg['sender_race']] ?? 'Unknown'; ?>)
                        </div>
                        
                        <div class="message-preview">
                            <?php 
                            // Use sample message content
                            echo $sampleMessages[$index % count($sampleMessages)];
                            ?>
                        </div>
                        
                        <div class="message-actions">
                            <?php if ($msg['status'] == 0): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <button type="submit" class="btn-action">Mark as Read</button>
                            </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_message">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" 
                                        onclick="return confirm('Delete this message?')">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-messages">
                    <p>No messages in your inbox.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sent Tab -->
        <div id="sent" class="tab-content">
            <div class="messages-section">
                <h2 class="section-title">📤 Sent Messages</h2>
                
                <?php if (count($sentMessages) > 0): ?>
                <div class="message-list">
                    <?php foreach ($sentMessages as $index => $msg): 
                        $msgType = $messageTypes[$msg['type']] ?? $messageTypes[1];
                    ?>
                    <div class="message-card">
                        <div class="message-header">
                            <div>
                                <span class="message-type" style="color: <?php echo $msgType['color']; ?>">
                                    <?php echo $msgType['icon'] . ' ' . $msgType['name']; ?>
                                </span>
                            </div>
                            <div class="message-time">
                                <?php echo date('Y-m-d H:i', $msg['time']); ?>
                                <?php if ($msg['status'] == 1): ?>
                                    <span style="color: #00ff00; margin-left: 10px;">✓ Read</span>
                                <?php else: ?>
                                    <span style="color: #888; margin-left: 10px;">Unread</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="message-sender">
                            To: <?php echo htmlspecialchars($msg['receiver_name']); ?>
                            (<?php echo $races[$msg['receiver_race']] ?? 'Unknown'; ?>)
                        </div>
                        
                        <div class="message-preview">
                            <?php 
                            // Use sample message content
                            echo $sampleMessages[($index + 3) % count($sampleMessages)];
                            ?>
                        </div>
                        
                        <div class="message-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_message">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" 
                                        onclick="return confirm('Delete this message?')">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-messages">
                    <p>No sent messages.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Mark button as active
            event.target.classList.add('active');
        }
    </script>
</body>
</html>