#include <WiFi.h>
#include <EEPROM.h>
#include <Wire.h>
#include "DHT.h"
#include <Preferences.h>
#include <Adafruit_SSD1306.h>
#include <DNSServer.h>
#include <HTTPClient.h>
#include <ESPAsyncWebServer.h>
#include <ArduinoJson.h>

// ===Server=== //
String serverName = "http://sensorbased.feeyazproduction.com/";
HTTPClient http;

// === OLED ===
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 32
#define OLED_RESET -1
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// === EEPROM ===
#define EEPROM_SIZE 128
#define SSID_ADDR 0
#define PASS_ADDR 32
#define DEVICEID_ADDR 64
#define DISPLAYTEXT_ADDR 96

// === Pins ===
#define DHTPIN 4
#define DHTTYPE DHT11
#define RELAY_PIN 2
#define CONFIG_BUTTON 0

DNSServer dns;
AsyncWebServer server(80);

// === Flags ===
bool configMode = false;
DHT dht(DHTPIN, DHTTYPE);

// === Declare global variables ===
Preferences prefs;
String displayText = "";
String deviceId = "";
bool relayStatus = false;
String relayMode = "auto"; // NEW: Track relay mode
float tempMax = 35.0;
float tempMin = 20.0;
float humMax = 85.0;
float humMin = 40.0;
bool deviceRegistered = false;
String currentAlert = "";
bool hasAlert = false;

// === EEPROM Helpers ===
void writeStringToEEPROM(int addr, const String &str) {
  for (int i = 0; i < 32; ++i)
    EEPROM.write(addr + i, i < str.length() ? str[i] : 0);
  EEPROM.commit();
}

String readStringFromEEPROM(int addr) {
  char data[33];
  for (int i = 0; i < 32; ++i)
    data[i] = EEPROM.read(addr + i);
  data[32] = '\0';
  return String(data);
}

// === Device Registration Function ===
bool registerDevice() {
  if (deviceId.length() == 0) {
    Serial.println("❌ Device ID is empty, cannot register");
    return false;
  }

  String httpReqStr = serverName + "api/device/register.php?device_id=" + deviceId + "&user=ESP32_User&display_text=" + displayText;
  http.begin(httpReqStr.c_str());
  int httpResponseCode = http.GET();
  
  if (httpResponseCode > 0) {
    String payload = http.getString();
    Serial.println("Registration Response: " + payload);
    
    StaticJsonDocument<512> doc;
    DeserializationError error = deserializeJson(doc, payload);
    
    if (!error && doc["success"]) {
      Serial.println("✅ Device registration successful");
      
      // Update thresholds from server response
      if (doc["device"]["temp_min"]) {
        tempMin = doc["device"]["temp_min"];
        tempMax = doc["device"]["temp_max"];
        humMin = doc["device"]["humidity_min"];
        humMax = doc["device"]["humidity_max"];
        
        // Store updated thresholds
        prefs.begin("thresholds", false);
        prefs.putFloat("tempMax", tempMax);
        prefs.putFloat("tempMin", tempMin);
        prefs.putFloat("humidityMax", humMax);
        prefs.putFloat("humidityMin", humMin);
        prefs.end();
        
        Serial.println("📊 Thresholds updated from server");
      }
      
      deviceRegistered = true;
      http.end();
      return true;
    } else {
      Serial.println("❌ Registration failed: Invalid response");
    }
  } else {
    Serial.print("❌ Registration HTTP error: ");
    Serial.println(httpResponseCode);
  }
  
  http.end();
  return false;
}

