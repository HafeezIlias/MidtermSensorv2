<?php
// ESP32 Sensor Data API
// api/device/sensor_data.php

require_once '../../config.php';
require_once '../../Database.php';
require_once '../../SensorModel.php';

header('Content-Type: application/json');

// Only handle GET requests (as per ESP32 code)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Load configuration
    $config = require '../../config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Initialize sensor model
    $sensorModel = new SensorModel($pdo,);
    
    // Get and validate parameters
    $deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;
    $temperature = isset($_GET['temperature']) ? floatval($_GET['temperature']) : null;
    $humidity = isset($_GET['humidity']) ? floatval($_GET['humidity']) : null;
    $relayStatus = isset($_GET['relay_status']) ? ($_GET['relay_status'] === 'true' ? 'ON' : 'OFF') : 'OFF';
    
    if ($deviceId === null || $temperature === null || $humidity === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    // Insert sensor data
    $success = $sensorModel->insertSensorData($deviceId, $temperature, $humidity, $relayStatus);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update sensor data']);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Sensor data updated successfully',
        'data' => [
            'device_id' => $deviceId,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'relay_status' => $relayStatus
        ]
    ]);
    
} catch (Exception $e) {
    error_log("API Error - sensor_data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} 