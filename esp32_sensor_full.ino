/**
 * ============================================================
 *  SENA Smart Garden - ESP32 Firmware (FULL + FUZZY LOGIC)
 * ============================================================
 *  Platform  : ESP32 (Arduino Framework)
 *  Author    : SENA IoT Team
 *
 *  Required Libraries (Install via Arduino Library Manager):
 *    - DHT sensor library       (by Adafruit)
 *    - Adafruit Unified Sensor  (by Adafruit) [dependency DHT]
 *    - LiquidCrystal I2C        (by Frank de Brabander)
 *    - ArduinoJson              (by Benoit Blanchon)
 *    - WiFi.h, HTTPClient.h, WiFiClientSecure.h  [Built-in ESP32]
 *
 *  Sensors:
 *    1. DHT22  – Suhu & Kelembaban Udara      (Pin digital 18)
 *    2. LDR    – Intensitas Cahaya (Analog)   (Pin ADC D32)
 *    3. Soil Moisture Sensor (Analog)         (Pin ADC D35)
 *    4. pH Tanah DMS (Depo Inovasi)           (ADC: D34, DMS: D13)
 *
 *  Display:
 *    - LCD 20x4 I2C (PCF8574)                 (SDA=21, SCL=22, Addr=0x27)
 *
 *  Aktuator:
 *    - Relay Pompa Air                        (Pin digital 33)
 *
 *  Fitur:
 *    - Fuzzy Logic lokal (Mamdani) untuk kontrol pompa mandiri
 *    - Sinkronisasi konfigurasi fuzzy dari server via MQTT (topic sena/rx/<code>)
 *    - Kirim telemetri ke API SENA setiap interval
 *    - Menerima perintah pump_state dari server (override manual)
 *    - Reconnect WiFi otomatis (non-blocking)
 *
 *  API Endpoint : POST https://<domain>/api/v1/telemetry
 *  Header       : X-Device-Code: <device_code>
 * ============================================================
 */

// ─── LIBRARY INCLUDES ────────────────────────────────────────
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>
#include <ArduinoJson.h>

// ─── USER CONFIGURATION ──────────────────────────────────────
// !! WAJIB DIISI SEBELUM UPLOAD !!
const char* WIFI_SSID       = "smartbrankas";
const char* WIFI_PASSWORD   = "smartbrankas321";
const char* SERVER_URL      = "https://moodle.mdpower.io/api/v1/telemetry";
const char* COMMAND_URL     = "https://moodle.mdpower.io/api/v1/device-commands";
const char* DEVICE_CODE     = "7d0857d9-7b2e-4ceb-a52c-b4f1edb954a4";

// Interval pengiriman telemetri (ms)
// NOTE: Pembacaan pH DMS memakan ~13 detik (blocking), jadi interval efektif ≈ 30 detik
const unsigned long TELEMETRY_INTERVAL = 5000; // 30 detik (kompensasi waktu baca pH DMS)

// Interval cek command dari server (ms)
const unsigned long COMMAND_CHECK_INTERVAL = 5000; // 5 detik

// ─── PIN DEFINITIONS ─────────────────────────────────────────
#define DHTPIN        18     // DHT22 data pin
#define DHTTYPE       DHT22
#define LDR_PIN       32    // ADC1_CH4 – LDR analog (D32)
#define SOIL_PIN      35    // ADC1_CH7 – Soil Moisture analog (D35)
#define PH_PIN        34    // ADC1_CH6 – pH Sensor ADC input (D34)
#define DMS_PIN       13    // DMS control output pin (D13) – trigger pH sensor
#define PUMP_PIN      33     // Relay pump (HIGH = ON)

// LCD I2C – 20x4
#define LCD_ADDR      0x27  // Coba 0x3F jika 0x27 tidak bekerja
#define LCD_COLS      20    // LCD 20 kolom
#define LCD_ROWS      4     // LCD 4 baris

// ─── SENSOR CALIBRATION ──────────────────────────────────────
// Soil Moisture
#define SOIL_DRY_ADC    3200  // ADC di udara (kering)
#define SOIL_WET_ADC     900  // ADC saat terendam air (basah)

