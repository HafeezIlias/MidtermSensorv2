<?php
// ESP32 MySQL Monitor - Set Relay Status API
// api/set_relay.php

// Include required files
require_once '../config.php';
require_once '../Database.php';

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
    $config = require '../config.php';
    
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
    
    // Validate device_id
    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameter: device_id']);
        exit;
    }
    
    // Validate status
    if (!$status || !in_array(strtoupper($status), ['ON', 'OFF', 'TRUE', 'FALSE', '1', '0'])) {
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
    
    // Check if device exists
    $stmt = $pdo->prepare("SELECT device_id FROM devices WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['error' => 'Device not found']);
        exit;
    }
    
    // Update relay status
    $stmt = $pdo->prepare("UPDATE devices SET relay_status = ?, updated_at = NOW() WHERE device_id = ?");
    $success = $stmt->execute([$relayStatus, $deviceId]);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update relay status']);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'status' => $relayStatus ? 'ON' : 'OFF',
        'relay_status' => $relayStatus,
        'device_id' => $deviceId,
        'message' => "Relay set to " . ($relayStatus ? 'ON' : 'OFF') . " for device {$deviceId}"
    ]);
    
} catch (Exception $e) {
    error_log("API Error - set_relay: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>