void startCaptivePortal() {
  WiFi.softAP("ESP32_Config_Hafeez", "");
  dns.start(53, "*", WiFi.softAPIP());

  float temp = dht.readTemperature();
  float hum = dht.readHumidity();
  String relayStatusStr = digitalRead(RELAY_PIN) == HIGH ? "ON" : "OFF";

  // Read EEPROM data
  String ssid = readStringFromEEPROM(SSID_ADDR);
  String pass = readStringFromEEPROM(PASS_ADDR);
  deviceId = readStringFromEEPROM(DEVICEID_ADDR);
  displayText = readStringFromEEPROM(DISPLAYTEXT_ADDR);

  // Read Preferences data
  prefs.begin("thresholds", true);
  float tempMax = prefs.getFloat("tempMax", 40.0);
  float tempMin = prefs.getFloat("tempMin", 20.0);
  float humidityMax = prefs.getFloat("humidityMax", 80.0);
  float humidityMin = prefs.getFloat("humidityMin", 30.0);
  prefs.end();

  // === HTML Generation ===
  String html = "<!DOCTYPE html><html><head><title>ESP32 Config</title><meta name='viewport' content='width=device-width, initial-scale=1'>";
  html += "<style>body{font-family:Arial;background:#f3f4f9;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}h2{color:#1e3a8a;text-align:center;}.container{background:#fff;border-radius:15px;padding:40px;max-width:400px;box-shadow:0 4px 6px rgba(0,0,0,0.1);}input,select{width:100%;padding:10px;margin:8px 0;border-radius:8px;border:1px solid #ddd;}button{background:#4CAF50;color:#fff;padding:12px;width:100%;border-radius:8px;border:none;}button:hover{background:#45a049;}.status{text-align:center;margin-top:20px;}</style></head><body>";
  html += "<div class='container'><h2>ESP32 Config Portal</h2><form action='/save'>";
  html += "<input name='ssid' placeholder='WiFi SSID' value='" + ssid + "' required><br>";
  html += "<input name='pass' placeholder='WiFi Password' value='" + pass + "'><br>";
  html += "<input name='deviceId' placeholder='Device ID' value='" + deviceId + "' required><br>";
  html += "<input name='displayText' placeholder='Display Text' value='" + displayText + "' required><br>";
  html += "<input name='tempMax' placeholder='Temperature Max' value='" + String(tempMax) + "' required><br>";
  html += "<input name='tempMin' placeholder='Temperature Min' value='" + String(tempMin) + "' required><br>";
  html += "<input name='humidityMax' placeholder='Humidity Max' value='" + String(humidityMax) + "' required><br>";
  html += "<input name='humidityMin' placeholder='Humidity Min' value='" + String(humidityMin) + "' required><br>";
  html += "<label>Relay:</label><select name='relay'>";
  html += "<option value='on'" + String(relayStatusStr == "ON" ? " selected" : "") + ">ON</option>";
  html += "<option value='off'" + String(relayStatusStr == "OFF" ? " selected" : "") + ">OFF</option>";
  html += "</select><br><button type='submit'>Save & Restart</button></form>";
  html += "<div class='status'><strong>Current:</strong><br>Temp: " + String(temp, 1) + "°C<br>Humidity: " + String(hum, 1) + "%<br>Relay: " + relayStatusStr + "</div></div></body></html>";

  server.on("/", HTTP_GET, [html](AsyncWebServerRequest *request) {
    request->send(200, "text/html", html);
  });

  server.on("/save", HTTP_GET, [](AsyncWebServerRequest *request) {
    String ssid = request->getParam("ssid")->value();
    String pass = request->getParam("pass")->value();
    String deviceId = request->getParam("deviceId")->value();
    String displayText = request->getParam("displayText")->value();
    float tempMax = request->getParam("tempMax")->value().toFloat();
    float tempMin = request->getParam("tempMin")->value().toFloat();
    float humidityMax = request->getParam("humidityMax")->value().toFloat();
    float humidityMin = request->getParam("humidityMin")->value().toFloat();

    writeStringToEEPROM(SSID_ADDR, ssid);
    writeStringToEEPROM(PASS_ADDR, pass);
    writeStringToEEPROM(DEVICEID_ADDR, deviceId);
    writeStringToEEPROM(DISPLAYTEXT_ADDR, displayText);

    // Store thresholds to Preferences
    prefs.begin("thresholds", false);
    prefs.putFloat("tempMax", tempMax);
    prefs.putFloat("tempMin", tempMin);
    prefs.putFloat("humidityMax", humidityMax);
    prefs.putFloat("humidityMin", humidityMin);
    prefs.end();

    request->send(200, "text/html", "Saved. Rebooting...");
    delay(1000);
    ESP.restart();
  });

  server.begin();

  display.clearDisplay();
  display.setCursor(0, 0);
  display.println("AP Mode: ESP32_Config");
  display.setCursor(0, 10);
  display.println("Go to: 192.168.4.1");
  display.setCursor(0, 20);
  display.println("Config WiFi & Relay");
  display.display();
}