// pH Sensor DMS (Depo Inovasi)
// Rumus regresi linier hasil kalibrasi: pH = (SLOPE * ADC) + INTERCEPT
// !! Ganti rumus ini sesuai hasil kalibrasi sensor Anda !!
#define PH_DMS_SLOPE      -0.0386f  // Slope regresi linier
#define PH_DMS_INTERCEPT  16.030f   // Intercept regresi linier
#define PH_DMS_WAIT_MS    10000     // Waktu tunggu DMS capture data (ms)
#define PH_DMS_READY_MS   3000      // Waktu tunggu DMS ready setelah baca (ms)

// LDR Cahaya: mapping ADC → lux perkiraan
// ADC tinggi = gelap, ADC rendah = terang
#define LDR_DARK_ADC    4000  // ADC saat gelap total
#define LDR_BRIGHT_ADC   200  // ADC saat sangat terang
#define LDR_MAX_LUX    1000  // Lux maksimum yang di-mapping

// ─── FUZZY LOGIC PARAMETERS ──────────────────────────────────
// Default parameter – akan di-update dari server via response/MQTT
struct FuzzyConfig {
    // Kelembapan Tanah (Soil Moisture %)
    float moisture_kering_max  = 40.0; // Batas atas zona "Kering"
    float moisture_normal_min  = 30.0; // Batas bawah zona "Normal"
    float moisture_normal_max  = 70.0; // Batas atas zona "Normal"
    float moisture_basah_min   = 60.0; // Batas bawah zona "Basah"
    // pH Tanah
    float ph_asam_max          = 6.5;  // Batas atas zona "Asam"
    float ph_netral_min        = 6.0;  // Batas bawah zona "Netral"
    float ph_netral_max        = 7.5;  // Batas atas zona "Netral"
    float ph_basa_min          = 7.0;  // Batas bawah zona "Basa"
} fuzzy;

// ─── OBJECTS ─────────────────────────────────────────────────
DHT dht(DHTPIN, DHTTYPE);
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
WiFiClientSecure wifiClient;

// ─── STATE VARIABLES ─────────────────────────────────────────
unsigned long lastTelemetryTime = 0;
unsigned long lastCommandCheckTime = 0;

// Pump Control State Machine
// Mode: 0 = Fuzzy Logic, 1 = Server Override (Manual)
uint8_t pumpControlMode = 0;
bool fuzzyPumpDecision = false;      // Output dari fuzzy logic
bool serverPumpCommand = false;      // Perintah terbaru dari server
bool actualPumpState = false;        // State sebenarnya di relay
unsigned long serverCommandTimestamp = 0;
const unsigned long OVERRIDE_TIMEOUT = 300000; // 5 menit, kembali ke fuzzy

// ─── GLOBAL SENSOR VALUES (untuk LCD) ───────────────────────
float g_temp = 0, g_humid = 0, g_soil = 0, g_ph = 0, g_lux = 0;

// ─── FORWARD DECLARATIONS ────────────────────────────────────
float readSoilMoisture();
float readPH();
float readLDR();
void  updateLCD();
float fuzzyMembership_Kering(float m);
float fuzzyMembership_Normal(float m);
float fuzzyMembership_Basah(float m);
float fuzzyMembership_PHAsam(float ph);
float fuzzyMembership_PHNetral(float ph);
float fuzzyMembership_PHBasa(float ph);
float runFuzzyLogic(float soilMoisture, float phValue);
void  controlPump(bool state);
void  sendTelemetry();
void  checkServerCommands();
void  reconnectWiFi();

