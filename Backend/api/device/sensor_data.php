<?php
// ESP32 Sensor Data API
// api/device/sensor_data.php

require_once '../../../db/config.php';
require_once '../../../db/Database.php';

header('Content-Type: application/json');

// Only handle GET requests (as per ESP32 code)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Load configuration
    $config = require '../../../db/config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Get and validate parameters
    $deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;
    $temperature = isset($_GET['temperature']) ? floatval($_GET['temperature']) : null;
    $humidity = isset($_GET['humidity']) ? floatval($_GET['humidity']) : null;
    $relayStatus = false;
    if (isset($_GET['relay_status'])) {
        $relayStatusParam = $_GET['relay_status'];
        if ($relayStatusParam === 'true' || $relayStatusParam === '1' || strtoupper($relayStatusParam) === 'ON') {
            $relayStatus = true;
        }
    }
    
    if ($deviceId === null || $temperature === null || $humidity === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    // Insert sensor data directly
    $stmt = $pdo->prepare("
        INSERT INTO sensor_data 
        (device_id, temperature, humidity, timestamp, relay_status) 
        VALUES (?, ?, ?, NOW(), ?)
    ");
    $success = $stmt->execute([$deviceId, $temperature, $humidity, $relayStatus]);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update sensor data']);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Sensor data inserted successfully',
        'device_id' => $deviceId,
        'temperature' => $temperature,
        'humidity' => $humidity,
        'relay_status' => $relayStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("API Error - sensor_data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}