<?php
// Simple health check endpoint for Railway
http_response_code(200);
echo json_encode([
    'status' => 'healthy',
    'service' => 'MagellanWars',
    'timestamp' => time()
]);
?>