<?php
// ESP32 MySQL Monitor - Sensor Data Model
// SensorModel.php

class SensorModel {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get latest sensor data
     */
    public function getLatestData($deviceId = null) {
        try {
            if ($deviceId) {
                $stmt = $this->pdo->prepare("SELECT * FROM sensor_data WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1");
                $stmt->execute([$deviceId]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1");
                $stmt->execute();
            }
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching latest data: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get historical data for specified hours
     */
    public function getHistoricalData($hours = 1, $deviceId = null) {
        try {
            if ($deviceId) {
                $stmt = $this->pdo->prepare("
                    SELECT temperature, humidity, relay_status, timestamp 
                    FROM sensor_data
                    WHERE device_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR) 
                    ORDER BY timestamp ASC
                ");
                $stmt->execute([$deviceId, $hours]);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT temperature, humidity, relay_status, timestamp 
                    FROM sensor_data 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR) 
                    ORDER BY timestamp ASC
                ");
                $stmt->execute([$hours]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching historical data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update relay status
     */
    public function updateRelayStatus($status, $deviceId = null) {
        try {
            if ($deviceId) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO sensor_data (device_id, relay_status, timestamp) 
                    VALUES (?, ?, NOW())
                ");
                return $stmt->execute([$deviceId, $status]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO sensor_data (relay_status, timestamp) 
                    VALUES (?, NOW())
                    ON DUPLICATE KEY UPDATE relay_status = ?, timestamp = NOW()
                ");
                return $stmt->execute([$status, $status]);
            }
        } catch (PDOException $e) {
            error_log("Error updating relay status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert new sensor data
     */
    public function insertSensorData($temperature, $humidity, $relayStatus, $deviceId = null) {
        try {
            if ($deviceId) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO sensor_data 
                    (device_id, temperature, humidity, relay_status, timestamp) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                return $stmt->execute([$deviceId, $temperature, $humidity, $relayStatus]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO sensor_data 
                    (temperature, humidity, relay_status, timestamp) 
                    VALUES (?, ?, ?, NOW())
                ");
                return $stmt->execute([$temperature, $humidity, $relayStatus]);
            }
        } catch (PDOException $e) {
            error_log("Error inserting sensor data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get min/max thresholds for a device
     */
    public function getDeviceThresholds($deviceId) {
        try {
            $stmt = $this->pdo->prepare("SELECT temp_min, temp_max, humidity_min, humidity_max FROM devices WHERE device_id = ? LIMIT 1");
            $stmt->execute([$deviceId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching device thresholds: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update min/max thresholds for a device
     */
    public function updateDeviceThresholds($deviceId, $tempMin, $tempMax, $humidityMin, $humidityMax) {
        try {
            $stmt = $this->pdo->prepare("UPDATE devices SET temp_min = ?, temp_max = ?, humidity_min = ?, humidity_max = ?, updated_at = NOW() WHERE device_id = ?");
            return $stmt->execute([$tempMin, $tempMax, $humidityMin, $humidityMax, $deviceId]);
        } catch (PDOException $e) {
            error_log("Error updating device thresholds: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all devices
     */
    public function getAllDevices() {
        try {
            $stmt = $this->pdo->prepare("SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max, created_at, updated_at FROM devices ORDER BY device_id");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching all devices: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single device by ID
     */
    public function getDevice($deviceId) {
        try {
            $stmt = $this->pdo->prepare("SELECT device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max, created_at, updated_at FROM devices WHERE device_id = ? LIMIT 1");
            $stmt->execute([$deviceId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching device: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Add or update device
     */
    public function upsertDevice($deviceId, $user = null, $displayText = null, $relayStatus = 0, $tempMin = 20, $tempMax = 30, $humidityMin = 40, $humidityMax = 70) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO devices 
                (device_id, user, display_text, relay_status, temp_min, temp_max, humidity_min, humidity_max, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                user = COALESCE(VALUES(user), user),
                display_text = COALESCE(VALUES(display_text), display_text),
                relay_status = VALUES(relay_status),
                updated_at = NOW()
            ");
            return $stmt->execute([$deviceId, $user, $displayText, $relayStatus, $tempMin, $tempMax, $humidityMin, $humidityMax]);
        } catch (PDOException $e) {
            error_log("Error upserting device: " . $e->getMessage());
            return false;
        }
    }
}
?>