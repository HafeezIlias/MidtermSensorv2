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
    
    // Debug: Log received data
    error_log("Update thresholds - Received data: " . json_encode($data));
    
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
        echo json_encode(['success' => false, 'error' => 'Device not found: ' . $deviceId]);
        exit;
    }
    
    // Build dynamic update query based on provided parameters
    $updateFields = [];
    $updateValues = [];
    
    // Handle temperature thresholds
    if (isset($data['temp_min']) && isset($data['temp_max'])) {
        $tempMin = floatval($data['temp_min']);
        $tempMax = floatval($data['temp_max']);
        
        if ($tempMin >= $tempMax) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Temperature minimum must be less than maximum']);
            exit;
        }
        
        $updateFields[] = 'temp_min = ?';
        $updateFields[] = 'temp_max = ?';
        $updateValues[] = $tempMin;
        $updateValues[] = $tempMax;
    }
    
    // Handle humidity thresholds
    if (isset($data['humidity_min']) && isset($data['humidity_max'])) {
        $humidityMin = floatval($data['humidity_min']);
        $humidityMax = floatval($data['humidity_max']);
        
        if ($humidityMin >= $humidityMax) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Humidity minimum must be less than maximum']);
            exit;
        }
        
        if ($humidityMin < 0 || $humidityMax > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Humidity values must be between 0% and 100%']);
            exit;
        }
        
        $updateFields[] = 'humidity_min = ?';
        $updateFields[] = 'humidity_max = ?';
        $updateValues[] = $humidityMin;
        $updateValues[] = $humidityMax;
    }
    
    // Check if we have any fields to update
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No valid threshold parameters provided']);
        exit;
    }
    
    // Add updated_at field
    $updateFields[] = 'updated_at = NOW()';
    
    // Add device_id for WHERE clause
    $updateValues[] = $deviceId;
    
    // Build and execute update query
    $sql = "UPDATE devices SET " . implode(', ', $updateFields) . " WHERE device_id = ?";
    $stmt = $pdo->prepare($sql);
    
    error_log("Update query: " . $sql);
    error_log("Update values: " . json_encode($updateValues));
    
    $success = $stmt->execute($updateValues);
    
    if ($success && $stmt->rowCount() > 0) {
        // Get updated device data
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        $updatedDevice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thresholds updated successfully',
            'device' => $updatedDevice
        ]);
    } else if ($success) {
        echo json_encode(['success' => true, 'message' => 'No changes made - values already current']);
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