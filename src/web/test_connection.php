<?php
// Test MySQL Connection for Railway
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Railway MySQL Connection Test</h2>";
echo "<pre>";

// Show all environment variables related to MySQL
echo "=== Environment Variables ===\n";
$mysql_vars = [
    'MYSQLHOST', 'MYSQLPORT', 'MYSQLUSER', 'MYSQLPASSWORD', 'MYSQLDATABASE',
    'DATABASE_URL', 'MYSQL_URL', 'RAILWAY_ENVIRONMENT',
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'
];

foreach ($mysql_vars as $var) {
    $value = getenv($var);
    if ($value) {
        if (strpos($var, 'PASS') !== false || strpos($var, 'PASSWORD') !== false) {
            echo "$var: ***hidden***\n";
        } else {
            echo "$var: $value\n";
        }
    }
}

echo "\n=== Testing Connection ===\n";

// Try different connection methods
$methods = [];

// Method 1: Using individual Railway variables
if (getenv('MYSQLHOST')) {
    $methods[] = [
        'name' => 'Railway MySQL Variables',
        'host' => getenv('MYSQLHOST'),
        'port' => getenv('MYSQLPORT'),
        'user' => getenv('MYSQLUSER'),
        'pass' => getenv('MYSQLPASSWORD'),
        'db' => getenv('MYSQLDATABASE')
    ];
}

// Method 2: Using DATABASE_URL
if (getenv('DATABASE_URL')) {
    $url = parse_url(getenv('DATABASE_URL'));
    $methods[] = [
        'name' => 'DATABASE_URL',
        'host' => $url['host'],
        'port' => $url['port'] ?? 3306,
        'user' => $url['user'],
        'pass' => $url['pass'],
        'db' => ltrim($url['path'], '/')
    ];
}

// Method 3: Using MYSQL_URL
if (getenv('MYSQL_URL')) {
    $url = parse_url(getenv('MYSQL_URL'));
    $methods[] = [
        'name' => 'MYSQL_URL',
        'host' => $url['host'],
        'port' => $url['port'] ?? 3306,
        'user' => $url['user'],
        'pass' => $url['pass'],
        'db' => ltrim($url['path'], '/')
    ];
}

// Test each method
foreach ($methods as $method) {
    echo "\nTesting: " . $method['name'] . "\n";
    echo "Host: " . $method['host'] . "\n";
    echo "Port: " . $method['port'] . "\n";
    echo "Database: " . $method['db'] . "\n";
    echo "User: " . $method['user'] . "\n";
    
    try {
        // Try with explicit TCP connection
        $dsn = "mysql:host=" . $method['host'] . ";port=" . $method['port'] . ";dbname=" . $method['db'] . ";charset=utf8mb4";
        echo "DSN: $dsn\n";
        
        $pdo = new PDO($dsn, $method['user'], $method['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        echo "✓ CONNECTION SUCCESSFUL!\n";
        
        // Test query
        $result = $pdo->query("SELECT 1 as test")->fetch();
        echo "✓ Query test successful: " . $result['test'] . "\n";
        
        // Show tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "✓ Tables found: " . count($tables) . "\n";
        if (count($tables) > 0) {
            echo "Tables: " . implode(", ", array_slice($tables, 0, 5)) . "...\n";
        }
        
        echo "\n*** Use this connection method! ***\n";
        break;
        
    } catch (PDOException $e) {
        echo "✗ Connection failed: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Instructions ===\n";
echo "1. In Railway, go to your MagellanWars service\n";
echo "2. Click the 'Variables' tab\n";
echo "3. Click 'Add Variable Reference'\n";
echo "4. Select your MySQL service\n";
echo "5. Add these variables:\n";
echo "   - MYSQLHOST\n";
echo "   - MYSQLPORT\n";
echo "   - MYSQLUSER\n";
echo "   - MYSQLPASSWORD\n";
echo "   - MYSQLDATABASE\n";
echo "6. Railway will automatically populate the values\n";
echo "7. Your app will restart and connect!\n";

echo "</pre>";
?>