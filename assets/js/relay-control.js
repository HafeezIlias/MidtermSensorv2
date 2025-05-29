// ESP32 Sensor Monitor - Relay Control Functions

// Relay mode functions
function changeRelayMode(mode) {
    if (!currentDevice) {
        showStatus('Please select a device first');
        return;
    }
    
    relayMode = mode;
    
    // Update UI
    const modeIndicator = document.getElementById('mode-indicator');
    const toggleSwitch = document.getElementById('relay-toggle');
    const autoModeOption = document.getElementById('auto-mode-option');
    const manualModeOption = document.getElementById('manual-mode-option');
    const modeInfo = document.getElementById('relay-mode-info');
    
    // Update mode option styling
    autoModeOption.classList.remove('active');
    manualModeOption.classList.remove('active');
    
    if (mode === 'manual') {
        modeIndicator.textContent = 'Manual';
        modeIndicator.className = 'mode-indicator manual';
        manualModeOption.classList.add('active');
        toggleSwitch.disabled = false;
        modeInfo.textContent = 'Manual control enabled - use toggle switch';
        modeInfo.style.color = '#f57c00';
    } else {
        modeIndicator.textContent = 'Auto';
        modeIndicator.className = 'mode-indicator';
        autoModeOption.classList.add('active');
        toggleSwitch.disabled = true;
        modeInfo.textContent = 'Relay controlled by temperature/humidity thresholds';
        modeInfo.style.color = '#888';
    }
    
    // Send mode change to server
    fetch(`Backend/api/set_relay.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `device_id=${encodeURIComponent(currentDevice)}&mode=${mode}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showStatus(data.message);
            
            // Update relay status if provided in response
            if (data.relay_status !== undefined) {
                document.getElementById('relay-value').textContent = data.status || (data.relay_status ? 'ON' : 'OFF');
                const toggleSwitch = document.getElementById('relay-toggle');
                if (toggleSwitch) {
                    toggleSwitch.checked = data.relay_status;
                }
            }
        } else {
            showStatus('Error changing relay mode: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Relay mode change error:', error);
        showStatus('Error changing relay mode');
    });
}

// New toggle switch function
function toggleRelaySwitch() {
    if (!currentDevice) {
        showStatus('Please select a device first');
        return;
    }
    
    if (relayMode !== 'manual') {
        showStatus('Switch to Manual mode first');
        // Reset the toggle
        const toggleSwitch = document.getElementById('relay-toggle');
        toggleSwitch.checked = !toggleSwitch.checked;
        return;
    }
    
    const toggleSwitch = document.getElementById('relay-toggle');
    const newStatus = toggleSwitch.checked;
    
    // Disable toggle during request
    toggleSwitch.disabled = true;
    
    fetch(`Backend/api/set_relay.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `device_id=${encodeURIComponent(currentDevice)}&status=${newStatus ? 'ON' : 'OFF'}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Response is not JSON');
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Relay toggle response:', data); // Debug log
        
        if (data.success) {
            // Update UI with actual status from server
            document.getElementById('relay-value').textContent = data.status;
            toggleSwitch.checked = data.relay_status;
            showStatus(data.message);
            
            // Log for debugging
            console.log('Relay status updated:', {
                status: data.status,
                relay_status: data.relay_status,
                database_value: data.database_value
            });
        } else {
            showStatus('Error toggling relay: ' + (data.error || 'Unknown error'));
            // Reset the toggle on error
            toggleSwitch.checked = !toggleSwitch.checked;
        }
    })
    .catch(error => {
        console.error('Relay toggle error:', error);
        showStatus('Error toggling relay: ' + error.message);
        // Reset the toggle on error
        toggleSwitch.checked = !toggleSwitch.checked;
    })
    .finally(() => {
        // Re-enable toggle
        if (relayMode === 'manual') {
            toggleSwitch.disabled = false;
        }
    });
}

// Toggle relay - LEGACY FUNCTION (keeping for compatibility)
function toggleRelay() {
    // Redirect to new toggle switch function
    const toggleSwitch = document.getElementById('relay-toggle');
    toggleSwitch.checked = !toggleSwitch.checked;
    toggleRelaySwitch();
} 