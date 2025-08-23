<?php
session_start();
require_once 'db_config.php';

// Get form data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate input
if (empty($username) || empty($password)) {
    header('Location: login.html?error=missing_fields');
    exit();
}

try {
    // Connect to database
    $pdo = getDBConnection();
    
    // Find player by name
    $stmt = $pdo->prepare("SELECT game_id, portal_id, name, race FROM player WHERE name = :name");
    $stmt->execute(['name' => $username]);
    
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        // Player not found
        header('Location: login.html?error=invalid_credentials');
        exit();
    }
    
    // For now, accept any password since we didn't set up password field in database
    // In production, you would verify password hash here
    // For demo, accept 'default123' or the character name as password
    if ($password !== 'default123' && $password !== $username) {
        header('Location: login.html?error=invalid_credentials');
        exit();
    }
    
    // Update last login time
    $updateStmt = $pdo->prepare("UPDATE player SET last_login = UNIX_TIMESTAMP(), last_login_ip = :ip WHERE game_id = :id");
    $updateStmt->execute([
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'id' => $player['game_id']
    ]);
    
    // Set session variables
    $_SESSION['player_id'] = $player['game_id'];
    $_SESSION['player_name'] = $player['name'];
    $_SESSION['player_race'] = $player['race'];
    
    // Redirect to game main page
    header('Location: game_main.php');
    exit();
    
} catch (PDOException $e) {
    // Database error
    header('Location: login.html?error=database_error');
    exit();
}
?>