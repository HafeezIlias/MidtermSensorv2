<?php
// Device Registration API
// api/device/register.php

require_once '../../config.php';
require_once '../../Database.php';

header('Content-Type: application/json');

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    // Get parameters (support both GET and POST)
    $deviceId = null;
    $user = null;
    $displayText = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $deviceId = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;
        $user = isset($_GET['user']) ? trim($_GET['user']) : null;
        $displayText = isset($_GET['display_text']) ? trim($_GET['display_text']) : null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $deviceId = isset($input['device_id']) ? trim($input['device_id']) : null;
        $user = isset($input['user']) ? trim($input['user']) : null;
        $displayText = isset($input['display_text']) ? trim($input['display_text']) : null;
    }
    
    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing device_id parameter']);
        exit;
    }
    
    // Check if device already exists
    $stmt = $pdo->prepare("SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max FROM devices WHERE device_id = ? LIMIT 1");
    $stmt->execute([$deviceId]);
    $existingDevice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingDevice) {
        // Device already registered
        echo json_encode([
            'success' => true,
            'registered' => true,
            'message' => 'Device already registered',
            'device' => $existingDevice
        ]);
    } else {
        // Register new device with default values
        $defaultUser = $user ?: 'Unknown User';
        $defaultDisplayText = $displayText ?: $deviceId;
        $defaultRelayStatus = false; // OFF
        $defaultTempMin = 20.0;
        $defaultTempMax = 30.0;
        $defaultHumidityMin = 40.0;
        $defaultHumidityMax = 70.0;
        
        $stmt = $pdo->prepare("
            INSERT INTO devices 
            (device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $success = $stmt->execute([
            $deviceId, 
            $defaultUser, 
            $defaultDisplayText, 
            $defaultRelayStatus, 
            $defaultTempMin, 
            $defaultTempMax, 
            $defaultHumidityMin, 
            $defaultHumidityMax
        ]);
        
        if ($success) {
            // Get the newly registered device
            $stmt = $pdo->prepare("SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max FROM devices WHERE device_id = ? LIMIT 1");
            $stmt->execute([$deviceId]);
            $newDevice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'registered' => false,
                'message' => 'Device registered successfully',
                'device' => $newDevice
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to register device']);
        }
    }
    
} catch (Exception $e) {
    error_log("API Error - device register: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?> 