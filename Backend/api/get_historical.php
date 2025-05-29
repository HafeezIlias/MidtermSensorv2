<?php
// ESP32 MySQL Monitor - Get Historical Data API
// api/get_historical.php

// Include required files
require_once '../../db/config.php';
require_once '../../db/Database.php';

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
    $config = require '../../db/config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Get parameters
    $hours = isset($_POST['hours']) ? floatval($_POST['hours']) : 1;
    $deviceId = isset($_POST['device_id']) ? trim($_POST['device_id']) : null;
    
    // Debug logging
    error_log("Historical data request - Hours: $hours, Device: $deviceId");
    
    // Validate hours parameter (allow fractional hours for short periods)
    if ($hours < 0.001 || $hours > 168) { // Min ~3.6 seconds, Max 1 week
        http_response_code(400);
        echo json_encode(['error' => 'Hours must be between 0.001 and 168']);
        exit;
    }
    
    // For very short periods (less than 1 hour), use different time units for better precision
    if ($hours < 1) {
        if ($hours < 0.1) {
            // For periods less than 6 minutes, use seconds
            $seconds = round($hours * 3600);
            $timeClause = "timestamp >= DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $timeValue = $seconds;
            error_log("Using SECOND interval: $seconds seconds");
        } else {
            // For periods less than 1 hour, use minutes
            $minutes = round($hours * 60);
            $timeClause = "timestamp >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";
            $timeValue = $minutes;
            error_log("Using MINUTE interval: $minutes minutes");
        }
    } else {
        // For 1 hour and above, use hours
        $timeClause = "timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
        $timeValue = $hours;
        error_log("Using HOUR interval: $hours hours");
    }
    
    // Build query based on whether device_id is provided
    if ($deviceId) {
        $sql = "
            SELECT device_id, temperature, humidity, relay_status, timestamp 
            FROM sensor_data 
            WHERE device_id = ? AND $timeClause
            ORDER BY timestamp ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$deviceId, $timeValue]);
        error_log("Query with device filter: $sql");
        error_log("Parameters: [$deviceId, $timeValue]");
    } else {
        $sql = "
            SELECT device_id, temperature, humidity, relay_status, timestamp 
            FROM sensor_data 
            WHERE $timeClause
            ORDER BY timestamp ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$timeValue]);
        error_log("Query without device filter: $sql");
        error_log("Parameters: [$timeValue]");
    }
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log query results
    error_log("Query returned " . count($data) . " records");
    if (count($data) > 0) {
        error_log("First record timestamp: " . $data[0]['timestamp']);
        error_log("Last record timestamp: " . $data[count($data)-1]['timestamp']);
    }
    
    // Convert relay_status to boolean for each record
    foreach ($data as &$record) {
        $record['relay_status'] = (bool)$record['relay_status'];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'hours' => $hours,
        'device_id' => $deviceId,
        'count' => count($data),
        'time_unit' => $hours < 0.1 ? 'seconds' : ($hours < 1 ? 'minutes' : 'hours'),
        'time_value' => $timeValue
    ]);
    
} catch (Exception $e) {
    error_log("API Error - get_historical: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>