// ============================================================
//  SETUP
// ============================================================
void setup() {
    Serial.begin(115200);
    delay(500);
    Serial.println();
    Serial.println("╔══════════════════════════════════════╗");
    Serial.println("║  SENA Smart Garden - ESP32 Firmware  ║");
    Serial.println("╚══════════════════════════════════════╝");

    // Inisialisasi pin
    pinMode(PUMP_PIN, OUTPUT);
    pinMode(LDR_PIN, INPUT);
    pinMode(PH_PIN, INPUT);       // pH ADC input (D34)
    // pinMode(DMS_PIN, OUTPUT);     // DMS trigger output (D25)
    // digitalWrite(DMS_PIN, HIGH);  // DMS non-aktif saat startup (HIGH = idle)
    controlPump(false); // Pompa mati saat startup

    // Inisialisasi sensor DHT22
    dht.begin();

    // Inisialisasi I2C & LCD 20x4
    Wire.begin(); // SDA=21, SCL=22 (default ESP32)
    lcd.init();
    lcd.backlight();

    // Tampilan startup LCD 20x4
    // Baris 0: "====================  " (garis atas)
    // Baris 1: "   SENA Smart Garden"
    // Baris 2: "     Initializing...  "
    // Baris 3: "====================  " (garis bawah)
    lcd.setCursor(0, 0);
    lcd.print("====================");
    lcd.setCursor(0, 1);
    lcd.print("   SENA SmartGarden ");
    lcd.setCursor(0, 2);
    lcd.print("   Initializing...  ");
    lcd.setCursor(0, 3);
    lcd.print("====================");

    Serial.println("[OK] LCD 20x4 I2C initialized");

    // Koneksi WiFi
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("[WiFi] Menghubungkan ke: ");
    Serial.println(WIFI_SSID);

    int attempt = 0;
    while (WiFi.status() != WL_CONNECTED && attempt < 30) {
        delay(500);
        Serial.print(".");
        attempt++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println();
        Serial.print("[WiFi] Terhubung! IP: ");
        Serial.println(WiFi.localIP());

        // Tampilkan IP di LCD baris ke-2 setelah konek
        lcd.setCursor(0, 2);
        lcd.print("IP:                 ");
        lcd.setCursor(3, 2);
        lcd.print(WiFi.localIP());
    } else {
        Serial.println("\n[WiFi] GAGAL terhubung. Akan retry di loop...");
        lcd.setCursor(0, 2);
        lcd.print("WiFi: GAGAL konek  ");
    }

    // SSL: Bypass verifikasi sertifikat (untuk development)
    wifiClient.setInsecure();
}

// ============================================================
//  LOOP
// ============================================================
void loop() {
    // Auto-reconnect WiFi jika terputus
    reconnectWiFi();

    // Update tampilan LCD (non-blocking, ganti layar tiap 3 detik)
    updateLCD();

    // Check & apply override timeout
    if (pumpControlMode == 1) { // Server Override active
        if (millis() - serverCommandTimestamp > OVERRIDE_TIMEOUT) {
            Serial.println("[PUMP] Server override timeout! Kembali ke Fuzzy Logic mode");
            pumpControlMode = 0;
        }
    }

    // Check server commands sesuai interval (non-blocking HTTP GET)
    if (millis() - lastCommandCheckTime >= COMMAND_CHECK_INTERVAL) {
        lastCommandCheckTime = millis();
        if (WiFi.status() == WL_CONNECTED) {
            checkServerCommands();
        }
    }

    // Kirim telemetri sesuai interval
    if (millis() - lastTelemetryTime >= TELEMETRY_INTERVAL) {
        lastTelemetryTime = millis();
        if (WiFi.status() == WL_CONNECTED) {
            sendTelemetry();
        } else {
            // Tetap jalankan fuzzy lokal meski WiFi mati (pompa tetap bisa bekerja)
            float soil = readSoilMoisture();
            float ph   = readPH();
            float out  = runFuzzyLogic(soil, ph);
            fuzzyPumpDecision = (out > 0.5f);

            // Jika no server override, gunakan fuzzy
            if (pumpControlMode == 0) {
                controlPump(fuzzyPumpDecision);
            }
        }
    }
}

// ============================================================
//  SENSOR READINGS
// ============================================================
/**
 * Baca kelembapan tanah dan konversi ke persen (0–100%)
 */
float readSoilMoisture() {
    long sum = 0;
    for (int i = 0; i < 10; i++) {
        sum += analogRead(SOIL_PIN);
        delay(5);
    }
    int rawADC = sum / 10;
    float moisture = map(rawADC, SOIL_DRY_ADC, SOIL_WET_ADC, 0, 100);
    moisture = constrain(moisture, 0.0f, 100.0f);
    return moisture;
}

/**
 * Baca nilai pH dari sensor DMS (Depo Inovasi).
 * PERHATIAN: Fungsi ini BLOCKING selama ~13 detik!
 */
