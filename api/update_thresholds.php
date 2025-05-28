<?php
require_once '../../config.php';
require_once '../../Database.php';
require_once '../../SensorModel.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$deviceId = isset($data['device_id']) ? $data['device_id'] : null;
$tempMin = isset($data['temp_min']) ? $data['temp_min'] : null;
$tempMax = isset($data['temp_max']) ? $data['temp_max'] : null;
$humidityMin = isset($data['humidity_min']) ? $data['humidity_min'] : null;
$humidityMax = isset($data['humidity_max']) ? $data['humidity_max'] : null;

if (!$deviceId || $tempMin === null || $tempMax === null || $humidityMin === null || $humidityMax === null) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    $config = require '../../config.php';
    $database = new Database($config);
    $pdo = $database->getConnection();
    $sensorModel = new SensorModel($pdo,);
    $success = $sensorModel->updateDeviceThresholds($deviceId, $tempMin, $tempMax, $humidityMin, $humidityMax);
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
} 