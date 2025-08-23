<?php
// Test database connection
echo "<h2>Database Connection Test</h2>";
echo "<pre>";

// Try environment variables first
$dbHost = getenv('DB_HOST') ?: 'mysql';
$dbName = getenv('DB_NAME') ?: 'Archspace2';
$dbUser = getenv('DB_USER') ?: 'archspace';
$dbPass = getenv('DB_PASSWORD') ?: 'archspace123';

echo "Attempting connection with:\n";
echo "Host: $dbHost\n";
echo "Database: $dbName\n";
echo "User: $dbUser\n";
echo "Password: [hidden]\n\n";

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<span style='color: green;'>✓ Connection successful!</span>\n\n";
    
    // Test query - count players
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM player");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Players in database: " . $result['count'] . "\n";
    
    // Show tables
    echo "\nAvailable tables:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
} catch (PDOException $e) {
    echo "<span style='color: red;'>✗ Connection failed!</span>\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    // Try alternative credentials
    echo "Trying alternative credentials (root)...\n";
    try {
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", 'root', 'rootpassword');
        echo "<span style='color: green;'>✓ Root connection successful!</span>\n";
        echo "Use root/rootpassword for database access\n";
    } catch (PDOException $e2) {
        echo "<span style='color: red;'>✗ Root connection also failed</span>\n";
        echo "Error: " . $e2->getMessage() . "\n";
    }
}

echo "</pre>";

// Show PHP info about MySQL
echo "<h3>PHP MySQL Extension Info</h3>";
echo "<pre>";
if (extension_loaded('pdo_mysql')) {
    echo "✓ PDO MySQL extension is loaded\n";
} else {
    echo "✗ PDO MySQL extension is NOT loaded\n";
}

if (extension_loaded('mysqli')) {
    echo "✓ MySQLi extension is loaded\n";
} else {
    echo "✗ MySQLi extension is NOT loaded\n";
}
echo "</pre>";
?>