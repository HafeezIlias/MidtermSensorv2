// ESP32 Sensor Monitor - Sensor Data Functions

// Enhanced sensor status checking with progress bars
function checkSensorStatus(value, min, max, sensorType) {
    let status, text, progress;
    
    if (value < min) {
        status = 'alert';
        text = `Too Low (${value.toFixed(1)})`;
        progress = 0;
    } else if (value > max) {
        status = 'alert';
        text = `Too High (${value.toFixed(1)})`;
        progress = 100;
    } else {
        // Calculate position within acceptable range
        const range = max - min;
        const position = value - min;
        progress = (position / range) * 100;
        
        // Determine warning levels
        const warningThreshold = 0.15; // 15% from edges
        const minWarning = min + (range * warningThreshold);
        const maxWarning = max - (range * warningThreshold);
        
        if (value < minWarning || value > maxWarning) {
            status = 'warning';
            text = `Warning (${value.toFixed(1)})`;
        } else {
            status = 'normal';
            text = `Normal (${value.toFixed(1)})`;
        }
    }
    
    // Update progress bar
    let progressBarId;
    if (sensorType.toLowerCase() === 'temperature') {
        progressBarId = 'temp-progress';
    } else if (sensorType.toLowerCase() === 'humidity') {
        progressBarId = 'humidity-progress';
    } else {
        progressBarId = `${sensorType.toLowerCase()}-progress`;
    }
    const progressBar = document.getElementById(progressBarId);
    if (progressBar) {
        console.log('Updating progress bar:', progressBarId, 'Progress:', progress); // Debug log
        progressBar.offsetWidth; // Force reflow
        progressBar.style.width = progress + '%';
        
        // Change color based on status
        if (status === 'alert') {
            progressBar.style.background = 'linear-gradient(90deg, #F44336, #E57373)';
        } else if (status === 'warning') {
            progressBar.style.background = 'linear-gradient(90deg, #FF9800, #FFB74D)';
        } else {
            progressBar.style.background = 'linear-gradient(90deg, #4CAF50, #8BC34A)';
        }
    }
    
    return { status, text };
}

// Update latest data
function updateLatestData() {
    if (!currentDevice) return;
    
    fetch(`Backend/api/get_latest.php?device_id=${currentDevice}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const temp = parseFloat(data.data.temperature);
            const humidity = parseFloat(data.data.humidity);
            const relayStatus = data.data.relay_status;
            
            // Update values
            document.getElementById('temp-value').textContent = temp.toFixed(1) + '°C';
            document.getElementById('humidity-value').textContent = humidity.toFixed(1) + '%';
            document.getElementById('relay-value').textContent = relayStatus ? 'ON' : 'OFF';
            
            // Update status indicators
            const tempStatus = checkSensorStatus(temp, deviceThresholds.temp_min, deviceThresholds.temp_max, 'Temperature');
            const tempStatusEl = document.getElementById('temp-status');
            const tempStatusIndicator = document.getElementById('temp-status-indicator');
            tempStatusEl.textContent = tempStatus.text;
            tempStatusIndicator.className = `status-indicator ${tempStatus.status}`;

            const humidityStatus = checkSensorStatus(humidity, deviceThresholds.humidity_min, deviceThresholds.humidity_max, 'Humidity');
            const humidityStatusEl = document.getElementById('humidity-status');
            const humidityStatusIndicator = document.getElementById('humidity-status-indicator');
            humidityStatusEl.textContent = humidityStatus.text;
            humidityStatusIndicator.className = `status-indicator ${humidityStatus.status}`;
            
            // Update toggle switch state
            const toggleSwitch = document.getElementById('relay-toggle');
            if (toggleSwitch) {
                toggleSwitch.checked = relayStatus;
            }
            
            // Update last update time
            document.getElementById('last-update-time').textContent = new Date().toLocaleTimeString();
        }
    })
    .catch(error => showStatus('Error updating data'));
}

// Auto-refresh functions
function toggleAutoRefresh() {
    autoRefreshEnabled = document.getElementById('auto-refresh-checkbox').checked;
    document.getElementById('refresh-status').textContent = autoRefreshEnabled ? 'ON' : 'OFF';
    
    if (autoRefreshEnabled) {
        startAutoRefresh();
    } else {
        stopAutoRefresh();
    }
}

function updateRefreshInterval() {
    refreshIntervalTime = parseInt(document.getElementById('refresh-interval').value);
    if (autoRefreshEnabled) {
        stopAutoRefresh();
        startAutoRefresh();
    }
}

function startAutoRefresh() {
    stopAutoRefresh();
    updateInterval = setInterval(() => {
        updateLatestData();
        checkDeviceStatus(); // Also check device status during auto-refresh
    }, refreshIntervalTime);
    
    chartUpdateInterval = setInterval(() => {
        const activeButton = document.querySelector('.time-button.active');
        if (activeButton) {
            const btnText = activeButton.textContent.toLowerCase();
            let period = '';
            
            if (btnText.includes('10 seconds')) period = '10s';
            else if (btnText.includes('30 seconds')) period = '30s';
            else if (btnText.includes('1 minute')) period = '1m';
            else if (btnText.includes('10 minutes')) period = '10m';
            else if (btnText.includes('30 minutes')) period = '30m';
            else if (btnText.includes('1 hour')) period = '1h';
            else if (btnText.includes('6 hours')) period = '6h';
            else if (btnText.includes('24 hours')) period = '24h';
            
            if (period) {
                loadChartData(period);
            }
        }
    }, refreshIntervalTime * 2); // Update chart less frequently
}

function stopAutoRefresh() {
    if (updateInterval) clearInterval(updateInterval);
    if (chartUpdateInterval) clearInterval(chartUpdateInterval);
}

function manualRefresh() {
    updateLatestData();
    const activeButton = document.querySelector('.time-button.active');
    if (activeButton) {
        const btnText = activeButton.textContent.toLowerCase();
        let period = '';
        
        if (btnText.includes('10 seconds')) period = '10s';
        else if (btnText.includes('30 seconds')) period = '30s';
        else if (btnText.includes('1 minute')) period = '1m';
        else if (btnText.includes('10 minutes')) period = '10m';
        else if (btnText.includes('30 minutes')) period = '30m';
        else if (btnText.includes('1 hour')) period = '1h';
        else if (btnText.includes('6 hours')) period = '6h';
        else if (btnText.includes('24 hours')) period = '24h';
        
        if (period) {
            loadChartData(period);
        }
    }
    showStatus('Data refreshed manually');
} 