<?php
// Centralized database configuration with Railway support

// Parse Railway's DATABASE_URL or MYSQL_URL if available
if (getenv('DATABASE_URL')) {
    $db_url = parse_url(getenv('DATABASE_URL'));
    define('DB_HOST', $db_url['host'] . (isset($db_url['port']) ? ':' . $db_url['port'] : ''));
    define('DB_USER', $db_url['user']);
    define('DB_PASS', $db_url['pass']);
    define('DB_NAME', ltrim($db_url['path'], '/'));
} elseif (getenv('MYSQL_URL')) {
    $db_url = parse_url(getenv('MYSQL_URL'));
    define('DB_HOST', $db_url['host'] . (isset($db_url['port']) ? ':' . $db_url['port'] : ''));
    define('DB_USER', $db_url['user']);
    define('DB_PASS', $db_url['pass']);
    define('DB_NAME', ltrim($db_url['path'], '/'));
} elseif (getenv('MYSQLHOST')) {
    // Railway's individual MySQL variables
    define('DB_HOST', getenv('MYSQLHOST') . ':' . getenv('MYSQLPORT'));
    define('DB_USER', getenv('MYSQLUSER'));
    define('DB_PASS', getenv('MYSQLPASSWORD'));
    define('DB_NAME', getenv('MYSQLDATABASE'));
} elseif (getenv('DB_HOST')) {
    // Docker environment
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASSWORD'));
} else {
    // Local development or non-Docker environment
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'Archspace2');
    define('DB_USER', 'archspace');
    define('DB_PASS', 'archspace123');
}

// Function to get database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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