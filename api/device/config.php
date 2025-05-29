<?php
// ESP32 Config API
// api/device/config.php

require_once '../../config.php';
require_once '../../Database.php';

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
    
    // Get device ID
    $deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;
    
    if ($deviceId === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Device ID is required']);
        exit;
    }
    
    // Get device configuration from database
    $stmt = $pdo->prepare("SELECT device_id, relay_status, relay_mode, temp_min, temp_max, humidity_min, humidity_max FROM devices WHERE device_id = ? LIMIT 1");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['error' => 'Device not found']);
        exit;
    }
    
    // Return config response with boolean relay_status
    echo json_encode([
        'success' => true,
        'device_id' => $deviceId,
        'temp_min' => (float)$device['temp_min'],
        'temp_max' => (float)$device['temp_max'],
        'humidity_min' => (float)$device['humidity_min'],
        'humidity_max' => (float)$device['humidity_max'],
        'relay_status' => (bool)$device['relay_status'],
        'relay_mode' => $device['relay_mode'] ?? 'auto'
    ]);
    
} catch (Exception $e) {
    error_log("API Error - config: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} 