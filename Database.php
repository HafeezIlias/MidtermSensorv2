<?php
// ESP32 MySQL Monitor - Database Connection Class
// Database.php

class Database {
    private $pdo;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->config['db_host']};dbname={$this->config['db_name']};charset=utf8mb4",
                $this->config['db_username'],
                $this->config['db_password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->pdo = null;
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function isConnected() {
        return $this->pdo !== null;
    }
    
    public function reconnect() {
        $this->connect();
        return $this->isConnected();
    }
    
    public function testConnection() {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            error_log("Database test failed: " . $e->getMessage());
            return false;
        }
    }
}
?>