<?php
require_once 'db_config.php';

// Get form data
$characterName = isset($_POST['ID']) ? trim($_POST['ID']) : '';
$race = isset($_POST['RACE']) ? intval($_POST['RACE']) : 1;
$password = 'default123'; // Default password for now, in production would come from form

// Validate input
if (empty($characterName)) {
    die('Error: Character name is required');
}

if (strlen($characterName) < 3 || strlen($characterName) > 30) {
    die('Error: Character name must be between 3 and 30 characters');
}

// Validate race (1-10 for the 10 races in the game)
if ($race < 1 || $race > 10) {
    $race = 1; // Default to human
}

try {
    // Connect to database
    $pdo = getDBConnection();
    
    // Check if character name already exists
    $checkStmt = $pdo->prepare("SELECT game_id FROM player WHERE name = :name");
    $checkStmt->execute(['name' => $characterName]);
    
    if ($checkStmt->rowCount() > 0) {
        die('Error: Character name already exists. Please choose a different name.');
    }
    
    // Get next game_id
    $maxIdStmt = $pdo->query("SELECT MAX(game_id) as max_id FROM player");
    $maxId = $maxIdStmt->fetch(PDO::FETCH_ASSOC)['max_id'];
    $newGameId = ($maxId ? $maxId : 1000) + 1;
    
    // Create new player
    $insertStmt = $pdo->prepare("
        INSERT INTO player (
            game_id, 
            portal_id,
            name, 
            race,
            mode,
            honor,
            production,
            ship_production,
            research,
            last_login,
            tick,
            turn,
            home_cluster_id
        ) VALUES (
            :game_id,
            :portal_id,
            :name,
            :race,
            0,
            50,
            1000,
            100,
            100,
            UNIX_TIMESTAMP(),
            0,
            0,
            1
        )
    ");
    
    $insertStmt->execute([
        'game_id' => $newGameId,
        'portal_id' => $newGameId, // Using same as game_id for now
        'name' => $characterName,
        'race' => $race
    ]);
    
    // Redirect to login page with success message
    header('Location: login.html?registered=true');
    exit();
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>