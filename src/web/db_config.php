<?php
// Centralized database configuration with Railway support

// Always use Railway public URL when we detect Railway environment or specific hosts
$is_railway = getenv('RAILWAY_ENVIRONMENT') || 
              getenv('MYSQL_PUBLIC_URL') || 
              getenv('MYSQLHOST') == 'mysql.railway.internal' ||
              (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'railway.app') !== false);

if ($is_railway) {
    // Always use hardcoded Railway public proxy values for now
    define('DB_HOST', 'interchange.proxy.rlwy.net');
    define('DB_PORT', '24717');
    define('DB_USER', 'root');
    define('DB_PASS', 'euqGOcmQIGrNpnygHCVthrdXrcNgCCHq');
    define('DB_NAME', 'railway');
} elseif (getenv('DATABASE_URL')) {
    $db_url = parse_url(getenv('DATABASE_URL'));
    define('DB_HOST', $db_url['host']);
    define('DB_PORT', $db_url['port'] ?? 3306);
    define('DB_USER', $db_url['user']);
    define('DB_PASS', $db_url['pass']);
    define('DB_NAME', ltrim($db_url['path'], '/'));
} elseif (getenv('DB_HOST')) {
    // Docker environment
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_PORT', getenv('DB_PORT') ?? 3306);
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASSWORD'));
} else {
    // Local development or non-Docker environment
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'Archspace2');
    define('DB_USER', 'archspace');
    define('DB_PASS', 'archspace123');
}

// Function to get database connection
function getDBConnection() {
    try {
        // Force TCP/IP connection
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        // Log the actual error for debugging
        error_log("Database connection error: " . $e->getMessage());
        error_log("Attempted connection: host=" . DB_HOST . ", port=" . DB_PORT . ", db=" . DB_NAME . ", user=" . DB_USER);
        
        // Return a user-friendly error
        throw new Exception("Database error: " . $e->getMessage());
    }
}
?>