/**
 * Smart Farming IoT - ESP32 Firmware Template
 * ------------------------------------------
 * Platform: ESP32
 * Dependencies: 
 *   - ArduinoJson (by Benoit Blanchon)
 *   - WiFi.h & HTTPClient.h (Built-in ESP32)
 *
 * Description:
 * This code sends randomized sensor data to the Laravel HTTPS API
 * and receives the "pump_state" control command in the response.
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>

// --- Configuration ---
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

// Use HTTPS URL (Domain should have SSL)
const char* serverUrl = "https://your-domain.com/api/v1/telemetry";
const char* deviceCode = "YOUR_DEVICE_UUID_FROM_DASHBOARD";

// --- Sensor Variables (Placeholders) ---
float phValue = 0.0;
float soilMoisture = 0.0;
float lightIntensity = 0.0;
float temperature = 0.0;
float humidity = 0.0;

// Output Pins
const int pumpPin = 2; // Internal LED or Relay Pin

// Timing
unsigned long lastTime = 0;
unsigned long timerDelay = 5000; // 5 seconds interval

void setup() {
  Serial.begin(115200);
  pinMode(pumpPin, OUTPUT);

  WiFi.begin(ssid, password);
  Serial.println("Connecting to WiFi");
  while(WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("");
  Serial.print("Connected to WiFi network with IP Address: ");
  Serial.println(WiFi.localIP());
}

void loop() {
  // Send telemetry every 'timerDelay' milliseconds
  if ((millis() - lastTime) > timerDelay) {
    if(WiFi.status() == WL_CONNECTED) {
      
      sendTelemetry();
      
    } else {
      Serial.println("WiFi Disconnected");
    }
    lastTime = millis();
  }
}

void sendTelemetry() {
  // 1. Read / Simulate Sensor Data
  phValue = 6.0 + (random(-10, 10) / 10.0);
  soilMoisture = random(40, 90);
  lightIntensity = random(300, 1200);
  temperature = 25.0 + (random(-20, 20) / 10.0);
  humidity = random(50, 80);

  Serial.println("--- Sending Data ---");

  // 2. Prepare JSON Payload
  StaticJsonDocument<256> doc;
  doc["ph"] = phValue;
  doc["soil_moisture"] = soilMoisture;
  doc["light_intensity"] = lightIntensity;
  doc["temperature"] = temperature;
  doc["humidity"] = humidity;

  String jsonPayload;
  serializeJson(doc, jsonPayload);

  // 3. Initialize HTTPS Client
  WiFiClientSecure *client = new WiFiClientSecure;
  if(client) {
    // Note: If you have issues with SSL, you might need to setInsecure() 
    // OR provide the Root CA Certificate.
    client->setInsecure(); // For testing, bypass SSL Cert verification

    HTTPClient https;
    
    if (https.begin(*client, serverUrl)) {
      https.addHeader("Content-Type", "application/json");
      https.addHeader("Accept", "application/json");
      https.addHeader("X-Device-Code", deviceCode);

      int httpResponseCode = https.POST(jsonPayload);

      if (httpResponseCode > 0) {
        String response = https.getString();
        Serial.print("HTTP Response code: ");
        Serial.println(httpResponseCode);
        
        // 4. Parse Response to get Command (Pump State)
        StaticJsonDocument<512> resDoc;
        DeserializationError error = deserializeJson(resDoc, response);

        if (!error) {
          if (resDoc.containsKey("device_controls")) {
            bool pumpState = resDoc["device_controls"]["pump_state"];
            
            if (pumpState) {
              digitalWrite(pumpPin, HIGH);
              Serial.println("COMMAND: PUMP ON");
            } else {
              digitalWrite(pumpPin, LOW);
              Serial.println("COMMAND: PUMP OFF");
            }
          }
        }
      } else {
        Serial.print("Error code: ");
        Serial.println(httpResponseCode);
      }
      https.end();
    }
    delete client;
  }
}
