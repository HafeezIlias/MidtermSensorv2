<?php
// ESP32 MySQL Monitor - Get Historical Data API
// api/get_historical.php

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
    
    // Get hours parameter (default to 1 hour)
    $hours = isset($_POST['hours']) ? intval($_POST['hours']) : 1;
    
    // Validate hours parameter
    if ($hours < 1 || $hours > 168) { // Max 1 week
        http_response_code(400);
        echo json_encode(['error' => 'Hours must be between 1 and 168']);
        exit;
    }
    
    // Get historical data
    $data = $sensorModel->getHistoricalData($hours);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'hours' => $hours,
        'count' => count($data)
    ]);
    
} catch (Exception $e) {
    error_log("API Error - get_historical: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>