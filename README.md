# ESP32 Sensor Monitor - Refactored

## Overview
This is a refactored version of the ESP32 Sensor Monitor with improved code organization and updated API paths.

## Changes Made

### 1. SensorModel.php Usage
- **Status**: Still in use, but only in `Backend/api/status.php`
- **Location**: Root directory (`SensorModel.php`)
- **Usage**: Used for system status checking and getting latest data timestamps
- **Recommendation**: Consider migrating its functionality to individual API files for consistency

### 2. Updated API Paths
All API calls in the frontend have been updated to use the new `Backend/api/` structure:

**Old paths** → **New paths**:
- `api/device/list.php` → `Backend/api/device/list.php`
- `api/device/get.php` → `Backend/api/device/get.php`
- `api/get_historical.php` → `Backend/api/get_historical.php`
- `api/get_latest.php` → `Backend/api/get_latest.php`
- `api/set_relay.php` → `Backend/api/set_relay.php`
- `api/device/update_thresholds.php` → `Backend/api/device/update_thresholds.php`

### 3. Organized CSS and JavaScript

#### CSS Structure (`assets/css/`)
- **`base.css`** - Global styles, reset, layout, animations
- **`components.css`** - Device selector, cards, status indicators
- **`sensor-cards.css`** - Modern sensor cards, thresholds, progress bars
- **`relay-controls.css`** - Toggle switches, mode selectors, relay controls
- **`charts.css`** - Chart section, time controls, responsive design

#### JavaScript Structure (`assets/js/`)
- **`app.js`** - Main application, global variables, initialization
- **`chart.js`** - Chart initialization, data loading, time formatting
- **`device-management.js`** - Device loading, selection, status monitoring
- **`sensor-data.js`** - Sensor status checking, data updates, auto-refresh
- **`relay-control.js`** - Relay mode management, toggle controls
- **`threshold-management.js`** - Threshold editing, validation, saving

## Project Structure
```
MidtermSensorv2/
├── assets/
│   ├── css/
│   │   ├── base.css
│   │   ├── components.css
│   │   ├── sensor-cards.css
│   │   ├── relay-controls.css
│   │   └── charts.css
│   └── js/
│       ├── app.js
│       ├── chart.js
│       ├── device-management.js
│       ├── sensor-data.js
│       ├── relay-control.js
│       └── threshold-management.js
├── Backend/
│   └── api/
│       ├── db/
│       │   ├── config.php
│       │   └── Database.php
│       ├── device/
│       │   ├── config.php
│       │   ├── get.php
│       │   ├── list.php
│       │   ├── register.php
│       │   ├── sensor_data.php
│       │   └── update_thresholds.php
│       ├── get_historical.php
│       ├── get_latest.php
│       ├── set_relay.php
│       └── status.php
├── Arduino/
├── components/
├── index.html
└── SensorModel.php
```

## Benefits of Refactoring

### 1. **Maintainability**
- Separated concerns: CSS, JavaScript, and HTML are now in separate files
- Modular structure makes it easier to find and modify specific functionality
- Each component has a clear responsibility

### 2. **Performance**
- CSS and JavaScript files can be cached by browsers
- Smaller file sizes for individual components
- Better loading performance

### 3. **Scalability**
- Easy to add new features by creating new component files
- Reusable components across different pages
- Clear separation of frontend and backend logic

### 4. **Developer Experience**
- Better code organization for team development
- Easier debugging with separated concerns
- Clear file naming conventions

## API Endpoints

### Device Management
- `GET Backend/api/device/list.php` - List all devices
- `GET Backend/api/device/get.php?device_id={id}` - Get device details
- `POST Backend/api/device/update_thresholds.php` - Update device thresholds

### Sensor Data
- `POST Backend/api/get_latest.php?device_id={id}` - Get latest sensor data
- `POST Backend/api/get_historical.php` - Get historical data

### Relay Control
- `POST Backend/api/set_relay.php` - Control relay (mode/status)

### System Status
- `GET/POST Backend/api/status.php` - Get system status

## Usage

1. **Development**: All files are organized for easy development and debugging
2. **Production**: CSS and JS files can be minified and combined if needed
3. **Maintenance**: Each component can be updated independently

## Notes

- The `SensorModel.php` file is still used in `status.php` but could be refactored for consistency
- All API paths have been updated to use the new `Backend/api/` structure
- The frontend is now completely modular with separated CSS and JavaScript components
- Responsive design is maintained across all components 