float readPH() {
    // analogReadResolution(10);
    // digitalWrite(DMS_PIN, LOW);
    // Serial.println("[pH DMS] Mengaktifkan sensor, menunggu capture...");
    // delay(PH_DMS_WAIT_MS);
    analogReadResolution(12); 

    int sensorValue = analogRead(PH_PIN);

  // Konversi nilai analog menjadi tegangan
  float voltage = sensorValue * (3.3 / 4095.0); // Tegangan referensi ESP32

  // Konversi tegangan menjadi nilai pH (sesuai datasheet sensor)
  float phValue = 7 + ((2.5 - voltage) / 0.18); // Formula bisa berbeda tergantung sensor yang digunakan

  // Tampilkan nilai pH ke Serial Monitor
  Serial.print("Nilai pH tanah: ");
  Serial.println(phValue);

    // digitalWrite(DMS_PIN, HIGH);
    // analogReadResolution(12);
    // delay(PH_DMS_READY_MS);

    // if (phValue < 0) {
    //     Serial.printf("[pH DMS] Sensor tidak stabil! ADC: %d → pH: %.2f (set -1)\n", rawADC, phValue);
    //     phValue = -1.0f;
    // } else {
    //     phValue = constrain(phValue, 0.0f, 14.0f);
    //     Serial.printf("[pH DMS] ADC(10bit): %d → pH: %.2f\n", rawADC, phValue);
    // }
    return phValue;
}

// ============================================================
//  FUZZY LOGIC ENGINE (Mamdani – Inference)
// ============================================================
float fuzzyMembership_Kering(float m) {
    float peak = fuzzy.moisture_kering_max - 10.0f;
    if (m <= peak) return 1.0f;
    if (m >= fuzzy.moisture_kering_max) return 0.0f;
    return (fuzzy.moisture_kering_max - m) / (fuzzy.moisture_kering_max - peak);
}

float fuzzyMembership_Normal(float m) {
    float center = (fuzzy.moisture_normal_min + fuzzy.moisture_normal_max) / 2.0f;
    if (m <= fuzzy.moisture_normal_min || m >= fuzzy.moisture_normal_max) return 0.0f;
    if (m <= center) return (m - fuzzy.moisture_normal_min) / (center - fuzzy.moisture_normal_min);
    return (fuzzy.moisture_normal_max - m) / (fuzzy.moisture_normal_max - center);
}

float fuzzyMembership_Basah(float m) {
    float peak = fuzzy.moisture_basah_min + 10.0f;
    if (m >= peak) return 1.0f;
    if (m <= fuzzy.moisture_basah_min) return 0.0f;
    return (m - fuzzy.moisture_basah_min) / (peak - fuzzy.moisture_basah_min);
}

float fuzzyMembership_PHAsam(float ph) {
    float peak = fuzzy.ph_asam_max - 1.0f;
    if (ph <= peak) return 1.0f;
    if (ph >= fuzzy.ph_asam_max) return 0.0f;
    return (fuzzy.ph_asam_max - ph) / (fuzzy.ph_asam_max - peak);
}

float fuzzyMembership_PHNetral(float ph) {
    float center = (fuzzy.ph_netral_min + fuzzy.ph_netral_max) / 2.0f;
    if (ph <= fuzzy.ph_netral_min || ph >= fuzzy.ph_netral_max) return 0.0f;
    if (ph <= center) return (ph - fuzzy.ph_netral_min) / (center - fuzzy.ph_netral_min);
    return (fuzzy.ph_netral_max - ph) / (fuzzy.ph_netral_max - center);
}

float fuzzyMembership_PHBasa(float ph) {
    float peak = fuzzy.ph_basa_min + 1.0f;
    if (ph >= peak) return 1.0f;
    if (ph <= fuzzy.ph_basa_min) return 0.0f;
    return (ph - fuzzy.ph_basa_min) / (peak - fuzzy.ph_basa_min);
}

/**
 * Rule Base (Mamdani):
 * R1: Kering  + Netral → TINGGI (1.0)
 * R2: Kering  + Asam   → SEDANG (0.7)
 * R3: Kering  + Basa   → SEDANG (0.7)
 * R4: Normal  + Netral → RENDAH (0.2)
 * R5: Normal  + Asam   → SEDANG (0.5)
 * R6: Normal  + Basa   → SEDANG (0.5)
 * R7: Basah   + Netral → MATI   (0.0)
 * R8: Basah   + Asam   → MATI   (0.0)
 * R9: Basah   + Basa   → MATI   (0.0)
 */
