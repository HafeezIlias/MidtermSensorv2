<?php
// ESP32 Config API
// api/device/config.php

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
    
    // Get device ID
    $deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;
    
    if ($deviceId === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Device ID is required']);
        exit;
    }
    
    // Get latest data and thresholds
    $latestData = $sensorModel->getLatestData();
    
    // Return config response
    echo json_encode([
        'success' => true,
        'data' => [
            'device_id' => $deviceId,
            'current' => [
                'temperature' => $latestData ? $latestData['temperature'] : null,
                'humidity' => $latestData ? $latestData['humidity'] : null,
                'relay_status' => $latestData ? $latestData['relay_status'] : 'OFF'
            ],
            'thresholds' => [
                'temperature' => [
                    'max' => $thresholds['temperature'],
                    'min' => 20.0 // Default minimum temperature
                ],
                'humidity' => [
                    'max' => $thresholds['humidity'],
                    'min' => 40.0 // Default minimum humidity
                ]
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("API Error - config: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} 