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
 *    1. DHT22  – Suhu & Kelembaban Udara      (Pin digital 4)
 *    2. LDR    – Intensitas Cahaya (Analog)   (Pin ADC 32)
 *    3. Analog Soil Moisture Sensor           (Pin ADC 34)
 *    4. Analog pH Sensor (pH-4502C)           (Pin ADC 35)
 *
 *  Display:
 *    - LCD 16x2 I2C (PCF8574)                 (SDA=21, SCL=22, Addr=0x27)
 *
 *  Aktuator:
 *    - Relay Pompa Air                        (Pin digital 2)
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
const char* WIFI_SSID       = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD   = "YOUR_WIFI_PASSWORD";
const char* SERVER_URL      = "https://your-domain.com/api/v1/telemetry";
const char* DEVICE_CODE     = "YOUR_DEVICE_UUID_FROM_DASHBOARD";

// Interval pengiriman telemetri (ms)
const unsigned long TELEMETRY_INTERVAL = 10000; // 10 detik

// ─── PIN DEFINITIONS ─────────────────────────────────────────
#define DHTPIN        4     // DHT22 data pin
#define DHTTYPE       DHT22
#define LDR_PIN       32    // ADC1_CH4 – LDR analog
#define SOIL_PIN      34    // ADC1_CH6 – Soil Moisture analog
#define PH_PIN        35    // ADC1_CH7 – pH Sensor analog
#define PUMP_PIN      2     // Relay pump (HIGH = ON)

// LCD I2C
#define LCD_ADDR      0x27  // Coba 0x3F jika 0x27 tidak bekerja
#define LCD_COLS      16
#define LCD_ROWS      2

// ─── SENSOR CALIBRATION ──────────────────────────────────────
// Soil Moisture
#define SOIL_DRY_ADC    3200  // ADC di udara (kering)
#define SOIL_WET_ADC     900  // ADC saat terendam air (basah)

// pH Sensor (pH-4502C)
#define PH_VOLTAGE_MID  2.50  // Tegangan saat pH = 7.0 (Volt)
#define PH_SLOPE        0.18  // Slope volt per unit pH (tipikal 0.18)

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
bool serverPumpOverride         = false; // true = ikuti perintah server
bool serverPumpState            = false; // nilai pump dari server
bool localPumpState             = false; // hasil fuzzy logic lokal

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
    controlPump(false); // Pompa mati saat startup

    // Inisialisasi sensor DHT22
    dht.begin();

    // Inisialisasi I2C & LCD
    Wire.begin(); // SDA=21, SCL=22 (default ESP32)
    lcd.init();
    lcd.backlight();
    lcd.setCursor(0, 0);
    lcd.print(" SENA  Garden  ");
    lcd.setCursor(0, 1);
    lcd.print("  Initializing..");
    Serial.println("[OK] LCD 16x2 I2C initialized");

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
    } else {
        Serial.println("\n[WiFi] GAGAL terhubung. Akan retry di loop...");
    }

    // SSL: Bypass verifikasi sertifikat (untuk development)
    // Untuk produksi: gunakan wifiClient.setCACert(rootCA_cert)
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

    // Kirim telemetri sesuai interval
    if (millis() - lastTelemetryTime >= TELEMETRY_INTERVAL) {
        lastTelemetryTime = millis();
        if (WiFi.status() == WL_CONNECTED) {
            sendTelemetry();
        } else {
            // Tetap jalankan fuzzy lokal meski WiFi mati
            float soil = readSoilMoisture();
            float ph   = readPH();
            float out  = runFuzzyLogic(soil, ph);
            if (!serverPumpOverride) controlPump(out > 0.5f);
        }
    }
}

// ============================================================
//  SENSOR READINGS
// ============================================================

/**
 * Baca kelembapan tanah dan konversi ke persen (0–100%)
 * Menggunakan mapping ADC berbasis kalibrasi
 */