float runFuzzyLogic(float soilMoisture, float phValue) {
    float mKering = fuzzyMembership_Kering(soilMoisture);
    float mNormal = fuzzyMembership_Normal(soilMoisture);
    float mBasah  = fuzzyMembership_Basah(soilMoisture);
    float pAsam   = fuzzyMembership_PHAsam(phValue);
    float pNetral = fuzzyMembership_PHNetral(phValue);
    float pBasa   = fuzzyMembership_PHBasa(phValue);

    float r1 = min(mKering, pNetral);
    float r2 = min(mKering, pAsam);
    float r3 = min(mKering, pBasa);
    float r4 = min(mNormal, pNetral);
    float r5 = min(mNormal, pAsam);
    float r6 = min(mNormal, pBasa);
    float r7 = min(mBasah,  pNetral);
    float r8 = min(mBasah,  pAsam);
    float r9 = min(mBasah,  pBasa);

    const float OUT_TINGGI = 1.0f;
    const float OUT_SEDANG = 0.7f;
    const float OUT_RENDAH = 0.2f;
    const float OUT_MATI   = 0.0f;

    float numerator   = (r1 * OUT_TINGGI)
                      + (r2 * OUT_SEDANG)
                      + (r3 * OUT_SEDANG)
                      + (r4 * OUT_RENDAH)
                      + (r5 * OUT_SEDANG)
                      + (r6 * OUT_SEDANG)
                      + (r7 * OUT_MATI)
                      + (r8 * OUT_MATI)
                      + (r9 * OUT_MATI);
    float denominator = r1 + r2 + r3 + r4 + r5 + r6 + r7 + r8 + r9;

    if (denominator == 0.0f) return 0.0f;
    float crispOutput = numerator / denominator;

    Serial.printf("[Fuzzy] Membership → Kering:%.2f Normal:%.2f Basah:%.2f | pAsam:%.2f pNetral:%.2f pBasa:%.2f\n",
                  mKering, mNormal, mBasah, pAsam, pNetral, pBasa);
    Serial.printf("[Fuzzy] Rules → R1:%.2f R2:%.2f R3:%.2f R4:%.2f R5:%.2f R6:%.2f\n",
                  r1, r2, r3, r4, r5, r6);
    Serial.printf("[Fuzzy] Output (crisp): %.3f → Pompa: %s\n",
                  crispOutput, crispOutput > 0.5f ? "ON" : "OFF");
    return crispOutput;
}

// ============================================================
//  PUMP CONTROL
// ============================================================
void controlPump(bool state) {
    digitalWrite(PUMP_PIN, state ? LOW : HIGH);
    actualPumpState = state;
    Serial.printf("[PUMP] State → %s (Mode: %s)\n",
                  state ? "ON ✓" : "OFF ✗",
                  pumpControlMode == 0 ? "FUZZY" : "MANUAL OVERRIDE");
}

