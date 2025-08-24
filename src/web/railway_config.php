<?php
// Railway MySQL Configuration
// Use this file to override db_config.php for Railway deployment

// Use the PUBLIC URL for external connections
define('DB_HOST', 'interchange.proxy.rlwy.net');
define('DB_PORT', '24717');
define('DB_USER', 'root');
define('DB_PASS', 'euqGOcmQIGrNpnygHCVthrdXrcNgCCHq');
define('DB_NAME', 'railway');

// Don't define getDBConnection here - it will be defined in db_config.php
?>