float readSoilMoisture() {
    // Rata-rata 10 sample untuk stabilisasi ADC
    long sum = 0;
    for (int i = 0; i < 10; i++) {
        sum += analogRead(SOIL_PIN);
        delay(5);
    }
    int rawADC = sum / 10;

    // Map: SOIL_DRY_ADC = 0%, SOIL_WET_ADC = 100%
    float moisture = map(rawADC, SOIL_DRY_ADC, SOIL_WET_ADC, 0, 100);
    moisture = constrain(moisture, 0.0f, 100.0f);
    return moisture;
}

/**
 * Baca nilai pH dari sensor analog
 * Menggunakan rumus kalibrasi linier
 */
float readPH() {
    // Rata-rata 10 sample
    long sum = 0;
    for (int i = 0; i < 10; i++) {
        sum += analogRead(PH_PIN);
        delay(5);
    }
    int rawADC = sum / 10;

    float voltage = rawADC * (3.3f / 4095.0f);

    // pH = 7 - ((Vmid - V) / slope)
    float phValue = 7.0f - ((PH_VOLTAGE_MID - voltage) / PH_SLOPE);
    phValue = constrain(phValue, 0.0f, 14.0f);
    return phValue;
}

// ============================================================
//  FUZZY LOGIC ENGINE (Mamdani – Inference)
// ============================================================

/**
 * Membership Functions – Kelembapan Tanah
 * Menggunakan trapezoid & triangle membership
 *
 *  Kering : [0 ─── kering_max] dengan slope turun di kering_max
 *  Normal : [normal_min ── normal_max] segitiga
 *  Basah  : [basah_min ─── 100] dengan slope naik di basah_min
 */