// ============================================================
//  MAIN TELEMETRY FUNCTION
// ============================================================
void sendTelemetry() {
    Serial.println("\n══════════════════════════════════════");
    Serial.println(" SENA - Cycle Telemetri");
    Serial.println("══════════════════════════════════════");

    float tempC    = dht.readTemperature();
    float humidAir = dht.readHumidity();
    if (isnan(tempC) || isnan(humidAir)) {
        Serial.println("[ERROR] Gagal membaca DHT22!");
        tempC    = -99.0f;
        humidAir = -99.0f;
    }

    float lux       = readLDR();
    float soilMoist = readSoilMoisture();
    float phVal     = readPH();

    g_temp = tempC; g_humid = humidAir;
    g_soil = soilMoist; g_ph = phVal; g_lux = lux;

    
    Serial.printf("[DHT22]  Suhu: %.2f°C | Kelembaban Udara: %.2f%%\n", tempC, humidAir);
    Serial.printf("[LDR]    Cahaya: %.2f lux\n", lux);
    Serial.printf("[Soil]   Kelembaban Tanah: %.2f%%\n", soilMoist);
    Serial.printf("[pH]     Nilai pH: %.2f\n", phVal);

    float fuzzyOutput = runFuzzyLogic(soilMoist, phVal);
    fuzzyPumpDecision = (fuzzyOutput > 0.5f);

    bool desiredPumpState;
    if (pumpControlMode == 0) {
        desiredPumpState = fuzzyPumpDecision;
    } else {
        desiredPumpState = serverPumpCommand;
    }
    controlPump(desiredPumpState);

    StaticJsonDocument<256> doc;
    doc["ph"]              = round(phVal * 100.0f) / 100.0f;
    doc["soil_moisture"]   = round(soilMoist * 10.0f) / 10.0f;
    doc["light_intensity"] = round(lux * 10.0f) / 10.0f;
    doc["temperature"]     = round(tempC * 10.0f) / 10.0f;
    doc["humidity"]        = round(humidAir * 10.0f) / 10.0f;
    String jsonPayload;
    serializeJson(doc, jsonPayload);
    Serial.printf("[HTTP]  Mengirim payload: %s\n", jsonPayload.c_str());

    HTTPClient https;
    if (!https.begin(wifiClient, SERVER_URL)) {
        Serial.println("[HTTP]  Gagal membuka koneksi!");
        return;
    }
    https.addHeader("Content-Type", "application/json");
    https.addHeader("Accept", "application/json");
    https.addHeader("X-Device-Code", DEVICE_CODE);
    https.setTimeout(8000);

    int httpCode = https.POST(jsonPayload);
    if (httpCode > 0) {
        String response = https.getString();
        Serial.printf("[HTTP]  Response Code: %d\n", httpCode);

        StaticJsonDocument<512> resDoc;
        DeserializationError err = deserializeJson(resDoc, response);
        if (!err) {
            if (resDoc.containsKey("fuzzy_config")) {
                JsonObject cfg = resDoc["fuzzy_config"];
                if (cfg.containsKey("moisture_kering_max")) fuzzy.moisture_kering_max = cfg["moisture_kering_max"];
                if (cfg.containsKey("moisture_normal_min")) fuzzy.moisture_normal_min = cfg["moisture_normal_min"];
                if (cfg.containsKey("moisture_normal_max")) fuzzy.moisture_normal_max = cfg["moisture_normal_max"];
                if (cfg.containsKey("moisture_basah_min"))  fuzzy.moisture_basah_min  = cfg["moisture_basah_min"];
                if (cfg.containsKey("ph_asam_max"))         fuzzy.ph_asam_max         = cfg["ph_asam_max"];
                if (cfg.containsKey("ph_netral_min"))       fuzzy.ph_netral_min       = cfg["ph_netral_min"];
                if (cfg.containsKey("ph_netral_max"))       fuzzy.ph_netral_max       = cfg["ph_netral_max"];
                if (cfg.containsKey("ph_basa_min"))         fuzzy.ph_basa_min         = cfg["ph_basa_min"];
                Serial.println("[Fuzzy] Konfigurasi diperbarui dari server!");
            }
            if (resDoc.containsKey("device_controls")) {
                bool srvPump = resDoc["device_controls"]["pump_state"] | false;
                if (srvPump != fuzzyPumpDecision) {
                    pumpControlMode = 1;
                    serverPumpCommand = srvPump;
                    serverCommandTimestamp = millis();
                    Serial.printf("[SERVER] Override pompa dari dashboard → %s (valid 5 menit)\n",
                                  srvPump ? "ON" : "OFF");
                } else {
                    pumpControlMode = 0;
                    Serial.println("[SERVER] Sesuai dengan fuzzy decision, tetap mode FUZZY");
                }
            }
        } else {
            Serial.printf("[HTTP]  Gagal parse JSON response: %s\n", err.c_str());
        }
    } else {
        Serial.printf("[HTTP]  POST gagal! Error: %s\n", https.errorToString(httpCode).c_str());
    }
    https.end();

    Serial.printf("[STATUS] Pompa: %s | Mode: %s | Fuzzy Score: %.3f\n",
                  actualPumpState ? "ON" : "OFF",
                  pumpControlMode == 0 ? "FUZZY" : "OVERRIDE",
                  fuzzyOutput);
    Serial.println("══════════════════════════════════════\n");
}

