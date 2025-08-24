<?php
// Simple entry point for the game
// This replaces the .phtml reference in index.html

// Include configuration
if (file_exists('../config.php')) {
    include '../config.php';
} elseif (file_exists('config.php')) {
    include 'config.php';
}

// Database connection
$db_host = isset($db_host) ? $db_host : 'localhost';
$db_name = isset($db_name) ? $db_name : 'Archspace2';
$db_user = isset($db_user) ? $db_user : 'archspace';
$db_pass = isset($db_pass) ? $db_pass : 'archspace123';

// Try to connect to database
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

?>
<!DOCTYPE html>
<html>
<head>
    <title>MagellanWars - Welcome</title>
    <style>
        body {
            background-color: #000;
            color: #999;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        h1 {
            color: #fff;
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        .status {
            background-color: #111;
            border: 1px solid #333;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success {
            color: #0f0;
        }
        .error {
            color: #f00;
        }
        .info {
            color: #999;
            margin: 10px 0;
        }
        a {
            color: #09f;
            text-decoration: none;
        }
        a:hover {
            color: #0cf;
        }
        .game-links {
            margin-top: 30px;
        }
        .game-links a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #222;
            border: 1px solid #444;
            margin: 10px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        .game-links a:hover {
            background-color: #333;
            border-color: #666;
        }
    </style>
    <script src="turn_timer.js"></script>
</head>
<body>
    <div class="container">
        <h1>MagellanWars</h1>
        
        <div class="status">
            <h2>System Status</h2>
            
            <?php if ($mysqli && !$mysqli->connect_error): ?>
                <p class="success">✓ Database Connection: Active</p>
                <?php
                // Check if tables exist
                $result = $mysqli->query("SHOW TABLES");
                if ($result) {
                    $table_count = $result->num_rows;
                    echo "<p class='info'>Database has $table_count tables</p>";
                }
                ?>
            <?php else: ?>
                <p class="error">✗ Database Connection: Failed</p>
                <?php if ($mysqli): ?>
                    <p class="error">Error: <?php echo $mysqli->connect_error; ?></p>
                <?php endif; ?>
            <?php endif; ?>
            
            <p class="info">Game Server: 
                <?php
                // Check if game server is running
                $game_server = isset($game_server) ? $game_server : 'localhost';
                $game_port = isset($game_port) ? $game_port : 5000;
                $connection = @fsockopen($game_server, $game_port, $errno, $errstr, 1);
                if ($connection) {
                    echo '<span class="success">Online</span>';
                    fclose($connection);
                } else {
                    echo '<span class="error">Offline</span>';
                }
                ?>
            </p>
        </div>
        
        <div class="game-links">
            <h2>Game Access</h2>
            <?php if (file_exists('login.html')): ?>
                <a href="login.html">Login to Game</a>
            <?php endif; ?>
            <?php if (file_exists('create_character.html')): ?>
                <a href="create_character.html">Create Character</a>
            <?php endif; ?>
            <a href="index.html">Classic Interface</a>
        </div>
        
        <div class="info" style="margin-top: 40px;">
            <p>MagellanWars is a space strategy game where you build your empire across the galaxy.</p>
            <p>Command fleets, research technologies, and conquer planets!</p>
        </div>
    </div>
</body>
</html>
<?php
if ($mysqli) {
    $mysqli->close();
}
?>