<?php
require_once 'db_config.php';

echo "<h2>Initializing Game Data</h2><pre>";

try {
    $pdo = getDBConnection();
    
    // Check if clusters exist
    $clusterCount = $pdo->query("SELECT COUNT(*) FROM cluster")->fetchColumn();
    
    if ($clusterCount == 0) {
        echo "Creating initial clusters...\n";
        
        // Create some initial clusters
        $clusters = [
            [1, 'Sol System', 1],
            [2, 'Alpha Centauri', 2],
            [3, 'Sirius', 3],
            [4, 'Vega', 4],
            [5, 'Arcturus', 5],
            [6, 'Capella', 6],
            [7, 'Rigel', 7],
            [8, 'Procyon', 8],
            [9, 'Betelgeuse', 9],
            [10, 'Altair', 10]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO cluster (id, name, name_number) VALUES (?, ?, ?)");
        foreach ($clusters as $cluster) {
            $stmt->execute($cluster);
        }
        
        echo "Created " . count($clusters) . " clusters\n";
    } else {
        echo "Clusters already exist: $clusterCount found\n";
    }
    
    // Initialize race data if needed
    echo "\nChecking race data...\n";
    $raceCount = $pdo->query("SELECT COUNT(*) FROM race")->fetchColumn();
    
    if ($raceCount == 0) {
        echo "Creating race data...\n";
        
        $races = [
            [1, 'Human', 'The versatile and adaptable Human race'],
            [2, 'Targro', 'Warriors born for battle'],
            [3, 'Bukka', 'Masters of trade and commerce'],
            [4, 'Xeloss', 'Technology specialists'],
            [5, 'Agerus', 'Ancient race with vast wisdom'],
            [6, 'Bosalian', 'Diplomatic and peaceful'],
            [7, 'Xeldorade', 'Explorers of the unknown'],
            [8, 'Kreen', 'Hive-minded insectoids'],
            [9, 'Madness', 'Chaotic and unpredictable'],
            [10, 'Magellan', 'The legendary founder race']
        ];
        
        // First check if race table exists
        $tables = $pdo->query("SHOW TABLES LIKE 'race'")->fetchAll();
        if (empty($tables)) {
            echo "Creating race table...\n";
            $pdo->exec("
                CREATE TABLE race (
                    id INT PRIMARY KEY,
                    name VARCHAR(50),
                    description TEXT
                )
            ");
        }
        
        $stmt = $pdo->prepare("INSERT INTO race (id, name, description) VALUES (?, ?, ?)");
        foreach ($races as $race) {
            try {
                $stmt->execute($race);
            } catch (PDOException $e) {
                // Ignore duplicate entries
            }
        }
        echo "Race data initialized\n";
    } else {
        echo "Race data already exists: $raceCount races found\n";
    }
    
    // Check empire tables
    echo "\nChecking empire tables...\n";
    $tables = ['empire', 'magistrate', 'fortress'];
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetchAll();
        if (empty($result)) {
            echo "Warning: Table '$table' does not exist\n";
        } else {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "Table '$table' exists with $count records\n";
        }
    }
    
    // Initialize empire if needed
    $empireCount = $pdo->query("SELECT COUNT(*) FROM empire")->fetchColumn();
    if ($empireCount == 0) {
        echo "\nInitializing Empire data...\n";
        
        // Create the Galactic Empire
        $pdo->exec("
            INSERT INTO empire (id, name, description, relation_default)
            VALUES (1, 'Galactic Empire', 'The ruling empire of the galaxy', 50)
        ");
        
        echo "Created Galactic Empire\n";
    }
    
    echo "\n<span style='color: green;'>✓ Game data initialization complete!</span>\n";
    
} catch (PDOException $e) {
    echo "<span style='color: red;'>Error: " . $e->getMessage() . "</span>\n";
}

echo "</pre>";

echo "<h3>Database Status</h3><pre>";

// Show all tables
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "  - $table ($count records)\n";
    }
} catch (PDOException $e) {
    echo "Error listing tables: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<p><a href='game_main.php'>Go to Game</a></p>";
?>