// ============================================================
//  LDR – BACA INTENSITAS CAHAYA
// ============================================================
float readLDR() {
    long sum = 0;
    for (int i = 0; i < 10; i++) {
        sum += analogRead(LDR_PIN);
        delay(3);
    }
    int rawADC = sum / 10;
    float lux = map(rawADC, LDR_BRIGHT_ADC, LDR_DARK_ADC, LDR_MAX_LUX, 0);
    lux = constrain(lux, 0.0f, (float)LDR_MAX_LUX);
    return lux;
}

// ============================================================
//  LCD 20x4 I2C – UPDATE DISPLAY (Non-Blocking)
// ============================================================
/**
 * Layout LCD 20x4 – bergantian setiap 3 detik:
 *
 * ── Layar 0: Suhu & Kelembaban ──────────────────────────────
 *   Baris 0: "SENA Smart Garden   "
 *   Baris 1: "Suhu : XX.X C       "
 *   Baris 2: "Hum  : XX.X %       "
 *   Baris 3: "Cahaya: XXXX lux    "
 *
 * ── Layar 1: Tanah & pH ─────────────────────────────────────
 *   Baris 0: "SENA Smart Garden   "
 *   Baris 1: "Tanah: XX.X %       "
 *   Baris 2: "pH   : XX.XX        "
 *   Baris 3: "Pompa: ON/OFF [F/M] "
 *
 * ── Layar 2: Status WiFi & Mode ─────────────────────────────
 *   Baris 0: "==== STATUS ========"
 *   Baris 1: "WiFi : OK/--        "
 *   Baris 2: "Mode : FUZZY/MANUAL "
 *   Baris 3: "Pompa: ON/OFF       "
 */
void updateLCD() {
    static unsigned long lastSwitch = 0;
    static uint8_t screen = 0;
    if (millis() - lastSwitch < 3000) return;
    lastSwitch = millis();

    lcd.clear();

    switch (screen) {
        // ── Layar 0: Sensor Iklim ──────────────────────────────
        case 0:
            // Baris 0: judul
            lcd.setCursor(0, 0);
            lcd.print("SENA Smart Garden   ");

            // Baris 1: Suhu
            lcd.setCursor(0, 1);
            lcd.print("Suhu : ");
            if (g_temp <= -99.0f) {
                lcd.print("ERROR       ");
            } else {
                lcd.print(g_temp, 1);
                lcd.print((char)223); // simbol derajat °
                lcd.print("C       ");
            }

            // Baris 2: Kelembaban Udara
            lcd.setCursor(0, 2);
            lcd.print("Hum  : ");
            if (g_humid <= -99.0f) {
                lcd.print("ERROR       ");
            } else {
                lcd.print(g_humid, 1);
                lcd.print("%        ");
            }

            // Baris 3: Cahaya
            lcd.setCursor(0, 3);
            lcd.print("Cahaya: ");
            lcd.print(g_lux, 0);
            lcd.print(" lux      ");
            break;

        // ── Layar 1: Sensor Tanah & Kontrol Pompa ─────────────
        case 1:
            // Baris 0: judul
            lcd.setCursor(0, 0);
            lcd.print("SENA Smart Garden   ");

            // Baris 1: Kelembaban Tanah
            lcd.setCursor(0, 1);
            lcd.print("Tanah: ");
            lcd.print(g_soil, 1);
            lcd.print("%          ");

            // Baris 2: pH
            lcd.setCursor(0, 2);
            lcd.print("pH   : ");
            if (g_ph < 0) {
                lcd.print("ERROR       ");
            } else {
                lcd.print(g_ph, 2);
                lcd.print("          ");
            }

            // Baris 3: Status Pompa + Mode
            lcd.setCursor(0, 3);
            lcd.print("Pompa: ");
            lcd.print(actualPumpState ? "ON " : "OFF");
            lcd.print(" [");
            lcd.print(pumpControlMode == 0 ? "FUZZY " : "MANUAL");
            lcd.print("]  ");
            break;

        // ── Layar 2: Status Sistem ─────────────────────────────
        case 2: {
            // Baris 0: header
            lcd.setCursor(0, 0);
            lcd.print("==== STATUS ========");

            // Baris 1: Status WiFi + IP singkat
            lcd.setCursor(0, 1);
            bool wifiOk = (WiFi.status() == WL_CONNECTED);
            if (wifiOk) {
                lcd.print("WiFi : OK           ");
            } else {
                lcd.print("WiFi : PUTUS        ");
            }

            // Baris 2: Mode kontrol
            lcd.setCursor(0, 2);
            if (pumpControlMode == 0) {
                lcd.print("Mode : FUZZY LOGIC  ");
            } else {
                lcd.print("Mode : MANUAL OVRD  ");
            }

            // Baris 3: Status Pompa
            lcd.setCursor(0, 3);
            lcd.print("Pompa: ");
            lcd.print(actualPumpState ? "ON  " : "OFF ");
            lcd.print("               ");
            break;
        }
    }

    screen = (screen + 1) % 3; // 3 layar (0,1,2)
}