void sendDataToMysql(String deviceId, float temperature, float humidity, bool relayStatus) {
  String httpReqStr = serverName + "api/device/sensor_data.php?device_id=" + deviceId + "&temperature=" + temperature + "&humidity=" + humidity + "&relay_status=" + relayStatus;
  http.begin(httpReqStr.c_str());
  int httpResponseCode = http.GET();
  
  if (httpResponseCode > 0) {
    Serial.print("📤 Data sent - HTTP Response: ");
    Serial.println(httpResponseCode);
    String payload = http.getString();
    Serial.println(payload);
  } else {
    Serial.print("❌ Data send error: ");
    Serial.println(httpResponseCode);
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("Failed Send Data");
    display.display();
  }
  http.end();
}

// === Setup ===
void setup() {
  Serial.begin(115200);
  pinMode(CONFIG_BUTTON, INPUT_PULLUP);
  EEPROM.begin(EEPROM_SIZE);
  pinMode(RELAY_PIN, OUTPUT);

  display.begin(SSD1306_SWITCHCAPVCC, 0x3C);
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(WHITE);
  display.setCursor(0, 0);
  display.println("Starting...");
  display.display();
  delay(1000);

  String ssid = readStringFromEEPROM(SSID_ADDR);
  String pass = readStringFromEEPROM(PASS_ADDR);
  deviceId = readStringFromEEPROM(DEVICEID_ADDR);
  displayText = readStringFromEEPROM(DISPLAYTEXT_ADDR);

  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.begin(ssid.c_str(), pass.c_str());
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry++ < 20) {
    delay(500);
    Serial.println("Connecting to WiFi...");
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi connection failed. Starting Captive Portal...");
    startCaptivePortal();
    return;
  }

  Serial.println("WiFi connected!");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());

  // Register device with server
  display.clearDisplay();
  display.setCursor(0, 0);
  display.println("Registering Device...");
  display.display();
  
  if (registerDevice()) {
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("Device Registered");
    display.setCursor(0, 10);
    display.println("Device ID: " + deviceId);
    display.display();
  } else {
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("Registration Failed");
    display.display();
  }
  
  delay(2000);

  // Send initial data
  float temp = dht.readTemperature();
  float hum = dht.readHumidity();
  sendDataToMysql(deviceId, temp, hum, relayStatus);
  Serial.println("✅ Sent initial data to MySQL");
}

void loop() {
  String ssid = readStringFromEEPROM(SSID_ADDR);
  String password = readStringFromEEPROM(PASS_ADDR);
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi lost. Reconnecting...");
    WiFi.disconnect();
    WiFi.begin(ssid, password);
    delay(5000);
    return;
  }

  // Read DHT11 data
  float temp = dht.readTemperature();
  float hum = dht.readHumidity();
  
  // Check if readings are valid
  if (isnan(temp) || isnan(hum)) {
    Serial.println("Failed to read from DHT sensor!");
    return;
  }
  
  sendDataToMysql(deviceId, temp, hum, relayStatus);
  delay(1500);
  getDataFromMySql(deviceId, temp, hum); // FIXED: Simplified function call
  delay(1500);
  checkForAlerts(temp, hum);
  updateDisplay(temp, hum, relayStatus);
  handleConfigButtonPress();
  
  delay(8000);
}

