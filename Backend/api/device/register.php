<?php
// Device Registration API
// api/device/register.php

// Prevent any HTML output and ensure JSON response
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header immediately
header('Content-Type: application/json');

// Wrap everything in try-catch to ensure JSON response
try {
    require_once '../../../db/config.php';
    require_once '../../../db/Database.php';

    // Handle both GET and POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Load configuration
    $config = require '../../../db/config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        ob_clean();
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
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Missing device_id parameter']);
        exit;
    }
    
    // Check if device already exists
    $stmt = $pdo->prepare("SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max FROM devices WHERE device_id = ? LIMIT 1");
    $stmt->execute([$deviceId]);
    $existingDevice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingDevice) {
        // Device already exists
        ob_clean();
        echo json_encode([
            'success' => true,
            'registered' => false,
            'message' => 'Device already registered',
            'device' => [
                'device_id' => $existingDevice['device_id'],
                'user' => $existingDevice['user'],
                'display_text' => $existingDevice['display_text'],
                'relay_status' => (bool)$existingDevice['relay_status'],
                'temp_min' => (float)$existingDevice['temp_min'],
                'temp_max' => (float)$existingDevice['temp_max'],
                'humidity_min' => (float)$existingDevice['humidity_min'],
                'humidity_max' => (float)$existingDevice['humidity_max']
            ]
        ]);
    } else {
        // Insert new device with default values
        $stmt = $pdo->prepare("
            INSERT INTO devices 
            (device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $success = $stmt->execute([
            $deviceId,
            $user ?: 'ESP32_User',
            $displayText ?: $deviceId,
            false, // Default relay status as boolean
            20.0,  // Default temp_min
            30.0,  // Default temp_max
            40.0,  // Default humidity_min
            70.0   // Default humidity_max
        ]);
        
        if ($success) {
            // Get the newly registered device
            $stmt = $pdo->prepare("SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max FROM devices WHERE device_id = ? LIMIT 1");
            $stmt->execute([$deviceId]);
            $registeredDevice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Device registered successfully',
                'device' => [
                    'device_id' => $registeredDevice['device_id'],
                    'user' => $registeredDevice['user'],
                    'display_text' => $registeredDevice['display_text'],
                    'relay_status' => (bool)$registeredDevice['relay_status'],
                    'temp_min' => (float)$registeredDevice['temp_min'],
                    'temp_max' => (float)$registeredDevice['temp_max'],
                    'humidity_min' => (float)$registeredDevice['humidity_min'],
                    'humidity_max' => (float)$registeredDevice['humidity_max']
                ]
            ]);
        } else {
            ob_clean();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to register device']);
        }
    }
    
} catch (Exception $e) {
    // Always return JSON even on exceptions
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?> 