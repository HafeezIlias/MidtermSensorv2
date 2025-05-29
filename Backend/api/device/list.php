<?php
require_once '../../../db/config.php';
require_once '../../../db/Database.php';

header('Content-Type: application/json');

try {
    $config = require '../../../db/config.php';
    $database = new Database($config);
    $pdo = $database->getConnection();
    $stmt = $pdo->query('SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max FROM devices');
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'devices' => $devices]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
} 