<?php
// Railway MySQL Configuration
// Use this file to override db_config.php for Railway deployment

// Use the PUBLIC URL for external connections
define('DB_HOST', 'interchange.proxy.rlwy.net');
define('DB_PORT', '24717');
define('DB_USER', 'root');
define('DB_PASS', 'euqGOcmQIGrNpnygHCVthrdXrcNgCCHq');
define('DB_NAME', 'railway');

// Override the getDBConnection function
function getDBConnection() {
    try {
        // Use TCP/IP connection with Railway's public proxy
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}
?>