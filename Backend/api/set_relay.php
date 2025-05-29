<?php
// ESP32 MySQL Monitor - Set Relay Status API
// api/set_relay.php

// Include required files
require_once '../../db/config.php';
require_once '../../db/Database.php';

// Set content type for JSON responses
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
                echo json_encode(['error' => 'Invalid JSON data']);
                exit;
            }
        } else {
            // Handle form data
            $data = $_POST;
        }
    } else {
        // Handle GET parameters
        $data = $_GET;
    }
    
    // Get parameters
    $deviceId = isset($data['device_id']) ? trim($data['device_id']) : null;
    $status = isset($data['status']) ? trim($data['status']) : null;
    $mode = isset($data['mode']) ? trim($data['mode']) : null;
    
    // Validate device_id
    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameter: device_id']);
        exit;
    }
    
    // Check if device exists
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['error' => 'Device not found']);
        exit;
    }
    
    // Handle mode change
    if ($mode && in_array($mode, ['auto', 'manual'])) {
        // Check if relay_mode column exists, if not add it
        try {
            $stmt = $pdo->prepare("SELECT relay_mode FROM devices WHERE device_id = ? LIMIT 1");
            $stmt->execute([$deviceId]);
        } catch (PDOException $e) {
            // Column doesn't exist, add it
            $pdo->exec("ALTER TABLE devices ADD COLUMN relay_mode VARCHAR(10) DEFAULT 'auto'");
        }
        
        $stmt = $pdo->prepare("UPDATE devices SET relay_mode = ?, updated_at = NOW() WHERE device_id = ?");
        $success = $stmt->execute([$mode, $deviceId]);
        
        if ($success) {
            // Get updated device info to return current relay status
            $stmt = $pdo->prepare("SELECT relay_status, relay_mode FROM devices WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            $updatedDevice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'mode' => $mode,
                'relay_status' => (bool)$updatedDevice['relay_status'],
                'status' => $updatedDevice['relay_status'] ? 'ON' : 'OFF',
                'device_id' => $deviceId,
                'message' => "Relay mode set to {$mode} for device {$deviceId}"
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update relay mode']);
        }
        exit;
    }
    
    // Handle relay status change
    if ($status !== null && $status !== '') {
        // Validate status
        if (!in_array(strtoupper($status), ['ON', 'OFF', 'TRUE', 'FALSE', '1', '0'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status. Must be ON/OFF, TRUE/FALSE, or 1/0']);
            exit;
        }
        
        // Convert status to boolean
        $relayStatus = false;
        $statusUpper = strtoupper($status);
        if ($statusUpper === 'ON' || $statusUpper === 'TRUE' || $status === '1') {
            $relayStatus = true;
        }
        
        // Re-fetch device to get current mode
        $stmt = $pdo->prepare("SELECT relay_mode FROM devices WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        $currentDevice = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentMode = $currentDevice['relay_mode'] ?? 'auto';
        
        if ($currentMode === 'auto') {
            http_response_code(400);
            echo json_encode([
                'error' => 'Cannot manually control relay in auto mode. Switch to manual mode first.',
                'current_mode' => $currentMode
            ]);
            exit;
        }
        
        // Update relay status (only in manual mode) - use boolean for database
        $stmt = $pdo->prepare("UPDATE devices SET relay_status = ?, updated_at = NOW() WHERE device_id = ?");
        $success = $stmt->execute([$relayStatus, $deviceId]);
        
        if (!$success) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update relay status in database']);
            exit;
        }
        
        // Verify the update worked
        $stmt = $pdo->prepare("SELECT relay_status FROM devices WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        $verifyResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($verifyResult === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to verify relay status update']);
            exit;
        }
        
        $actualStatus = (bool)$verifyResult['relay_status'];
        
        // Return success response
        echo json_encode([
            'success' => true,
            'status' => $actualStatus ? 'ON' : 'OFF',
            'relay_status' => $actualStatus,
            'mode' => $currentMode,
            'device_id' => $deviceId,
            'database_value' => $verifyResult['relay_status'],
            'message' => "Relay set to " . ($actualStatus ? 'ON' : 'OFF') . " for device {$deviceId} (Manual mode)"
        ]);
        exit;
    }
    
    // If no valid action specified
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: status or mode']);
    
} catch (Exception $e) {
    error_log("API Error - set_relay: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>