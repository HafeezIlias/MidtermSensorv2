<?php
// ESP32 MySQL Monitor - System Status API
// api/status.php

// Include required files
require_once 'db/config.php';
require_once 'db/Database.php';
require_once '../../SensorModel.php';

// Set content type for JSON responses
header('Content-Type: application/json');

// Handle both GET and POST requests for status check
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Load configuration
    $config = require 'db/config.php';
    
    // Initialize database connection
    $database = new Database($config);
    $pdo = $database->getConnection();
    
    $dbConnected = $pdo !== null;
    $lastDataTime = null;
    $totalRecords = 0;
    
    if ($dbConnected) {
        // Initialize sensor model
        $sensorModel = new SensorModel($pdo,);
        
        // Get latest data to check last update time
        $latestData = $sensorModel->getLatestData();
        if ($latestData) {
            $lastDataTime = $latestData['timestamp'];
        }
        
        // Get total record count
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$config['table_name']}");
            $stmt->execute();
            $result = $stmt->fetch();
            $totalRecords = $result['total'];
        } catch (PDOException $e) {
            error_log("Error getting record count: " . $e->getMessage());
        }
    }
    
    // Calculate system uptime (time since last data)
    $dataAge = null;
    if ($lastDataTime) {
        $lastUpdate = new DateTime($lastDataTime);
        $now = new DateTime();
        $interval = $now->diff($lastUpdate);
        $dataAge = [
            'seconds' => $interval->s,
            'minutes' => $interval->i,
            'hours' => $interval->h,
            'days' => $interval->d,
            'total_seconds' => $now->getTimestamp() - $lastUpdate->getTimestamp()
        ];
    }
    
    // Return system status
    echo json_encode([
        'success' => true,
        'system_status' => [
            'database_connected' => $dbConnected,
            'last_data_time' => $lastDataTime,
            'data_age' => $dataAge,
            'total_records' => $totalRecords,
            'server_time' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'formatted' => [
                    'current' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                    'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
                ]
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("API Error - status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>