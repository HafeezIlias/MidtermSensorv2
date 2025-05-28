<?php
// ESP32 MySQL Monitor - Toggle Relay API
// api/toggle_relay.php

// Include required files
require_once '../config.php';
require_once '../Database.php';
require_once '../SensorModel.php';

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
    
    // Initialize sensor model
    $sensorModel = new SensorModel($pdo,);
    
    // Get current status parameter
    $currentStatus = $_POST['current_status'] ?? '';
    
    // Validate current status
    if (!in_array($currentStatus, ['ON', 'OFF'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid current_status. Must be ON or OFF']);
        exit;
    }
    
    // Determine new status
    $newStatus = ($currentStatus === 'ON') ? 'OFF' : 'ON';
    
    // Update relay status
    $success = $sensorModel->updateRelayStatus($newStatus);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update relay status']);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'previous_status' => $currentStatus,
        'new_status' => $newStatus,
        'message' => "Relay turned {$newStatus}"
    ]);
    
} catch (Exception $e) {
    error_log("API Error - toggle_relay: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>