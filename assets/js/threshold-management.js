// ESP32 Sensor Monitor - Threshold Management Functions

// Threshold editing functions
function toggleThresholdEdit(sensorType) {
    const editForm = document.getElementById(`${sensorType}-threshold-edit`);
    const display = document.getElementById(`${sensorType}-threshold-display`);
    const isEditing = editForm.classList.contains('active');
    
    if (isEditing) {
        cancelThresholdEdit(sensorType);
    } else {
        // Show edit form
        editForm.classList.add('active');
        display.style.opacity = '0.5';
        
        // Populate current values
        if (sensorType === 'temp') {
            document.getElementById('temp-min-input').value = deviceThresholds.temp_min;
            document.getElementById('temp-max-input').value = deviceThresholds.temp_max;
        } else {
            document.getElementById('humidity-min-input').value = deviceThresholds.humidity_min;
            document.getElementById('humidity-max-input').value = deviceThresholds.humidity_max;
        }
        
        // Focus first input
        document.getElementById(`${sensorType}-min-input`).focus();
    }
}

function cancelThresholdEdit(sensorType) {
    const editForm = document.getElementById(`${sensorType}-threshold-edit`);
    const display = document.getElementById(`${sensorType}-threshold-display`);
    
    editForm.classList.remove('active');
    display.style.opacity = '1';
    
    // Clear inputs
    document.getElementById(`${sensorType}-min-input`).value = '';
    document.getElementById(`${sensorType}-max-input`).value = '';
}

function saveThresholds(sensorType) {
    if (!currentDevice) {
        showStatus('Please select a device first');
        return;
    }
    
    const minInput = document.getElementById(`${sensorType}-min-input`);
    const maxInput = document.getElementById(`${sensorType}-max-input`);
    
    const minValue = parseFloat(minInput.value);
    const maxValue = parseFloat(maxInput.value);
    
    // Validation
    if (isNaN(minValue) || isNaN(maxValue)) {
        showStatus('Please enter valid numbers');
        return;
    }
    
    if (minValue >= maxValue) {
        showStatus('Minimum value must be less than maximum value');
        return;
    }
    
    // Additional validation for humidity
    if (sensorType === 'humidity') {
        if (minValue < 0 || maxValue > 100) {
            showStatus('Humidity values must be between 0% and 100%');
            return;
        }
    }
    
    // Prepare data
    const updateData = {
        device_id: currentDevice
    };
    
    if (sensorType === 'temp') {
        updateData.temp_min = minValue;
        updateData.temp_max = maxValue;
    } else {
        updateData.humidity_min = minValue;
        updateData.humidity_max = maxValue;
    }
    
    // Debug logging
    console.log('Saving thresholds:', {
        sensorType: sensorType,
        currentDevice: currentDevice,
        updateData: updateData
    });
    
    // Disable save button during request
    const saveBtn = document.querySelector(`#${sensorType}-threshold-edit .save`);
    saveBtn.disabled = true;
    saveBtn.textContent = '💾 Saving...';
    
    // Send update request
    fetch('Backend/api/device/update_thresholds.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
            // Update local thresholds
            if (sensorType === 'temp') {
                deviceThresholds.temp_min = minValue;
                deviceThresholds.temp_max = maxValue;
                document.getElementById('temp-min-display').textContent = minValue.toFixed(1) + '°C';
                document.getElementById('temp-max-display').textContent = maxValue.toFixed(1) + '°C';
            } else {
                deviceThresholds.humidity_min = minValue;
                deviceThresholds.humidity_max = maxValue;
                document.getElementById('humidity-min-display').textContent = minValue.toFixed(1) + '%';
                document.getElementById('humidity-max-display').textContent = maxValue.toFixed(1) + '%';
            }
            
            // Close edit form
            cancelThresholdEdit(sensorType);
            
            // Refresh current data to update status
            updateLatestData();
            
            showStatus(`${sensorType === 'temp' ? 'Temperature' : 'Humidity'} thresholds updated successfully!`);
        } else {
            showStatus('Error updating thresholds: ' + (data.error || 'Unknown error'));
            console.error('API Error:', data);
        }
    })
    .catch(error => {
        console.error('Threshold update error:', error);
        showStatus('Error updating thresholds: ' + error.message);
    })
    .finally(() => {
        // Re-enable save button
        saveBtn.disabled = false;
        saveBtn.textContent = '�� Save';
    });
} 