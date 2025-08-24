<?php
// Redirect to login or create character
session_start();

if (isset($_SESSION['player_id'])) {
    // Already logged in, go to game
    header('Location: game_main.php');
} else {
    // Not logged in, go to login page
    header('Location: login.html');
}
exit();
?>