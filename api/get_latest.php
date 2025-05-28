<?php
// ESP32 MySQL Monitor - Get Latest Data API
// api/get_latest.php

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
    
    // Get latest data
    $data = $sensorModel->getLatestData();
    
    if ($data === null) {
        http_response_code(404);
        echo json_encode(['error' => 'No data found']);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log("API Error - get_latest: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>