// FIXED: Simplified getDataFromMySql function with proper relay mode integration
void getDataFromMySql(String deviceId, float temp, float hum) {
    String httpReqStr = serverName + "api/device/get.php?device_id=" + deviceId;
    http.begin(httpReqStr.c_str());
    int httpResponseCode = http.GET();

    if (httpResponseCode > 0) {
        String payload = http.getString();
        
        StaticJsonDocument<512> doc;
        DeserializationError error = deserializeJson(doc, payload);

        if (!error && doc["success"]) {
            JsonObject device = doc["device"];
            
            String newDisplayText = String((const char*)device["display_text"]);
            String newRelayMode = String((const char*)device["relay_mode"]);
            bool newRelayStatus = device["relay_status"];
            float newTempMax = device["temp_max"];
            float newTempMin = device["temp_min"];
            float newHumidityMax = device["humidity_max"];
            float newHumidityMin = device["humidity_min"];

            bool configChanged = false;

            // Update relay mode
if (newRelayMode != relayMode) {
    relayMode = newRelayMode;
    Serial.println("⚙️ Relay mode updated: " + relayMode);
    configChanged = true;
}

// Handle relay control based on current mode
if (relayMode == "manual") {
    // Manual mode - relay controlled by web interface
    Serial.println("👤 Manual mode active - web control");
    
    // Check for relay status change in manual mode
    if (newRelayStatus != relayStatus) {
        relayStatus = newRelayStatus;
        digitalWrite(RELAY_PIN, relayStatus ? HIGH : LOW);
        Serial.println("🔌 Relay updated from server: " + String(relayStatus ? "ON" : "OFF"));
        
        // Show on display immediately
        display.clearDisplay();
        display.setCursor(0, 0);
        display.println("Relay Updated!");
        display.setCursor(0, 10);
        display.println("Status: " + String(relayStatus ? "ON" : "OFF"));
        display.display();
        delay(2000);
        
        configChanged = true;
    }
} else {
    // Auto mode - use threshold-based control
    Serial.println("🤖 Auto mode active - threshold control");
    checkThresholdAndControlRelay(temp, hum);
}
            

            // Update display text if changed
            if (newDisplayText != displayText && newDisplayText.length() > 0) {
                displayText = newDisplayText;
                writeStringToEEPROM(DISPLAYTEXT_ADDR, displayText);
                configChanged = true;
            }

            // Update thresholds if changed
            prefs.begin("thresholds", false);
            if (prefs.getFloat("tempMax", 0) != newTempMax) {
                prefs.putFloat("tempMax", newTempMax);
                tempMax = newTempMax;
                configChanged = true;
            }
            if (prefs.getFloat("tempMin", 0) != newTempMin) {
                prefs.putFloat("tempMin", newTempMin);
                tempMin = newTempMin;
                configChanged = true;
            }
            if (prefs.getFloat("humidityMax", 0) != newHumidityMax) {
                prefs.putFloat("humidityMax", newHumidityMax);
                humMax = newHumidityMax;
                configChanged = true;
            }
            if (prefs.getFloat("humidityMin", 0) != newHumidityMin) {
                prefs.putFloat("humidityMin", newHumidityMin);
                humMin = newHumidityMin;
                configChanged = true;
            }
            prefs.end();

            if (configChanged) {
                Serial.println("✅ Configuration updated from server");
            }
        }
    } else {
        Serial.print("❌ Config check failed: ");
        Serial.println(httpResponseCode);
    }

    http.end();
}
// Modified updateDisplay function to show alerts
void updateDisplay(float temp, float hum, bool relayStatus) {
  // First screen - WiFi, Device ID, Mode
  display.clearDisplay();

  display.setCursor(0, 0);
  display.println(WiFi.status() == WL_CONNECTED ? "WiFi: Connected" : "WiFi: Disconnected");

  display.setCursor(0, 10);
  display.print("ID: ");
  display.print(deviceId.substring(0, 12));

  display.setCursor(0, 20);
  display.print("Mode: ");
  display.print(relayMode.substring(0, 6));
  display.display();

  delay(2000);

  // Second screen - Temperature, Humidity, Relay
  display.clearDisplay();
  display.setCursor(0, 0);
  display.print("Temp: ");
  display.print(temp, 1);
  display.print("C");

  display.setCursor(0, 10);
  display.print("Hum: ");
  display.print(hum, 1);
  display.print("%");

  display.setCursor(0, 20);
  display.print("Relay: ");
  display.print(relayStatus ? "ON" : "OFF");
  
  display.display();
  
  // If there's an alert, show it on a third screen
  if (hasAlert) {
    delay(2000);
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("ALERT! (" + relayMode + ")");
    display.setCursor(0, 10);
    display.println(currentAlert);
    display.display();
    delay(3000); // Show alert for 3 seconds
  }
}

