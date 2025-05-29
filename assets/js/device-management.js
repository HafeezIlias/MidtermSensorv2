// ESP32 Sensor Monitor - Device Management Functions

// Load devices
function loadDevices() {
    fetch('Backend/api/device/list.php')
    .then(response => response.json())
    .then(data => {
        const select = document.getElementById('device-select');
        select.innerHTML = '<option value="">Select a device...</option>';
        
        if (data.success && data.devices) {
            data.devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.device_id;
                // Format: Display Text (Device ID)
                option.textContent = `${device.display_text}`;
                select.appendChild(option);
            });
            
            // Auto-select first device if available
            if (data.devices.length > 0) {
                select.value = data.devices[0].device_id;
                changeDevice();
            }
        }
    })
    .catch(error => showStatus('Error loading devices'));
}

// Change device
function changeDevice() {
    const select = document.getElementById('device-select');
    currentDevice = select.value;
    
    if (currentDevice) {
        loadDeviceThresholds();
        updateLatestData();
        loadChartData('1h');
        startDeviceStatusMonitoring(); // Start monitoring device status
    } else {
        stopDeviceStatusMonitoring(); // Stop monitoring if no device selected
        updateDeviceStatusIndicator('unknown', 'No device selected');
    }
}

// Load device thresholds
function loadDeviceThresholds() {
    if (!currentDevice) return;
    
    fetch(`Backend/api/device/get.php?device_id=${currentDevice}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.device) {
            deviceThresholds = {
                temp_min: parseFloat(data.device.temp_min),
                temp_max: parseFloat(data.device.temp_max),
                humidity_min: parseFloat(data.device.humidity_min),
                humidity_max: parseFloat(data.device.humidity_max)
            };
            
            // Update threshold display
            document.getElementById('temp-min-display').textContent = deviceThresholds.temp_min.toFixed(1) + '°C';
            document.getElementById('temp-max-display').textContent = deviceThresholds.temp_max.toFixed(1) + '°C';
            document.getElementById('humidity-min-display').textContent = deviceThresholds.humidity_min.toFixed(1) + '%';
            document.getElementById('humidity-max-display').textContent = deviceThresholds.humidity_max.toFixed(1) + '%';
            
            // Load relay mode
            const deviceRelayMode = data.device.relay_mode || 'auto';
            relayMode = deviceRelayMode;
            
            // Update UI based on relay mode
            document.querySelector(`input[name="relay-mode"][value="${deviceRelayMode}"]`).checked = true;
            changeRelayMode(deviceRelayMode);
        }
    })
    .catch(error => console.error('Error loading thresholds:', error));
}

// Device status checking
function checkDeviceStatus() {
    if (!currentDevice) {
        updateDeviceStatusIndicator('unknown', 'No device selected');
        return;
    }

    fetch(`Backend/api/get_latest.php?device_id=${currentDevice}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const dataTimestamp = new Date(data.data.timestamp);
            const now = new Date();
            const timeDiff = (now - dataTimestamp) / 1000; // seconds
            
            // Update last data timestamp
            lastDataTimestamp = dataTimestamp;
            
            // Determine device status based on data age
            if (timeDiff <= 30) {
                // Data within last 30 seconds = online
                updateDeviceStatusIndicator('online', 'Online');
            } else if (timeDiff <= 120) {
                // Data within last 2 minutes = recently online
                updateDeviceStatusIndicator('online', `Last seen ${Math.round(timeDiff)}s ago`);
            } else if (timeDiff <= 300) {
                // Data within last 5 minutes = warning
                updateDeviceStatusIndicator('unknown', `Last seen ${Math.round(timeDiff/60)}m ago`);
            } else {
                // No data for more than 5 minutes = offline
                const minutes = Math.round(timeDiff / 60);
                const hours = Math.round(timeDiff / 3600);
                const timeText = hours > 1 ? `${hours}h ago` : `${minutes}m ago`;
                updateDeviceStatusIndicator('offline', `Offline (${timeText})`);
            }
        } else {
            updateDeviceStatusIndicator('offline', 'No data available');
        }
    })
    .catch(error => {
        console.error('Device status check error:', error);
        updateDeviceStatusIndicator('offline', 'Connection error');
    });
}

function updateDeviceStatusIndicator(status, text) {
    const deviceDot = document.getElementById('device-dot');
    const deviceStatus = document.getElementById('device-status');
    
    // Remove all status classes
    deviceDot.classList.remove('online', 'offline', 'unknown');
    
    // Add current status class
    deviceDot.classList.add(status);
    
    // Update status text
    deviceStatus.textContent = text;
    
    // Log status changes
    console.log(`Device status: ${status} - ${text}`);
}

function startDeviceStatusMonitoring() {
    // Check device status every 15 seconds
    if (deviceStatusInterval) {
        clearInterval(deviceStatusInterval);
    }
    
    deviceStatusInterval = setInterval(checkDeviceStatus, 15000);
    
    // Initial check
    checkDeviceStatus();
}

function stopDeviceStatusMonitoring() {
    if (deviceStatusInterval) {
        clearInterval(deviceStatusInterval);
        deviceStatusInterval = null;
    }
} 