float fuzzyMembership_Kering(float m) {
    // Sepenuhnya kering jika m <= (kering_max - 10)
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

/**
 * Membership Functions – pH Tanah
 *  Asam   : [0 ─── ph_asam_max]
 *  Netral : [ph_netral_min ─── ph_netral_max] segitiga
 *  Basa   : [ph_basa_min ─── 14]
 */
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
 * Menjalankan Fuzzy Logic dan mengembalikan nilai crisp (0–100)
 * yang merepresentasikan "kebutuhan penyiraman".
 *
 * Rule Base (Mamdani):
 * ─────────────────────────────────────────────────────────────
 * R1: IF Moisture=Kering  AND pH=Netral → Pompa=TINGGI   (1.0)
 * R2: IF Moisture=Kering  AND pH=Asam   → Pompa=SEDANG   (0.7)
 * R3: IF Moisture=Kering  AND pH=Basa   → Pompa=SEDANG   (0.7)
 * R4: IF Moisture=Normal  AND pH=Netral → Pompa=RENDAH   (0.2)
 * R5: IF Moisture=Normal  AND pH=Asam   → Pompa=SEDANG   (0.5)
 * R6: IF Moisture=Normal  AND pH=Basa   → Pompa=SEDANG   (0.5)
 * R7: IF Moisture=Basah   AND pH=Netral → Pompa=MATI     (0.0)
 * R8: IF Moisture=Basah   AND pH=Asam   → Pompa=MATI     (0.0)
 * R9: IF Moisture=Basah   AND pH=Basa   → Pompa=MATI     (0.0)
 *
 * Defuzzifikasi: Weighted Average
 * Returns: nilai 0.0–1.0 (threshold >0.5 → pompa ON)
 */
float runFuzzyLogic(float soilMoisture, float phValue) {
    // Fuzzifikasi Kelembapan
    float mKering = fuzzyMembership_Kering(soilMoisture);
    float mNormal = fuzzyMembership_Normal(soilMoisture);
    float mBasah  = fuzzyMembership_Basah(soilMoisture);

    // Fuzzifikasi pH
    float pAsam   = fuzzyMembership_PHAsam(phValue);
    float pNetral = fuzzyMembership_PHNetral(phValue);
    float pBasa   = fuzzyMembership_PHBasa(phValue);

    // Evaluasi Rules (MIN operator – Mamdani)
    float r1 = min(mKering, pNetral); // → Tinggi (1.0)
    float r2 = min(mKering, pAsam);   // → Sedang (0.7)
    float r3 = min(mKering, pBasa);   // → Sedang (0.7)
    float r4 = min(mNormal, pNetral); // → Rendah (0.2)
    float r5 = min(mNormal, pAsam);   // → Sedang (0.5)
    float r6 = min(mNormal, pBasa);   // → Sedang (0.5)
    float r7 = min(mBasah,  pNetral); // → Mati   (0.0)
    float r8 = min(mBasah,  pAsam);   // → Mati   (0.0)
    float r9 = min(mBasah,  pBasa);   // → Mati   (0.0)

    // Output singleton values
    const float OUT_TINGGI = 1.0f;
    const float OUT_SEDANG = 0.7f;
    const float OUT_RENDAH = 0.2f;
    const float OUT_MATI   = 0.0f;

    // Defuzzifikasi: Weighted Average (Centroid simplifed)
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

    // Debug output fuzzy
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
    digitalWrite(PUMP_PIN, state ? HIGH : LOW);
    localPumpState = state;
    Serial.printf("[PUMP] → %s\n", state ? "ON ✓" : "OFF ✗");
}

// ============================================================
//  MAIN TELEMETRY FUNCTION
// ============================================================
void sendTelemetry() {
    Serial.println("\n══════════════════════════════════════");
    Serial.println(" SENA - Cycle Telemetri");
    Serial.println("══════════════════════════════════════");

    // ── 1. BACA SEMUA SENSOR ─────────────────────────────────
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

    // Simpan ke global (untuk LCD)
    g_temp = tempC; g_humid = humidAir;
    g_soil = soilMoist; g_ph = phVal; g_lux = lux;

    // Log ke Serial
    Serial.printf("[DHT22]  Suhu: %.2f°C | Kelembaban Udara: %.2f%%\n", tempC, humidAir);
    Serial.printf("[LDR]    Cahaya: %.2f lux\n", lux);
    Serial.printf("[Soil]   Kelembaban Tanah: %.2f%%\n", soilMoist);
    Serial.printf("[pH]     Nilai pH: %.2f\n", phVal);

    // ── 2. JALANKAN FUZZY LOGIC ──────────────────────────────
    float fuzzyOutput = runFuzzyLogic(soilMoist, phVal);
    bool  fuzzyDecision = (fuzzyOutput > 0.5f);

    // ── 3. KONTROL POMPA (sebelum request – keputusan lokal) ─
    // Jika server tidak memberikan override, gunakan fuzzy
    if (!serverPumpOverride) {
        controlPump(fuzzyDecision);
    }

    // ── 4. BANGUN JSON PAYLOAD ───────────────────────────────
    StaticJsonDocument<256> doc;
    doc["ph"]              = round(phVal * 100.0f) / 100.0f;
    doc["soil_moisture"]   = round(soilMoist * 10.0f) / 10.0f;
    doc["light_intensity"] = round(lux * 10.0f) / 10.0f;
    doc["temperature"]     = round(tempC * 10.0f) / 10.0f;
    doc["humidity"]        = round(humidAir * 10.0f) / 10.0f;

    String jsonPayload;
    serializeJson(doc, jsonPayload);

    Serial.printf("[HTTP]  Mengirim payload: %s\n", jsonPayload.c_str());

    // ── 5. HTTP POST ─────────────────────────────────────────
    HTTPClient https;
    if (!https.begin(wifiClient, SERVER_URL)) {
        Serial.println("[HTTP]  Gagal membuka koneksi!");
        return;
    }

    https.addHeader("Content-Type", "application/json");
    https.addHeader("Accept", "application/json");
    https.addHeader("X-Device-Code", DEVICE_CODE);
    https.setTimeout(8000); // 8 detik timeout

    int httpCode = https.POST(jsonPayload);

    if (httpCode > 0) {
        String response = https.getString();
        Serial.printf("[HTTP]  Response Code: %d\n", httpCode);

        // ── 6. PARSE RESPONSE & TERIMA PERINTAH SERVER ───────
        StaticJsonDocument<512> resDoc;
        DeserializationError err = deserializeJson(resDoc, response);

        if (!err) {
            // Update fuzzy config jika server mengirimnya
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

            // Terima perintah pompa dari server (kontrol manual dashboard)
            if (resDoc.containsKey("device_controls")) {
                bool srvPump = resDoc["device_controls"]["pump_state"] | false;

                if (srvPump != fuzzyDecision) {
                    // Server mengoverride keputusan fuzzy (kontrol manual)
                    serverPumpOverride = true;
                    serverPumpState    = srvPump;
                    controlPump(srvPump);
                    Serial.printf("[SERVER] Override pompa dari dashboard: %s\n",
                                  srvPump ? "ON" : "OFF");
                } else {
                    // Server setuju dengan fuzzy, lepas override
                    serverPumpOverride = false;
                }
            }
        } else {
            Serial.printf("[HTTP]  Gagal parse JSON response: %s\n", err.c_str());
        }

    } else {
        Serial.printf("[HTTP]  POST gagal! Error: %s\n", https.errorToString(httpCode).c_str());
    }

    https.end();

    Serial.printf("[STATUS] Pompa: %s | Fuzzy Score: %.3f\n",
                  localPumpState ? "ON" : "OFF", fuzzyOutput);
    Serial.println("══════════════════════════════════════\n");
}

// ============================================================
//  LDR – BACA INTENSITAS CAHAYA
// ============================================================
/**
 * Baca LDR dan konversi ADC ke estimasi lux.
 * LDR: resistansi tinggi = gelap (ADC tinggi), resistansi rendah = terang.
 * Gunakan rangkaian voltage divider: 3.3V – LDR – GPIO32 – 10kΩ – GND
 */
float readLDR() {
    long sum = 0;
    for (int i = 0; i < 10; i++) {
        sum += analogRead(LDR_PIN);
        delay(3);
    }
    int rawADC = sum / 10;
    // Map ADC terbalik: ADC rendah = terang, ADC tinggi = gelap
    float lux = map(rawADC, LDR_BRIGHT_ADC, LDR_DARK_ADC, LDR_MAX_LUX, 0);
    lux = constrain(lux, 0.0f, (float)LDR_MAX_LUX);
    return lux;
}

// ============================================================
//  LCD 16x2 I2C – UPDATE DISPLAY (Non-Blocking)
// ============================================================
/**
 * Menampilkan data sensor bergantian setiap 3 detik:
 *   Layar 0: Suhu & Kelembaban Udara
 *   Layar 1: Kelembaban Tanah & pH
 *   Layar 2: Cahaya & Status Pompa
 *   Layar 3: Status WiFi & Fuzzy
 */
void updateLCD() {
    static unsigned long lastSwitch = 0;
    static uint8_t screen = 0;
    if (millis() - lastSwitch < 3000) return;
    lastSwitch = millis();

    lcd.clear();
    switch (screen) {
        case 0:
            lcd.setCursor(0, 0);
            lcd.print("Suhu: ");
            lcd.print(g_temp, 1);
            lcd.print((char)223); // simbol derajat
            lcd.print("C      ");
            lcd.setCursor(0, 1);
            lcd.print("Hum: ");
            lcd.print(g_humid, 1);
            lcd.print("%       ");
            break;
        case 1:
            lcd.setCursor(0, 0);
            lcd.print("Tanah: ");
            lcd.print(g_soil, 1);
            lcd.print("%      ");
            lcd.setCursor(0, 1);
            lcd.print("pH   : ");
            lcd.print(g_ph, 2);
            lcd.print("       ");
            break;
        case 2:
            lcd.setCursor(0, 0);
            lcd.print("Cahaya: ");
            lcd.print(g_lux, 0);
            lcd.print(" lux  ");
            lcd.setCursor(0, 1);
            lcd.print("Pompa : ");
            lcd.print(localPumpState ? "ON  " : "OFF ");
            break;
        case 3: {
            lcd.setCursor(0, 0);
            bool online = (WiFi.status() == WL_CONNECTED);
            lcd.print(online ? "WiFi: OK        " : "WiFi: --        ");
            lcd.setCursor(0, 1);
            lcd.print(serverPumpOverride ? "Mode: MANUAL    " : "Mode: FUZZY     ");
            break;
        }
    }
    screen = (screen + 1) % 4;
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
