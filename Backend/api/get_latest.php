<?php
// ESP32 MySQL Monitor - Get Latest Data API
// api/get_latest.php

// Include required files
require_once '../../db/config.php';
require_once '../../db/Database.php';

// Set content type for JSON responses
header('Content-Type: application/json');

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Load configuration
    $config = require '../../db/config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Get device_id parameter
    $deviceId = null;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $deviceId = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $deviceId = isset($input['device_id']) ? trim($input['device_id']) : null;
        
        // Also check URL parameters for POST requests
        if (!$deviceId && isset($_GET['device_id'])) {
            $deviceId = trim($_GET['device_id']);
        }
    }
    
    // Build query based on whether device_id is provided
    if ($deviceId) {
        $stmt = $pdo->prepare("
            SELECT device_id, temperature, humidity, relay_status, timestamp 
            FROM sensor_data 
            WHERE device_id = ? 
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        $stmt->execute([$deviceId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT device_id, temperature, humidity, relay_status, timestamp 
            FROM sensor_data 
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        $stmt->execute();
    }
    
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => $deviceId ? 'No data found for device' : 'No data found'
        ]);
        exit;
    }
    
    // Convert relay_status to boolean
    $data['relay_status'] = (bool)$data['relay_status'];
    
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