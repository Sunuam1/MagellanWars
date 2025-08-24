<?php
// Database Setup Script for Railway
// This creates all necessary tables for MagellanWars

require_once 'db_config.php';

$tables_created = 0;
$errors = [];

try {
    $pdo = getDBConnection();
    echo "<h2>MagellanWars Database Setup</h2>";
    echo "<pre>";
    
    // Array of table creation queries
    $tables = [
        'cluster' => "CREATE TABLE IF NOT EXISTS cluster (
            id smallint(6) DEFAULT '0' NOT NULL,
            name char(50) NOT NULL,
            name_number int(8) DEFAULT '0' NOT NULL,
            PRIMARY KEY (id)
        )",
        
        'player_pref' => "CREATE TABLE IF NOT EXISTS player_pref (
            player_id int(11) NOT NULL DEFAULT '0',
            java_choice int(11) NOT NULL DEFAULT '0',
            accept_ally int(11) NOT NULL DEFAULT '-1',
            accept_truce int(11) NOT NULL DEFAULT '-1',
            accept_pact int(11) NOT NULL DEFAULT '-1',
            commander_view int(11) UNSIGNED NOT NULL DEFAULT '15',
            PRIMARY KEY (player_id)
        )",
        
        'player' => "CREATE TABLE IF NOT EXISTS player (
            game_id int(10) NOT NULL,
            portal_id int(10) NOT NULL,
            name char(30) NOT NULL,
            home_cluster_id int(10) DEFAULT '0' NOT NULL,
            last_login int(10) DEFAULT '0' NOT NULL,
            last_login_ip varchar(15) DEFAULT '000.000.000.000' NOT NULL,
            mode tinyint(1) DEFAULT '0' NOT NULL,
            race tinyint(2) DEFAULT '0' NOT NULL,
            honor int(3) DEFAULT '50' NOT NULL,
            research_invest int(10) DEFAULT '0' NOT NULL,
            tick int(10) DEFAULT '0' NOT NULL,
            turn int(10) DEFAULT '0' NOT NULL,
            production int(10) DEFAULT '0' NOT NULL,
            ship_production int(10) DEFAULT '0' NOT NULL,
            invested_ship_production int(10) DEFAULT '0' NOT NULL,
            research int(10) DEFAULT '0' NOT NULL,
            ability char(65) DEFAULT '',
            research_tech int(10) DEFAULT '0' NOT NULL,
            admiral_timer int(10) DEFAULT '0' NOT NULL,
            last_turn_production int(10) DEFAULT '0' NOT NULL,
            last_turn_research int(10) DEFAULT '0' NOT NULL,
            last_turn_military int(10) DEFAULT '0' NOT NULL,
            council_id smallint(6) DEFAULT '0' NOT NULL,
            council_vote smallint(6) DEFAULT '0' NOT NULL,
            council_production int(10) DEFAULT '0' NOT NULL,
            council_donation int(10) DEFAULT '0' NOT NULL,
            security_level tinyint(5) DEFAULT '1' NOT NULL,
            alertness int(11) DEFAULT '0' NOT NULL,
            empire_relation int(6) DEFAULT '50' NOT NULL,
            protected_mode tinyint(4) DEFAULT '0' NOT NULL,
            protected_terminate_time int(10) DEFAULT '0' NOT NULL,
            news_turn int(10) DEFAULT '0' NOT NULL,
            news_production int(10) DEFAULT '0' NOT NULL,
            news_research int(10) DEFAULT '0' NOT NULL,
            news_population int(10) DEFAULT '0' NOT NULL,
            news_ability char(65) DEFAULT '',
            news_tech text,
            news_planet text,
            news_project text,
            news_admiral text,
            news_time_news longtext,
            planet_invest_pool int(10) DEFAULT '0' NOT NULL,
            admission_time_limit int(11) DEFAULT '-1' NOT NULL,
            honor_timer int(10) DEFAULT '0' NOT NULL,
            rating int(10) DEFAULT '2000' NOT NULL,
            PRIMARY KEY (game_id),
            KEY idx0 (name),
            UNIQUE idx1 (portal_id)
        )",
        
        'planet' => "CREATE TABLE IF NOT EXISTS planet (
            id int(8) NOT NULL,
            cluster int(8) NOT NULL,
            owner int(8) NOT NULL,
            order_ int(8) NOT NULL,
            name char(50) DEFAULT '' NOT NULL,
            attribute char(8) DEFAULT '',
            population int(10) DEFAULT '0' NOT NULL,
            building_factory smallint(5) DEFAULT '0' NOT NULL,
            building_military_base smallint(5) DEFAULT '0' NOT NULL,
            building_research_lab smallint(5) DEFAULT '0' NOT NULL,
            progress_factory smallint(5) DEFAULT '0' NOT NULL,
            progress_military_base smallint(5) DEFAULT '0' NOT NULL,
            progress_research_lab smallint(5) DEFAULT '0' NOT NULL,
            ratio_factory smallint(5) DEFAULT '40' NOT NULL,
            ratio_military_base smallint(5) DEFAULT '30' NOT NULL,
            ratio_research_lab smallint(5) DEFAULT '30' NOT NULL,
            atmosphere char(8) DEFAULT '' NOT NULL,
            temperature smallint(3) DEFAULT '300' NOT NULL,
            size tinyint(1) DEFAULT '2' NOT NULL,
            resource tinyint(1) DEFAULT '2' NOT NULL,
            gravity double DEFAULT '1.0' NOT NULL,
            investment int(10) DEFAULT '0' NOT NULL,
            terraforming smallint(1) DEFAULT '0' NOT NULL,
            terraforming_timer int(10) DEFAULT '0' NOT NULL,
            commerce_with_1 int(10) DEFAULT '0' NOT NULL,
            commerce_with_2 int(10) DEFAULT '0' NOT NULL,
            commerce_with_3 int(10) DEFAULT '0' NOT NULL,
            privateer_timer int(10) DEFAULT '0' NOT NULL,
            blockade_timer int(10) DEFAULT '0' NOT NULL,
            news_population int(10) DEFAULT '0' NOT NULL,
            news_factory smallint(5) DEFAULT '0' NOT NULL,
            news_military_base smallint(5) DEFAULT '0' NOT NULL,
            news_research_lab smallint(5) DEFAULT '0' NOT NULL,
            turns_till_destruction int(11) DEFAULT '0' NOT NULL,
            planet_invest_pool tinyint(1) DEFAULT '0' NOT NULL,
            PRIMARY KEY (id),
            KEY idx0 (owner)
        )",
        
        'tech' => "CREATE TABLE IF NOT EXISTS tech (
            owner smallint(6) DEFAULT '0' NOT NULL,
            info char(10) NOT NULL,
            life char(10) NOT NULL,
            matter char(10) NOT NULL,
            social char(10) NOT NULL,
            upgrade char(10) NOT NULL,
            schematics char(10) NOT NULL,
            amatter char(10) NOT NULL,
            PRIMARY KEY (owner)
        )",
        
        'project' => "CREATE TABLE IF NOT EXISTS project (
            owner smallint(6) DEFAULT '0' NOT NULL,
            project_id smallint(5) DEFAULT '0' NOT NULL,
            type smallint(6) DEFAULT '0' NOT NULL,
            PRIMARY KEY (owner, project_id)
        )",
        
        'admiral' => "CREATE TABLE IF NOT EXISTS admiral (
            id bigint(10) DEFAULT '0' NOT NULL,
            owner int(10) DEFAULT '-1' NOT NULL,
            race smallint(3) DEFAULT '0' NOT NULL,
            type smallint(3) DEFAULT '0' NOT NULL,
            name char(40) NOT NULL,
            exp int(10) DEFAULT '0' NOT NULL,
            level smallint(3) DEFAULT '0' NOT NULL,
            fleet_number int(10) DEFAULT '0' NOT NULL,
            armada_commanding tinyint NOT NULL,
            fleet_commanding smallint(3) DEFAULT '-10' NOT NULL,
            efficiency smallint(3) DEFAULT '-10' NOT NULL,
            offense smallint(3) DEFAULT '-10' NOT NULL,
            offense_up_level smallint(3) DEFAULT '-10' NOT NULL,
            defense smallint(3) DEFAULT '-10' NOT NULL,
            defense_up_level smallint(3) DEFAULT '-10' NOT NULL,
            maneuver smallint(3) DEFAULT '-10' NOT NULL,
            maneuver_up_level smallint(3) DEFAULT '-10' NOT NULL,
            detection smallint(3) DEFAULT '-10' NOT NULL,
            detection_up_level smallint(3) DEFAULT '-10' NOT NULL,
            commonability smallint(2) DEFAULT '-10' NOT NULL,
            raceability smallint(2) DEFAULT '-10' NOT NULL,
            PRIMARY KEY (id)
        )",
        
        'council' => "CREATE TABLE IF NOT EXISTS council (
            id int(10) DEFAULT '0' NOT NULL,
            speaker int(10) DEFAULT '0' NOT NULL,
            name char(40) DEFAULT '' NOT NULL,
            slogan char(255) DEFAULT '' NOT NULL,
            production int(10) DEFAULT '0' NOT NULL,
            honor int(3) DEFAULT '50' NOT NULL,
            auto_assign smallint(1) DEFAULT '1' NOT NULL,
            home_cluster_id int(10) DEFAULT '0' NOT NULL,
            merge_penalty_time int(11) DEFAULT '-1' NOT NULL,
            secondary_speaker int(10) DEFAULT '0' NOT NULL,
            PRIMARY KEY (id)
        )",
        
        'diplomatic_message' => "CREATE TABLE IF NOT EXISTS diplomatic_message (
            id INT UNSIGNED DEFAULT '0' NOT NULL,
            type SMALLINT DEFAULT '0' NOT NULL,
            sender INT UNSIGNED DEFAULT '0' NOT NULL,
            receiver INT UNSIGNED DEFAULT '0' NOT NULL,
            time INT UNSIGNED DEFAULT '0' NOT NULL,
            status SMALLINT DEFAULT '0' NOT NULL,
            PRIMARY KEY(id),
            KEY idx0 (receiver)
        )",
        
        'council_message' => "CREATE TABLE IF NOT EXISTS council_message (
            id INT UNSIGNED DEFAULT '0' NOT NULL,
            type SMALLINT DEFAULT '0' NOT NULL,
            sender INT UNSIGNED DEFAULT '0' NOT NULL,
            receiver INT UNSIGNED DEFAULT '0' NOT NULL,
            time INT UNSIGNED DEFAULT '0' NOT NULL,
            status SMALLINT DEFAULT '0' NOT NULL,
            PRIMARY KEY(id),
            KEY idx0 (receiver)
        )",
        
        'fleet' => "CREATE TABLE IF NOT EXISTS fleet (
            owner int NOT NULL,
            id int(10) NOT NULL,
            name char(40) NOT NULL,
            admiral bigint(10) NOT NULL,
            exp int NOT NULL,
            status int NOT NULL,
            maxship int NOT NULL,
            currentship int NOT NULL,
            shipclass int NOT NULL,
            mission int NOT NULL,
            mission_target int NOT NULL,
            mission_terminate_time int NOT NULL,
            killed_ship int NOT NULL,
            killed_fleet int NOT NULL,
            PRIMARY KEY(owner, id)
        )",
        
        'class' => "CREATE TABLE IF NOT EXISTS class (
            owner int NOT NULL,
            design_id int NOT NULL,
            name char(40) NOT NULL,
            body int NOT NULL,
            armor int NOT NULL,
            engine int NOT NULL,
            computer int NOT NULL,
            shield int NOT NULL,
            weapon1 int NOT NULL,
            weapon2 int NOT NULL,
            weapon3 int NOT NULL,
            weapon4 int NOT NULL,
            weapon5 int NOT NULL,
            weapon6 int NOT NULL,
            weapon7 int NOT NULL,
            weapon8 int NOT NULL,
            weapon9 int NOT NULL,
            weapon10 int NOT NULL,
            weapon_number1 int NOT NULL,
            weapon_number2 int NOT NULL,
            weapon_number3 int NOT NULL,
            weapon_number4 int NOT NULL,
            weapon_number5 int NOT NULL,
            weapon_number6 int NOT NULL,
            weapon_number7 int NOT NULL,
            weapon_number8 int NOT NULL,
            weapon_number9 int NOT NULL,
            weapon_number10 int NOT NULL,
            device1 int NOT NULL,
            device2 int NOT NULL,
            device3 int NOT NULL,
            device4 int NOT NULL,
            device5 int NOT NULL,
            device6 int NOT NULL,
            device7 int NOT NULL,
            device8 int NOT NULL,
            time int NOT NULL,
            cost int NOT NULL,
            black_market_design int(3) NOT NULL DEFAULT 0,
            empire_design int(3) NOT NULL DEFAULT 0,
            PRIMARY KEY(owner, design_id)
        )",
        
        'game_status' => "CREATE TABLE IF NOT EXISTS game_status (
            last_game_time int(12) unsigned DEFAULT '0' NOT NULL
        )",
        
        'empire' => "CREATE TABLE IF NOT EXISTS empire (
            current_outer_planets int DEFAULT '0' NOT NULL,
            current_inner_planets int DEFAULT '0' NOT NULL
        )",
        
        'battle_record' => "CREATE TABLE IF NOT EXISTS battle_record (
            id int NOT NULL,
            attacker_id int NOT NULL,
            defender_id int NOT NULL,
            attacker_name char(30) NOT NULL,
            defender_name char(30) NOT NULL,
            attacker_race int NOT NULL,
            defender_race int NOT NULL,
            attacker_council int NOT NULL,
            defender_council int NOT NULL,
            time int NOT NULL,
            war_type int NOT NULL,
            is_draw char(3) NOT NULL DEFAULT 'NO',
            winner int NOT NULL,
            planet_id int NOT NULL,
            battle_field_name char(50) NOT NULL,
            attacker_gain text,
            attacker_lose_fleet text,
            attacker_lose_admiral text,
            defender_lose_fleet text,
            defender_lose_admiral text,
            record_file char(50) NOT NULL,
            there_was_battle tinyint NOT NULL,
            PRIMARY KEY(id)
        )",
        
        'blackmarket' => "CREATE TABLE IF NOT EXISTS blackmarket (
            id int NOT NULL,
            type int NOT NULL,
            item int NOT NULL,
            winner int NOT NULL,
            price int NOT NULL,
            opend int NOT NULL,
            expire int NOT NULL,
            closed int NOT NULL,
            number_of_planet int NOT NULL,
            PRIMARY KEY(id)
        )",
        
        'plan' => "CREATE TABLE IF NOT EXISTS plan (
            owner int NOT NULL,
            id int NOT NULL,
            type tinyint NOT NULL,
            name char(40) NOT NULL,
            capital int NOT NULL,
            enemy int NOT NULL,
            min int NOT NULL,
            max int NOT NULL,
            attack_type int NOT NULL,
            PRIMARY KEY(owner, id)
        )",
        
        'defense_fleet' => "CREATE TABLE IF NOT EXISTS defense_fleet (
            owner int NOT NULL,
            plan_id int NOT NULL,
            fleet_id int NOT NULL,
            command int NOT NULL,
            x int NOT NULL,
            y int NOT NULL,
            PRIMARY KEY(owner, plan_id, fleet_id)
        )"
    ];
    
    // Create each table
    foreach ($tables as $table_name => $query) {
        try {
            $pdo->exec($query);
            echo "✓ Created table: $table_name\n";
            $tables_created++;
        } catch (PDOException $e) {
            echo "✗ Error creating $table_name: " . $e->getMessage() . "\n";
            $errors[] = $table_name;
        }
    }
    
    // Insert initial data
    echo "\n--- Inserting Initial Data ---\n";
    
    try {
        $pdo->exec("INSERT INTO game_status (last_game_time) VALUES (" . time() . ") ON DUPLICATE KEY UPDATE last_game_time=" . time());
        echo "✓ Initialized game_status\n";
    } catch (PDOException $e) {
        echo "✗ Error initializing game_status: " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("INSERT INTO empire (current_outer_planets, current_inner_planets) VALUES (100, 50) ON DUPLICATE KEY UPDATE current_outer_planets=100");
        echo "✓ Initialized empire\n";
    } catch (PDOException $e) {
        echo "✗ Error initializing empire: " . $e->getMessage() . "\n";
    }
    
    // Add some initial clusters
    try {
        $clusters = [
            [1, 'Alpha Centauri', 1],
            [2, 'Sol System', 2],
            [3, 'Vega', 3],
            [4, 'Proxima', 4],
            [5, 'Andromeda', 5]
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO cluster (id, name, name_number) VALUES (?, ?, ?)");
        foreach ($clusters as $cluster) {
            $stmt->execute($cluster);
        }
        echo "✓ Added initial clusters\n";
    } catch (PDOException $e) {
        echo "✗ Error adding clusters: " . $e->getMessage() . "\n";
    }
    
    echo "\n========================================\n";
    echo "Setup Complete!\n";
    echo "Tables created: $tables_created / " . count($tables) . "\n";
    
    if (count($errors) > 0) {
        echo "\nTables with errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    echo "\n✓ Database is ready for MagellanWars!\n";
    echo "</pre>";
    
    // Show connection info
    echo "<h3>Connection Details:</h3>";
    echo "<pre>";
    echo "Host: " . DB_HOST . "\n";
    echo "Port: " . DB_PORT . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    echo "</pre>";
    
    echo '<p><a href="index.php">Go to Game</a></p>';
    
} catch (PDOException $e) {
    echo "<h2>Database Connection Error</h2>";
    echo "<pre>";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Make sure you have connected the MySQL database variables in Railway!\n";
    echo "</pre>";
}
?>