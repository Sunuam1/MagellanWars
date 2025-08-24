<?php
// Centralized database configuration with Railway support

// Check if we're on Railway and use the public config
if (getenv('RAILWAY_ENVIRONMENT') || getenv('MYSQL_PUBLIC_URL')) {
    require_once 'railway_config.php';
    return;
}

// Original configuration continues below for local development

// Parse Railway's DATABASE_URL or MYSQL_URL if available
if (getenv('DATABASE_URL')) {
    $db_url = parse_url(getenv('DATABASE_URL'));
    define('DB_HOST', $db_url['host']);
    define('DB_PORT', $db_url['port'] ?? 3306);
    define('DB_USER', $db_url['user']);
    define('DB_PASS', $db_url['pass']);
    define('DB_NAME', ltrim($db_url['path'], '/'));
} elseif (getenv('MYSQL_URL')) {
    $db_url = parse_url(getenv('MYSQL_URL'));
    define('DB_HOST', $db_url['host']);
    define('DB_PORT', $db_url['port'] ?? 3306);
    define('DB_USER', $db_url['user']);
    define('DB_PASS', $db_url['pass']);
    define('DB_NAME', ltrim($db_url['path'], '/'));
} elseif (getenv('MYSQLHOST')) {
    // Railway's individual MySQL variables
    define('DB_HOST', getenv('MYSQLHOST'));
    define('DB_PORT', getenv('MYSQLPORT'));
    define('DB_USER', getenv('MYSQLUSER'));
    define('DB_PASS', getenv('MYSQLPASSWORD'));
    define('DB_NAME', getenv('MYSQLDATABASE'));
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
        // Force TCP/IP connection for Railway - use IP instead of hostname to avoid socket issues
        $host = DB_HOST;
        
        // For Railway, always use TCP/IP connection
        if (strpos($host, 'railway') !== false || getenv('RAILWAY_ENVIRONMENT')) {
            // Railway connection - force TCP
            $dsn = "mysql:host=" . $host . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        } else {
            // Local connection
            $dsn = "mysql:host=" . $host . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        }
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // If connection fails with primary credentials, try alternatives
        $alternatives = [
            ['host' => 'mysql', 'user' => 'archspace', 'pass' => 'archspace123'],
            ['host' => 'localhost', 'user' => 'archspace', 'pass' => 'archspace123'],
            ['host' => 'mysql', 'user' => 'root', 'pass' => 'rootpassword'],
            ['host' => 'localhost', 'user' => 'root', 'pass' => 'rootpassword'],
            ['host' => '127.0.0.1', 'user' => 'archspace', 'pass' => 'archspace123'],
            ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'rootpassword'],
        ];
        
        foreach ($alternatives as $alt) {
            try {
                $pdo = new PDO("mysql:host=" . $alt['host'] . ";dbname=" . DB_NAME, $alt['user'], $alt['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // If successful, update the constants for future use
                if (!getenv('DB_HOST')) {
                    define('DB_HOST_WORKING', $alt['host']);
                    define('DB_USER_WORKING', $alt['user']);
                    define('DB_PASS_WORKING', $alt['pass']);
                }
                return $pdo;
            } catch (PDOException $e2) {
                continue;
            }
        }
        
        // If all attempts fail, throw the original error
        throw $e;
    }
}
?>