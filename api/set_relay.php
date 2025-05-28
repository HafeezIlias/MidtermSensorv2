<?php
// ESP32 MySQL Monitor - Set Relay Status API
// api/set_relay.php

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
    
    // Get status parameter
    $status = $_POST['status'] ?? '';
    
    // Validate status
    if (!in_array($status, ['ON', 'OFF'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status. Must be ON or OFF']);
        exit;
    }
    
    // Update relay status
    $success = $sensorModel->updateRelayStatus($status);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update relay status']);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'status' => $status,
        'message' => "Relay set to {$status}"
    ]);
    
} catch (Exception $e) {
    error_log("API Error - set_relay: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>