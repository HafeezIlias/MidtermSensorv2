<?php
// Get Device API
// api/device/get.php

require_once '../../../db/config.php';
require_once '../../../db/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
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
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // Get device_id parameter
    $deviceId = null;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $deviceId = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $deviceId = isset($input['device_id']) ? trim($input['device_id']) : null;
    }
    
    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing device_id parameter']);
        exit;
    }  
    // Get device data
    $stmt = $pdo->prepare("
        SELECT device_id, user, display_text, relay_mode, 
               temp_min, temp_max, humidity_min, humidity_max, 
               created_at, updated_at,relay_status 
        FROM devices 
        WHERE device_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Device not found']);
        exit;
    }
    
    // Convert relay_status to boolean and ensure relay_mode has default
    $device['relay_status'] = (bool)$device['relay_status'];
    $device['relay_mode'] = $device['relay_mode'] ?: 'auto';
    
    // Convert numeric values
    $device['temp_min'] = (float)$device['temp_min'];
    $device['temp_max'] = (float)$device['temp_max'];
    $device['humidity_min'] = (float)$device['humidity_min'];
    $device['humidity_max'] = (float)$device['humidity_max'];
    
    echo json_encode([
        'success' => true,
        'device' => $device
    ]);
    
} catch (Exception $e) {
    error_log("API Error - device get: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?> 