// ESP32 Sensor Monitor - Main Application
// Global Variables
let sensorChart;
let updateInterval;
let chartUpdateInterval;
let deviceStatusInterval;
let currentDevice = '';
let deviceThresholds = {};
let autoRefreshEnabled = true;
let refreshIntervalTime = 10000;
let lastDataCount = 0;
let relayMode = 'auto';
let lastDataTimestamp = null;

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initChart();
    loadDevices();
    startAutoRefresh();
    // Device status monitoring will start when a device is selected
});

// Handle page visibility
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
        stopDeviceStatusMonitoring();
    } else if (autoRefreshEnabled) {
        updateLatestData();
        startAutoRefresh();
        if (currentDevice) {
            startDeviceStatusMonitoring();
        }
    }
});

// Show status message
function showStatus(message) {
    const status = document.getElementById('status-message');
    status.textContent = message;
    status.style.display = 'block';
    setTimeout(() => status.style.display = 'none', 3000);
} 