//check thresholds in both manual and auto modes
void checkForAlerts(float temperature, float humidity) {
  prefs.begin("thresholds", true);
  float tempMax = prefs.getFloat("tempMax", 40.0);
  float tempMin = prefs.getFloat("tempMin", 20.0);
  float humidityMax = prefs.getFloat("humidityMax", 80.0);
  float humidityMin = prefs.getFloat("humidityMin", 30.0);
  prefs.end();

  bool tempAlert = false;
  bool humAlert = false;
  String alertMessage = "";

  // Check temperature
  if (temperature < tempMin) {
    tempAlert = true;
    alertMessage += "TEMP LOW ";
  } else if (temperature > tempMax) {
    tempAlert = true;
    alertMessage += "TEMP HIGH ";
  }

  // Check humidity
  if (humidity < humidityMin) {
    humAlert = true;
    alertMessage += "HUM LOW ";
  } else if (humidity > humidityMax) {
    humAlert = true;
    alertMessage += "HUM HIGH ";
  }

  // Update global alert status
  if (tempAlert || humAlert) {
    currentAlert = alertMessage.substring(0, alertMessage.length() - 1);
    hasAlert = true;
  } else {
    currentAlert = "";
    hasAlert = false;
  }
}

void handleConfigButtonPress() {
  static unsigned long buttonPressTime = 0;
  static bool inConfig = false;

  if (digitalRead(CONFIG_BUTTON) == LOW && !inConfig) {
    if (buttonPressTime == 0) {
      buttonPressTime = millis();
    } else if (millis() - buttonPressTime > 2000) {
      inConfig = true;
      Serial.println("Button pressed, entering config mode...");
      startCaptivePortal();
      while (true) delay(100);
    }
  } else {
    buttonPressTime = 0;
  }
}

// Modified checkThresholdAndControlRelay function
void checkThresholdAndControlRelay(float temperature, float humidity) {
  prefs.begin("thresholds", true);
  float tempMax = prefs.getFloat("tempMax", 40.0);
  float tempMin = prefs.getFloat("tempMin", 20.0);
  float humidityMax = prefs.getFloat("humidityMax", 80.0);
  float humidityMin = prefs.getFloat("humidityMin", 30.0);
  prefs.end();

  bool shouldTurnOn = false;
  bool tempAlert = false;
  bool humAlert = false;
  String alertMessage = "";

  // Check temperature
  if (temperature < tempMin) {
    Serial.println("🌡️ Temperature too low!");
    shouldTurnOn = true;
    tempAlert = true;
    alertMessage += "TEMP LOW ";
  } else if (temperature > tempMax) {
    Serial.println("🌡️ Temperature too high!");
    shouldTurnOn = true;
    tempAlert = true;
    alertMessage += "TEMP HIGH ";
  }

  // Check humidity
  if (humidity < humidityMin) {
    Serial.println("💧 Humidity too low!");
    shouldTurnOn = true;
    humAlert = true;
    alertMessage += "HUM LOW ";
  } else if (humidity > humidityMax) {
    Serial.println("💧 Humidity too high!");
    shouldTurnOn = true;
    humAlert = true;
    alertMessage += "HUM HIGH ";
  }

  // Update global alert status
  if (tempAlert || humAlert) {
    currentAlert = alertMessage.substring(0, alertMessage.length() - 1); // Remove trailing space
    hasAlert = true;
  } else {
    currentAlert = "";
    hasAlert = false;
  }

  // Control relay based on threshold check
  if (shouldTurnOn != relayStatus) {
    relayStatus = shouldTurnOn;
    digitalWrite(RELAY_PIN, relayStatus ? HIGH : LOW);
    Serial.print("🔌 Auto mode - Relay turned ");
    Serial.println(relayStatus ? "ON" : "OFF");
  }
}
