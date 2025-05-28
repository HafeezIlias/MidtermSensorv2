<?php
// Update Device Thresholds API
// api/device/update_thresholds.php

require_once '../../config.php';
require_once '../../Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Load configuration
    $config = require '../../config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // Get input data - handle both JSON and form data
    $data = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            // Handle JSON input
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
                exit;
            }
        } else {
            // Handle form data
            $data = $_POST;
        }
    }
    
    // Get device_id (required)
    $deviceId = isset($data['device_id']) ? trim($data['device_id']) : null;
    
    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameter: device_id']);
        exit;
    }
    
    // Check if device exists
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Device not found']);
        exit;
    }
    
    
    // Handle threshold updates
    $tempMin = isset($data['temp_min']) ? floatval($data['temp_min']) : null;
    $tempMax = isset($data['temp_max']) ? floatval($data['temp_max']) : null;
    $humidityMin = isset($data['humidity_min']) ? floatval($data['humidity_min']) : null;
    $humidityMax = isset($data['humidity_max']) ? floatval($data['humidity_max']) : null;
    
    if ($tempMin === null || $tempMax === null || $humidityMin === null || $humidityMax === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: temp_min, temp_max, humidity_min, humidity_max']);
        exit;
    }
    
    // Validate ranges
    if ($tempMin >= $tempMax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Temperature minimum must be less than maximum']);
        exit;
    }
    
    if ($humidityMin >= $humidityMax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Humidity minimum must be less than maximum']);
        exit;
    }
    
    // Update thresholds
    $stmt = $pdo->prepare("
        UPDATE devices 
        SET temp_min = ?, temp_max = ?, humidity_min = ?, humidity_max = ?, updated_at = NOW() 
        WHERE device_id = ?
    ");
    
    $success = $stmt->execute([$tempMin, $tempMax, $humidityMin, $humidityMax, $deviceId]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Thresholds updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update thresholds']);
    }
    
} catch (Exception $e) {
    error_log("API Error - update thresholds: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}
?> 