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
    
    // Get player's council if they're in one
    $myCouncil = null;
    if ($player['council_id'] > 0) {
        $councilStmt = $pdo->prepare("SELECT * FROM council WHERE id = :id");
        $councilStmt->execute(['id' => $player['council_id']]);
        $myCouncil = $councilStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get all councils
    $councilsStmt = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM player WHERE council_id = c.id) as member_count,
               sp.name as speaker_name
        FROM council c
        LEFT JOIN player sp ON c.speaker = sp.game_id
        ORDER BY c.production DESC
    ");
    $councils = $councilsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get council members if player is in a council
    $councilMembers = [];
    if ($myCouncil) {
        $membersStmt = $pdo->prepare("
            SELECT game_id, name, production, honor, last_login 
            FROM player 
            WHERE council_id = :council_id
            ORDER BY production DESC
        ");
        $membersStmt->execute(['council_id' => $myCouncil['id']]);
        $councilMembers = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get admission requests
    $admissionRequests = [];
    if ($myCouncil) {
        $admissionStmt = $pdo->prepare("
            SELECT a.*, p.name as player_name, p.production, p.honor
            FROM admission a
            JOIN player p ON a.player = p.game_id
            WHERE a.council = :council_id AND a.status = 0
            ORDER BY a.time DESC
        ");
        $admissionStmt->execute(['council_id' => $myCouncil['id']]);
        $admissionRequests = $admissionStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle council actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_council' && $player['council_id'] == 0) {
        $councilName = trim($_POST['council_name'] ?? '');
        $councilSlogan = trim($_POST['council_slogan'] ?? '');
        
        if ($councilName) {
            // Create new council
            $councilId = time() + rand(1000, 9999);
            
            $createCouncilStmt = $pdo->prepare("
                INSERT INTO council (id, speaker, name, slogan, production, honor, auto_assign, home_cluster_id)
                VALUES (:id, :speaker, :name, :slogan, 0, 50, 1, :cluster)
            ");
            $createCouncilStmt->execute([
                'id' => $councilId,
                'speaker' => $playerId,
                'name' => $councilName,
                'slogan' => $councilSlogan,
                'cluster' => $player['home_cluster_id'] ?? 1
            ]);
            
            // Update player's council
            $pdo->prepare("UPDATE player SET council_id = :council WHERE game_id = :id")
                ->execute(['council' => $councilId, 'id' => $playerId]);
            
            $successMsg = "Council '$councilName' created successfully! You are now the Speaker.";
            
            // Reload data
            header("Location: council.php?created=1");
            exit();
        }
    }
    
    if ($action == 'join_council' && $player['council_id'] == 0) {
        $councilId = intval($_POST['council_id'] ?? 0);
        
        if ($councilId > 0) {
            // Create admission request
            $admissionId = time() + rand(1000, 9999);
            $message = trim($_POST['message'] ?? 'Request to join your council');
            
            // Check if admission request already exists
            $checkAdmissionStmt = $pdo->prepare("
                SELECT * FROM admission WHERE player = :player AND council = :council
            ");
            $checkAdmissionStmt->execute(['player' => $playerId, 'council' => $councilId]);
            
            if ($checkAdmissionStmt->fetch()) {
                $errorMsg = "You already have a pending admission request for this council!";
            } else {
                $createAdmissionStmt = $pdo->prepare("
                    INSERT INTO admission (player, council, status, time, content)
                    VALUES (:player, :council, 0, :time, :content)
                ");
                $createAdmissionStmt->execute([
                    'player' => $playerId,
                    'council' => $councilId,
                    'time' => time(),
                    'content' => $message
                ]);
                
                // Get council speaker to send notification
                $speakerStmt = $pdo->prepare("SELECT speaker FROM council WHERE id = :id");
                $speakerStmt->execute(['id' => $councilId]);
                $speakerId = $speakerStmt->fetchColumn();
                
                if ($speakerId) {
                    // Send notification to council speaker
                    $msgId = time() + rand(100000, 999999);
                    $notifyStmt = $pdo->prepare("
                        INSERT INTO council_message (id, type, sender, receiver, time, status)
                        VALUES (:id, 1, :sender, :receiver, :time, 0)
                    ");
                    $notifyStmt->execute([
                        'id' => $msgId,
                        'sender' => $playerId,
                        'receiver' => $speakerId,
                        'time' => time()
                    ]);
                }
                
                $successMsg = "Admission request sent! The council speaker has been notified.";
            }
        }
    }
    
    if ($action == 'leave_council' && $player['council_id'] > 0) {
        // Leave council
        $pdo->prepare("UPDATE player SET council_id = 0 WHERE game_id = :id")
            ->execute(['id' => $playerId]);
        
        // If was speaker, assign new speaker
        if ($myCouncil && $myCouncil['speaker'] == $playerId) {
            $newSpeakerStmt = $pdo->prepare("
                SELECT game_id FROM player 
                WHERE council_id = :council AND game_id != :player
                ORDER BY production DESC
                LIMIT 1
            ");
            $newSpeakerStmt->execute(['council' => $myCouncil['id'], 'player' => $playerId]);
            $newSpeaker = $newSpeakerStmt->fetchColumn();
            
            if ($newSpeaker) {
                $pdo->prepare("UPDATE council SET speaker = :speaker WHERE id = :id")
                    ->execute(['speaker' => $newSpeaker, 'id' => $myCouncil['id']]);
            } else {
                // Delete empty council
                $pdo->prepare("DELETE FROM council WHERE id = :id")
                    ->execute(['id' => $myCouncil['id']]);
            }
        }
        
        header("Location: council.php?left=1");
        exit();
    }
    
    if ($action == 'accept_member' && $myCouncil && $myCouncil['speaker'] == $playerId) {
        $admissionPlayerId = intval($_POST['player_id'] ?? 0);
        
        // Accept member
        $pdo->prepare("UPDATE player SET council_id = :council WHERE game_id = :id")
            ->execute(['council' => $myCouncil['id'], 'id' => $admissionPlayerId]);
        
        // Delete admission request
        $pdo->prepare("DELETE FROM admission WHERE player = :player AND council = :council")
            ->execute(['player' => $admissionPlayerId, 'council' => $myCouncil['id']]);
        
        $successMsg = "New member accepted!";
        header("Location: council.php?accepted=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Council - MagellanWars</title>
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
            background: linear-gradient(45deg, #ff00ff, #ff66ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .council-status {
            background: rgba(0, 0, 0, 0.7);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid #ff00ff;
        }
        
        .status-title {
            font-size: 24px;
            color: #ff00ff;
            margin-bottom: 15px;
        }
        
        .council-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: rgba(255, 0, 255, 0.1);
            padding: 10px;
            border-radius: 5px;
        }
        
        .info-label {
            color: #ff99ff;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .info-value {
            color: #fff;
            font-size: 18px;
            margin-top: 5px;
        }
        
        .councils-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .council-card {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff00ff;
            border-radius: 10px;
            padding: 15px;
        }
        
        .council-name {
            font-size: 20px;
            color: #ff00ff;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .council-slogan {
            color: #ff99ff;
            font-style: italic;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .council-stats {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 10px;
        }
        
        .btn {
            padding: 8px 20px;
            background: linear-gradient(45deg, #ff00ff, #ff66ff);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 0, 255, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #ff3333, #cc0000);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #00cc00, #00ff00);
            color: #000;
        }
        
        .members-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff00ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .members-table {
            width: 100%;
            margin-top: 15px;
        }
        
        .members-table th {
            background: rgba(255, 0, 255, 0.2);
            padding: 10px;
            text-align: left;
            color: #ff00ff;
        }
        
        .members-table td {
            padding: 10px;
            border-bottom: 1px solid #333;
        }
        
        .create-council-form {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff00ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ff00ff;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            max-width: 500px;
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #ff00ff;
            color: #fff;
            border-radius: 5px;
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
        
        .speaker-badge {
            display: inline-block;
            padding: 2px 8px;
            background: linear-gradient(45deg, #ffaa00, #ffcc00);
            color: #000;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Council Chamber</div>
        <a href="../game_main.php" style="color: #0099ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>🏛️ Galactic Council</h1>
        
        <?php if (isset($successMsg)): ?>
            <div class="message success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['created'])): ?>
            <div class="message success">Council created successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['left'])): ?>
            <div class="message success">You have left the council.</div>
        <?php endif; ?>
        
        <?php if ($myCouncil): ?>
        <div class="council-status">
            <h2 class="status-title">Your Council: <?php echo htmlspecialchars($myCouncil['name']); ?></h2>
            <?php if ($myCouncil['slogan']): ?>
            <p style="color: #ff99ff; font-style: italic; margin-bottom: 15px;">
                "<?php echo htmlspecialchars($myCouncil['slogan']); ?>"
            </p>
            <?php endif; ?>
            
            <div class="council-info">
                <div class="info-item">
                    <div class="info-label">Total Production</div>
                    <div class="info-value"><?php echo number_format($myCouncil['production']); ?> PP</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Honor</div>
                    <div class="info-value"><?php echo $myCouncil['honor']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Members</div>
                    <div class="info-value"><?php echo count($councilMembers); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Your Role</div>
                    <div class="info-value">
                        <?php echo ($myCouncil['speaker'] == $playerId) ? 'Speaker' : 'Member'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="members-section">
            <h3 style="color: #ff00ff; margin-bottom: 15px;">Council Members</h3>
            <table class="members-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Production</th>
                        <th>Honor</th>
                        <th>Last Active</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($councilMembers as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                        <td><?php echo number_format($member['production']); ?></td>
                        <td><?php echo $member['honor']; ?></td>
                        <td><?php echo date('Y-m-d H:i', $member['last_login']); ?></td>
                        <td>
                            <?php if ($member['game_id'] == $myCouncil['speaker']): ?>
                                <span class="speaker-badge">Speaker</span>
                            <?php else: ?>
                                Member
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="leave_council">
                    <button type="submit" class="btn btn-danger" 
                            onclick="return confirm('Are you sure you want to leave this council?')">
                        Leave Council
                    </button>
                </form>
            </div>
        </div>
        
        <?php if ($myCouncil['speaker'] == $playerId && count($admissionRequests) > 0): ?>
        <div class="members-section">
            <h3 style="color: #ff00ff; margin-bottom: 15px;">Admission Requests</h3>
            <?php foreach ($admissionRequests as $request): ?>
            <div style="background: rgba(255, 0, 255, 0.1); padding: 15px; margin-bottom: 10px; border-radius: 5px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?php echo htmlspecialchars($request['player_name']); ?></strong><br>
                        <span style="color: #aaa; font-size: 14px;">
                            Production: <?php echo number_format($request['production']); ?> | 
                            Honor: <?php echo $request['honor']; ?>
                        </span><br>
                        <span style="color: #ff99ff; font-size: 14px;">
                            "<?php echo htmlspecialchars($request['message']); ?>"
                        </span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="accept_member">
                        <input type="hidden" name="player_id" value="<?php echo $request['player_id']; ?>">
                        <button type="submit" class="btn btn-success">Accept</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="create-council-form">
            <h2 style="color: #ff00ff; margin-bottom: 20px;">Create New Council</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_council">
                
                <div class="form-group">
                    <label>Council Name:</label>
                    <input type="text" name="council_name" required placeholder="Enter council name...">
                </div>
                
                <div class="form-group">
                    <label>Council Slogan (optional):</label>
                    <textarea name="council_slogan" rows="2" placeholder="Enter your council's motto..."></textarea>
                </div>
                
                <button type="submit" class="btn">Create Council</button>
            </form>
        </div>
        <?php endif; ?>
        
        <h2 style="color: #ff00ff; margin-bottom: 20px;">All Councils</h2>
        <div class="councils-grid">
            <?php foreach ($councils as $council): ?>
            <div class="council-card">
                <div class="council-name">
                    <?php echo htmlspecialchars($council['name']); ?>
                    <?php if ($council['id'] == ($myCouncil['id'] ?? 0)): ?>
                        <span style="color: #00ff00; font-size: 14px;">(Your Council)</span>
                    <?php endif; ?>
                </div>
                <?php if ($council['slogan']): ?>
                <div class="council-slogan">"<?php echo htmlspecialchars($council['slogan']); ?>"</div>
                <?php endif; ?>
                <div class="council-stats">
                    Speaker: <?php echo htmlspecialchars($council['speaker_name'] ?? 'None'); ?><br>
                    Members: <?php echo $council['member_count']; ?><br>
                    Production: <?php echo number_format($council['production']); ?> PP<br>
                    Honor: <?php echo $council['honor']; ?>
                </div>
                
                <?php if ($player['council_id'] == 0 && $council['id'] != ($myCouncil['id'] ?? 0)): ?>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="join_council">
                    <input type="hidden" name="council_id" value="<?php echo $council['id']; ?>">
                    <input type="text" name="message" placeholder="Message (optional)" 
                           style="width: 100%; margin-bottom: 5px; padding: 5px; background: rgba(0,0,0,0.5); border: 1px solid #ff00ff; color: #fff; border-radius: 5px;">
                    <button type="submit" class="btn">Request to Join</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($councils) == 0): ?>
        <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.5); border-radius: 10px;">
            <p style="color: #888;">No councils exist yet. Be the first to create one!</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>