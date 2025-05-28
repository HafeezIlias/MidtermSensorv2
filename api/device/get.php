<?php
require_once '../../config.php';
require_once '../../Database.php';

header('Content-Type: application/json');

$deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;
if (!$deviceId) {
    echo json_encode(['success' => false, 'error' => 'Missing device_id']);
    exit;
}

try {
    $config = require '../../config.php';
    $database = new Database($config);
    $pdo = $database->getConnection();
    $stmt = $pdo->prepare('SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max FROM devices WHERE device_id = ? LIMIT 1');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($device) {
        echo json_encode(['success' => true, 'device' => $device]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Device not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
} 