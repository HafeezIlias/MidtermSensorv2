<?php
// ESP32 MySQL Monitor - Get Historical Data API
// api/get_historical.php

// Include required files
require_once '../config.php';
require_once '../Database.php';

// Set content type for JSON responses
header('Content-Type: application/json');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Load configuration
    $config = require '../config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Get parameters
    $hours = isset($_POST['hours']) ? floatval($_POST['hours']) : 1;
    $deviceId = isset($_POST['device_id']) ? trim($_POST['device_id']) : null;
    
    // Validate hours parameter (allow fractional hours for short periods)
    if ($hours < 0.001 || $hours > 168) { // Min ~3.6 seconds, Max 1 week
        http_response_code(400);
        echo json_encode(['error' => 'Hours must be between 0.001 and 168']);
        exit;
    }
    
    // Build query based on whether device_id is provided
    if ($deviceId) {
        $stmt = $pdo->prepare("
            SELECT device_id, temperature, humidity, relay_status, timestamp 
            FROM sensor_data 
            WHERE device_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$deviceId, $hours]);
    } else {
        $stmt = $pdo->prepare("
            SELECT device_id, temperature, humidity, relay_status, timestamp 
            FROM sensor_data 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$hours]);
    }
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert relay_status to boolean for each record
    foreach ($data as &$record) {
        $record['relay_status'] = (bool)$record['relay_status'];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'hours' => $hours,
        'device_id' => $deviceId,
        'count' => count($data)
    ]);
    
} catch (Exception $e) {
    error_log("API Error - get_historical: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>