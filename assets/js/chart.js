// ESP32 Sensor Monitor - Chart Functions

// Initialize chart
function initChart() {
    const ctx = document.getElementById('sensor-chart').getContext('2d');
    sensorChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Temperature (°C)',
                    data: [],
                    borderColor: '#ff6384',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Humidity (%)',
                    data: [],
                    borderColor: '#36a2eb',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

// Convert time period to hours for API
function timeToHours(period) {
    const conversions = {
        '10s': 10/3600,      // 10 seconds = 0.00278 hours
        '30s': 30/3600,      // 30 seconds = 0.00833 hours
        '1m': 1/60,          // 1 minute = 0.01667 hours
        '10m': 10/60,        // 10 minutes = 0.1667 hours
        '30m': 30/60,        // 30 minutes = 0.5 hours
        '1h': 1,             // 1 hour
        '6h': 6,             // 6 hours
        '24h': 24            // 24 hours
    };
    return conversions[period] || 1;
}

// Format timestamp based on time period
function formatTimestamp(timestamp, period) {
    const date = new Date(timestamp);
    
    if (period === '10s' || period === '30s' || period === '1m') {
        // Show hours:minutes:seconds for short periods
        return date.toLocaleTimeString([], {
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit'
        });
    } else if (period === '10m' || period === '30m' || period === '1h') {
        // Show hours:minutes:seconds for medium periods
        return date.toLocaleTimeString([], {
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit'
        });
    } else {
        // Show date and time for longer periods
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {
            hour: '2-digit', 
            minute: '2-digit'
        });
    }
}

// Load chart data
function loadChartData(period) {
    if (!currentDevice) {
        showStatus('Please select a device first');
        return;
    }

    console.log(`Loading chart data for period: ${period}`);

    // Remove active class from all buttons and add to clicked button
    document.querySelectorAll('.time-button').forEach(btn => btn.classList.remove('active'));
    
    // Find and activate the correct button based on period
    const buttons = document.querySelectorAll('.time-button');
    buttons.forEach(btn => {
        const btnText = btn.textContent.toLowerCase();
        let btnPeriod = '';
        
        if (btnText.includes('10 seconds')) btnPeriod = '10s';
        else if (btnText.includes('30 seconds')) btnPeriod = '30s';
        else if (btnText.includes('1 minute')) btnPeriod = '1m';
        else if (btnText.includes('10 minutes')) btnPeriod = '10m';
        else if (btnText.includes('30 minutes')) btnPeriod = '30m';
        else if (btnText.includes('1 hour')) btnPeriod = '1h';
        else if (btnText.includes('6 hours')) btnPeriod = '6h';
        else if (btnText.includes('24 hours')) btnPeriod = '24h';
        
        if (btnPeriod === period) {
            btn.classList.add('active');
        }
    });

    const hours = timeToHours(period);
    console.log(`Converted ${period} to ${hours} hours`);
    
    fetch('Backend/api/get_historical.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `hours=${hours}&device_id=${currentDevice}`
    })
    .then(response => {
        console.log(`API Response status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        console.log('API Response data:', data);
        
        if (data.success) {
            if (data.data && data.data.length > 0) {
                const labels = data.data.map(item => formatTimestamp(item.timestamp, period));
                const tempData = data.data.map(item => parseFloat(item.temperature));
                const humidityData = data.data.map(item => parseFloat(item.humidity));

                sensorChart.data.labels = labels;
                sensorChart.data.datasets[0].data = tempData;
                sensorChart.data.datasets[1].data = humidityData;
                sensorChart.update();
                
                // Check for new data
                if (data.data.length > lastDataCount) {
                    lastDataCount = data.data.length;
                    showStatus('New data received!');
                }
                
                console.log(`✅ Loaded ${data.count} records for ${period} period (${data.time_unit}: ${data.time_value})`);
                showStatus(`Loaded ${data.count} records for ${period} period`);
            } else {
                // No data available for this period
                sensorChart.data.labels = [];
                sensorChart.data.datasets[0].data = [];
                sensorChart.data.datasets[1].data = [];
                sensorChart.update();
                
                console.log(`⚠️ No data available for ${period} period`);
                showStatus(`No data available for ${period} period - try a longer time range`);
            }
        } else {
            console.error('API Error:', data);
            showStatus('Error loading chart data: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Chart data error:', error);
        showStatus('Error loading chart data: ' + error.message);
    });
} 