<?php
// ESP32 MySQL Monitor - Main Application
// index.php

// Include required files
require_once 'config.php';
require_once 'Database.php';
require_once 'SensorModel.php';

// Load configuration
$config = require 'config.php';

// Initialize database connection
$database = new Database($config);
$pdo = $database->getConnection();

// Initialize variables
$latestData = null;
$connectionStatus = 'Disconnected';

// Get initial data if database is connected
if ($pdo) {
    $sensorModel = new SensorModel($pdo, );
    $latestData = $sensorModel->getLatestData();
    $connectionStatus = 'Connected to MySQL';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 MySQL Monitor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .connected { background-color: #d4edda; color: #155724; }
        .disconnected { background-color: #f8d7da; color: #721c24; }
        .data-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ESP32 MySQL Monitor</h1>
        
        <div class="status <?php echo $pdo ? 'connected' : 'disconnected'; ?>">
            Status: <?php echo $connectionStatus; ?>
        </div>
        
        <?php if ($latestData): ?>
        <div class="data-card">
            <h3>Latest Sensor Data</h3>
            <p><strong>Temperature:</strong> <?php echo htmlspecialchars($latestData['temperature'] ?? 'N/A'); ?>°C</p>
            <p><strong>Humidity:</strong> <?php echo htmlspecialchars($latestData['humidity'] ?? 'N/A'); ?>%</p>
            <p><strong>Relay Status:</strong> <?php echo htmlspecialchars($latestData['relay_status'] ?? 'N/A'); ?></p>
            <p><strong>Last Update:</strong> <?php echo htmlspecialchars($latestData['timestamp'] ?? 'N/A'); ?></p>
        </div>
        
        <button onclick="toggleRelay()">Toggle Relay</button>
        <button onclick="refreshData()">Refresh Data</button>
        <button onclick="getHistoricalData(24)">Get 24h History</button>
        <button onclick="checkSystemStatus()">System Status</button>
        <?php else: ?>
        <div class="data-card">
            <p>No sensor data available.</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function refreshData() {
            fetch('api/get_latest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Simple refresh for now
                } else {
                    alert('Error: ' + (data.error || 'Failed to refresh data'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        }

        function toggleRelay() {
            const currentStatus = '<?php echo $latestData['relay_status'] ?? 'OFF'; ?>';
            
            fetch('api/toggle_relay.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `current_status=${currentStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to toggle relay'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        }

        function getHistoricalData(hours = 24) {
            fetch('api/get_historical.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `hours=${hours}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Historical data for ${data.hours} hours:`, data.data);
                    alert(`Retrieved ${data.count} records for the last ${data.hours} hours`);
                } else {
                    alert('Error: ' + (data.error || 'Failed to get historical data'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        }

        function checkSystemStatus() {
            fetch('api/status.php', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const status = data.system_status;
                    let message = `System Status:\n`;
                    message += `Database: ${status.database_connected ? 'Connected' : 'Disconnected'}\n`;
                    message += `Total Records: ${status.total_records}\n`;
                    message += `Server Time: ${status.server_time}\n`;
                    message += `Memory Usage: ${status.memory_usage.formatted.current}`;
                    
                    if (status.last_data_time) {
                        message += `\nLast Data: ${status.last_data_time}`;
                    }
                    
                    alert(message);
                } else {
                    alert('Error: ' + (data.error || 'Failed to get system status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        }
    </script>
</body>
</html>