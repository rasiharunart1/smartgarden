/**
 * Smart Farming IoT - ESP32 Firmware (FULL SENSOR VERSION)
 * -------------------------------------------------------
 * Platform: ESP32
 *
 * Required Libraries (Install via Library Manager):
 *   - DHT sensor library (by Adafruit)
 *   - BH1750 (by Christopher Laws)
 *   - ArduinoJson (by Benoit Blanchon)
 *
 * Sensors Integrated:
 *   1. DHT22 (Air Temp & Humidity)
 *   2. BH1750 GY-302 (Light Intensity via I2C)
 *   3. Soil Moisture Sensor (Analog)
 *   4. pH Sensor (Analog)
 */

#include <ArduinoJson.h>
#include <BH1750.h>
#include <DHT.h>
#include <HTTPClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <Wire.h>


// --- Configuration ---
const char *ssid = "YOUR_WIFI_SSID";
const char *password = "YOUR_WIFI_PASSWORD";
const char *serverUrl = "https://your-domain.com/api/v1/telemetry";
const char *deviceCode = "YOUR_DEVICE_UUID_FROM_DASHBOARD";

// --- Pin Definitions ---
#define DHTPIN 4      // Digital pin connected to the DHT22
#define DHTTYPE DHT22 // DHT 22 (AM2302)
#define SOIL_PIN 34   // Analog pin for Soil Moisture (ADC1_CH6)
#define PH_PIN 35     // Analog pin for pH Sensor (ADC1_CH7)
#define PUMP_PIN 2    // Relay/Pump Control Pin

// --- Objects ---
DHT dht(DHTPIN, DHTTYPE);
BH1750 lightMeter;
WiFiClientSecure client;

// --- Timing ---
unsigned long lastTime = 0;
unsigned long timerDelay = 10000; // Send telemetry every 10 seconds

void setup() {
  Serial.begin(115200);

  pinMode(PUMP_PIN, OUTPUT);
  digitalWrite(PUMP_PIN, LOW);

  // Initialize Sensors
  dht.begin();
  Wire.begin(); // Standard I2C Pins: SDA=21, SCL=22

  if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
    Serial.println(F("BH1750 initialized"));
  } else {
    Serial.println(F("Error initializing BH1750"));
  }

  // Connect WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nConnected!");

  // Security: Handle SSL
  client.setInsecure(); // Bypass certificate verification for testing
}

void loop() {
  if ((millis() - lastTime) > timerDelay) {
    if (WiFi.status() == WL_CONNECTED) {
      sendData();
    }
    lastTime = millis();
  }
}

void sendData() {
  // 1. READ SENSORS
  float humAir = dht.readHumidity();
  float tempAir = dht.readTemperature();
  float lux = lightMeter.readLightLevel();

  // Soil Moisture (Map 12-bit ADC 0-4095 to 0-100%)
  int rawSoil = analogRead(SOIL_PIN);
  float soilMoist =
      map(rawSoil, 4095, 1000, 0, 100); // Calibration needed based on sensor
  if (soilMoist < 0)
    soilMoist = 0;
  if (soilMoist > 100)
    soilMoist = 100;

  // pH Sensor (Simple Linear Calculation - Calibration REQUIRED)
  int rawPh = analogRead(PH_PIN);
  float voltage = rawPh * (3.3 / 4095.0);
  float phVal = 3.5 * voltage; // Example conversion formula

  // Check if any reads failed
  if (isnan(humAir) || isnan(tempAir)) {
    Serial.println(F("Failed to read from DHT sensor!"));
    return;
  }

  Serial.println("--- Telemetry Report ---");
  Serial.printf("Temp: %.2fC | Hum: %.2f%% | Light: %.2f lux\n", tempAir,
                humAir, lux);
  Serial.printf("Soil: %.2f%% | pH: %.2f\n", soilMoist, phVal);

  // 2. PREPARE JSON
  StaticJsonDocument<256> doc;
  doc["ph"] = phVal;
  doc["soil_moisture"] = soilMoist;
  doc["light_intensity"] = lux;
  doc["temperature"] = tempAir;
  doc["humidity"] = humAir;

  String jsonPayload;
  serializeJson(doc, jsonPayload);

  // 3. HTTP POST
  HTTPClient https;
  if (https.begin(client, serverUrl)) {
    https.addHeader("Content-Type", "application/json");
    https.addHeader("X-Device-Code", deviceCode);

    int httpCode = https.POST(jsonPayload);

    if (httpCode > 0) {
      String response = https.getString();
      Serial.print("Server Response: ");
      Serial.println(httpCode);

      // 4. PARSE PUMP COMMAND
      StaticJsonDocument<512> resDoc;
      if (!deserializeJson(resDoc, response)) {
        if (resDoc.containsKey("device_controls")) {
          bool pumpState = resDoc["device_controls"]["pump_state"];
          digitalWrite(PUMP_PIN, pumpState ? HIGH : LOW);
          Serial.printf("PUMP COMMAND: %s\n", pumpState ? "ON" : "OFF");
        }
      }
    } else {
      Serial.printf("POST Gagal, error: %s\n",
                    https.errorToString(httpCode).c_str());
    }
    https.end();
  }
}