// ============================================================
//  WIFI AUTO RECONNECT (Non-Blocking)
// ============================================================
void reconnectWiFi() {
    static unsigned long lastWiFiCheck = 0;
    if (millis() - lastWiFiCheck < 5000) return;
    lastWiFiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[WiFi] Koneksi terputus, mencoba reconnect...");
        WiFi.disconnect();
        WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    }
}

// ============================================================
//  CHECK SERVER COMMANDS (HTTP Polling)
// ============================================================
/**
 * Polling endpoint untuk cek apakah ada command dari server.
 * Endpoint: GET /api/v1/device-commands/{device_code}
 *
 * Response:
 * {
 *   "has_command": true,
 *   "command": {
 *     "type": "pump_toggle",
 *     "pump_state": true
 *   }
 * }
 */
void checkServerCommands() {
    String commandCheckURL = String(COMMAND_URL) + "/" + DEVICE_CODE;

    HTTPClient https;
    if (!https.begin(wifiClient, commandCheckURL)) {
        Serial.println("[Command] Gagal buka koneksi");
        return;
    }
    https.addHeader("Content-Type", "application/json");
    https.addHeader("Accept", "application/json");
    https.addHeader("X-Device-Code", DEVICE_CODE);
    https.setTimeout(5000);

    int httpCode = https.GET();
    if (httpCode == 200) {
        String response = https.getString();
        Serial.printf("[Command] Response: %s\n", response.c_str());

        StaticJsonDocument<256> doc;
        DeserializationError err = deserializeJson(doc, response);
        if (!err && doc["has_command"]) {
            JsonObject cmd = doc["command"];
            String cmdType = cmd["type"];

            // ── Handle: Pump Toggle ──────────────────────────────
            if (cmdType == "pump_toggle") {
                bool newPumpState = cmd["pump_state"] | false;
                pumpControlMode = 1; // MANUAL OVERRIDE
                serverPumpCommand = newPumpState;
                serverCommandTimestamp = millis();
                controlPump(newPumpState);
                Serial.printf("[Command] Pompa override → %s (valid 5 menit)\n",
                              newPumpState ? "ON" : "OFF");
            }
            // ── Handle: Fuzzy Config Update ──────────────────────
            else if (cmdType == "update_fuzzy") {
                JsonObject cfg = cmd["fuzzy_config"];
                if (cfg.containsKey("moisture_kering_max")) fuzzy.moisture_kering_max = cfg["moisture_kering_max"];
                if (cfg.containsKey("moisture_normal_min")) fuzzy.moisture_normal_min = cfg["moisture_normal_min"];
                if (cfg.containsKey("moisture_normal_max")) fuzzy.moisture_normal_max = cfg["moisture_normal_max"];
                if (cfg.containsKey("moisture_basah_min"))  fuzzy.moisture_basah_min  = cfg["moisture_basah_min"];
                if (cfg.containsKey("ph_asam_max"))         fuzzy.ph_asam_max         = cfg["ph_asam_max"];
                if (cfg.containsKey("ph_netral_min"))       fuzzy.ph_netral_min       = cfg["ph_netral_min"];
                if (cfg.containsKey("ph_netral_max"))       fuzzy.ph_netral_max       = cfg["ph_netral_max"];
                if (cfg.containsKey("ph_basa_min"))         fuzzy.ph_basa_min         = cfg["ph_basa_min"];
                Serial.println("[Command] Fuzzy Config diperbarui!");
            }
        }
    } else {
        Serial.printf("[Command] GET gagal, code: %d\n", httpCode);
    }
    https.end();
}
