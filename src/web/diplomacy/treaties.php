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
    
    // Get player relations (treaties)
    $relationsStmt = $pdo->prepare("
        SELECT pr.*, p.name as player_name
        FROM player_relation pr
        JOIN player p ON (pr.player1 = p.game_id OR pr.player2 = p.game_id)
        WHERE (pr.player1 = :player OR pr.player2 = :player2) AND p.game_id != :player3
        ORDER BY pr.time DESC
    ");
    $relationsStmt->execute(['player' => $playerId, 'player2' => $playerId, 'player3' => $playerId]);
    $relations = $relationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get diplomatic messages for treaty proposals
    $messagesStmt = $pdo->prepare("
        SELECT dm.*, p.name as sender_name
        FROM diplomatic_message dm
        JOIN player p ON dm.sender = p.game_id
        WHERE dm.receiver = :receiver AND dm.status = 0
        ORDER BY dm.time DESC
    ");
    $messagesStmt->execute(['receiver' => $playerId]);
    $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get other players for new treaties
    $playersStmt = $pdo->prepare("
        SELECT game_id, name, production, honor, council_id
        FROM player 
        WHERE game_id != :id
        ORDER BY production DESC
    ");
    $playersStmt->execute(['id' => $playerId]);
    $otherPlayers = $playersStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Relation types
$relationTypes = [
    -3 => ['name' => 'Total War', 'color' => '#ff0000', 'icon' => '⚔️'],
    -2 => ['name' => 'War', 'color' => '#ff6600', 'icon' => '🗡️'],
    -1 => ['name' => 'Hostile', 'color' => '#ff9900', 'icon' => '😠'],
    0 => ['name' => 'Neutral', 'color' => '#888888', 'icon' => '😐'],
    1 => ['name' => 'Peace Treaty', 'color' => '#66ff66', 'icon' => '🕊️'],
    2 => ['name' => 'Trade Agreement', 'color' => '#00ff00', 'icon' => '🤝'],
    3 => ['name' => 'Alliance', 'color' => '#00ffff', 'icon' => '🛡️'],
];

// Message types
$messageTypes = [
    1 => 'Peace Proposal',
    2 => 'Trade Agreement',
    3 => 'Alliance Request',
    4 => 'War Declaration',
    5 => 'Treaty Cancellation'
];

// Handle treaty actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'propose_treaty') {
        $targetPlayer = intval($_POST['target_player'] ?? 0);
        $treatyType = intval($_POST['treaty_type'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if ($targetPlayer > 0) {
            // Create diplomatic message
            $messageId = time() + rand(1000, 9999);
            
            $createMessageStmt = $pdo->prepare("
                INSERT INTO diplomatic_message (id, type, sender, receiver, time, status)
                VALUES (:id, :type, :sender, :receiver, :time, 0)
            ");
            $createMessageStmt->execute([
                'id' => $messageId,
                'type' => $treatyType,
                'sender' => $playerId,
                'receiver' => $targetPlayer,
                'time' => time()
            ]);
            
            $successMsg = "Treaty proposal sent!";
        }
    }
    
    if ($action == 'accept_treaty') {
        $messageId = intval($_POST['message_id'] ?? 0);
        
        // Get message details
        $msgStmt = $pdo->prepare("SELECT * FROM diplomatic_message WHERE id = :id AND receiver = :receiver");
        $msgStmt->execute(['id' => $messageId, 'receiver' => $playerId]);
        $msg = $msgStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($msg) {
            // Determine relation value based on message type
            $relationValue = 0;
            switch ($msg['type']) {
                case 1: $relationValue = 1; break; // Peace
                case 2: $relationValue = 2; break; // Trade
                case 3: $relationValue = 3; break; // Alliance
                case 4: $relationValue = -2; break; // War
            }
            
            // Check if relation already exists
            $checkRelationStmt = $pdo->prepare("
                SELECT * FROM player_relation 
                WHERE (player1 = :p1 AND player2 = :p2) OR (player1 = :p3 AND player2 = :p4)
            ");
            $checkRelationStmt->execute([
                'p1' => $playerId,
                'p2' => $msg['sender'],
                'p3' => $msg['sender'],
                'p4' => $playerId
            ]);
            
            if ($checkRelationStmt->rowCount() > 0) {
                // Update existing relation
                $updateRelationStmt = $pdo->prepare("
                    UPDATE player_relation 
                    SET relation = :relation, time = :time
                    WHERE (player1 = :p1 AND player2 = :p2) OR (player1 = :p3 AND player2 = :p4)
                ");
                $updateRelationStmt->execute([
                    'relation' => $relationValue,
                    'time' => time(),
                    'p1' => $playerId,
                    'p2' => $msg['sender'],
                    'p3' => $msg['sender'],
                    'p4' => $playerId
                ]);
            } else {
                // Create new relation
                $relationId = time() + rand(1000, 9999);
                $createRelationStmt = $pdo->prepare("
                    INSERT INTO player_relation (id, player1, player2, relation, time)
                    VALUES (:id, :p1, :p2, :relation, :time)
                ");
                $createRelationStmt->execute([
                    'id' => $relationId,
                    'p1' => min($playerId, $msg['sender']),
                    'p2' => max($playerId, $msg['sender']),
                    'relation' => $relationValue,
                    'time' => time()
                ]);
            }
            
            // Mark message as read
            $pdo->prepare("UPDATE diplomatic_message SET status = 1 WHERE id = :id")
                ->execute(['id' => $messageId]);
            
            $successMsg = "Treaty accepted!";
            
            // Reload relations
            $relationsStmt->execute(['player' => $playerId, 'player2' => $playerId, 'player3' => $playerId]);
            $relations = $relationsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $messagesStmt->execute(['receiver' => $playerId]);
            $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    if ($action == 'cancel_treaty') {
        $relationId = intval($_POST['relation_id'] ?? 0);
        
        // Delete relation
        $pdo->prepare("DELETE FROM player_relation WHERE id = :id AND (player1 = :p1 OR player2 = :p2)")
            ->execute(['id' => $relationId, 'p1' => $playerId, 'p2' => $playerId]);
        
        $successMsg = "Treaty cancelled!";
        
        // Reload relations
        $relationsStmt->execute(['player' => $playerId, 'player2' => $playerId, 'player3' => $playerId]);
        $relations = $relationsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Treaties - MagellanWars</title>
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
            background: linear-gradient(45deg, #00ffff, #0099ff);
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
        
        .section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #0099ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 24px;
            color: #00ffff;
            margin-bottom: 20px;
        }
        
        .treaties-grid {
            display: grid;
            gap: 15px;
        }
        
        .treaty-card {
            background: rgba(0, 50, 100, 0.3);
            border: 1px solid #0066cc;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .treaty-info {
            flex: 1;
        }
        
        .treaty-type {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .treaty-player {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .treaty-time {
            color: #888;
            font-size: 12px;
        }
        
        .treaty-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 20px;
            background: linear-gradient(45deg, #0066cc, #0099ff);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 150, 255, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #ff3333, #cc0000);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #00cc00, #00ff00);
            color: #000;
        }
        
        .propose-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #00ffff;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group textarea {
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #0099ff;
            color: #fff;
            border-radius: 5px;
        }
        
        .message-card {
            background: rgba(255, 255, 0, 0.1);
            border: 1px solid #ffff00;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .message-type {
            color: #ffff00;
            font-weight: bold;
        }
        
        .message-sender {
            color: #fff;
            font-size: 16px;
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
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Diplomatic Treaties</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>📜 Treaties & Diplomacy</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">Active Treaties:</span>
                <span class="stat-value"><?php echo count($relations); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Pending Proposals:</span>
                <span class="stat-value"><?php echo count($messages); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Diplomatic Standing:</span>
                <span class="stat-value"><?php echo $player['honor']; ?> Honor</span>
            </div>
        </div>
        
        <?php if (count($messages) > 0): ?>
        <div class="section">
            <h2 class="section-title">📨 Treaty Proposals</h2>
            
            <?php foreach ($messages as $msg): ?>
            <div class="message-card">
                <div class="message-header">
                    <div>
                        <div class="message-type"><?php echo $messageTypes[$msg['type']] ?? 'Unknown'; ?></div>
                        <div class="message-sender">From: <?php echo htmlspecialchars($msg['sender_name']); ?></div>
                    </div>
                    <div style="color: #888; font-size: 12px;">
                        <?php echo date('Y-m-d H:i', $msg['time']); ?>
                    </div>
                </div>
                
                <div style="margin-top: 10px;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="accept_treaty">
                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                        <button type="submit" class="btn btn-success">Accept</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="decline_treaty">
                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                        <button type="submit" class="btn btn-danger">Decline</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2 class="section-title">🤝 Propose New Treaty</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="propose_treaty">
                
                <div class="propose-form">
                    <div class="form-group">
                        <label>Target Player:</label>
                        <select name="target_player" required>
                            <option value="">Select player...</option>
                            <?php foreach ($otherPlayers as $otherPlayer): ?>
                            <option value="<?php echo $otherPlayer['game_id']; ?>">
                                <?php echo htmlspecialchars($otherPlayer['name']); ?> 
                                (<?php echo number_format($otherPlayer['production']); ?> PP, <?php echo $otherPlayer['honor']; ?> Honor)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Treaty Type:</label>
                        <select name="treaty_type" required>
                            <option value="1">🕊️ Peace Treaty</option>
                            <option value="2">🤝 Trade Agreement</option>
                            <option value="3">🛡️ Military Alliance</option>
                            <option value="4">⚔️ Declaration of War</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Message (optional):</label>
                        <textarea name="message" rows="3" placeholder="Add a diplomatic message..."></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn">Send Proposal</button>
            </form>
        </div>
        
        <div class="section">
            <h2 class="section-title">📋 Active Treaties</h2>
            
            <?php if (count($relations) > 0): ?>
            <div class="treaties-grid">
                <?php foreach ($relations as $relation): 
                    $otherPlayerId = ($relation['player1'] == $playerId) ? $relation['player2'] : $relation['player1'];
                    $relationType = $relationTypes[$relation['relation']] ?? $relationTypes[0];
                ?>
                <div class="treaty-card">
                    <div class="treaty-info">
                        <div class="treaty-type" style="background: <?php echo $relationType['color']; ?>20; color: <?php echo $relationType['color']; ?>">
                            <?php echo $relationType['icon'] . ' ' . $relationType['name']; ?>
                        </div>
                        <div class="treaty-player"><?php echo htmlspecialchars($relation['player_name']); ?></div>
                        <div class="treaty-time">Established: <?php echo date('Y-m-d', $relation['time']); ?></div>
                    </div>
                    
                    <div class="treaty-actions">
                        <form method="POST">
                            <input type="hidden" name="action" value="cancel_treaty">
                            <input type="hidden" name="relation_id" value="<?php echo $relation['id']; ?>">
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('Cancel this treaty?')">Cancel</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-data">
                <p>No active treaties. Propose treaties to other players to establish diplomatic relations.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>