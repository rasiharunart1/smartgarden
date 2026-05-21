# 📋 ANALISIS KOMUNIKASI BIDIREKSIONAL ESP32 ↔ LARAVEL

**Status**: ⚠️ **DIPERBAIKI** (Versi sebelumnya: TIDAK LENGKAP)
**Tanggal**: May 22, 2026
**Scope**: Telemetri, Kontrol Pompa, Fuzzy Logic Config

---

## 1. ARSITEKTUR KOMUNIKASI

### 1.1 Flow Diagram Bidireksional

```
┌─────────────────────────────────────────────────────────────────┐
│                        SENSOR READING CYCLE                      │
│                      (Setiap 10 detik)                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────┐
        │  1. Baca: DHT22, LDR, Soil, pH          │
        └─────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────┐
        │  2. Hitung Fuzzy Logic                  │
        │     → fuzzyPumpDecision (bool)          │
        └─────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────┐
        │  3. Tentukan Mode Kontrol               │
        │     Mode 0: Fuzzy Logic (otomatis)      │
        │     Mode 1: Server Override (manual)    │
        └─────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────┐
        │  4. KONTROL POMPA sesuai Mode           │
        │     (IMMEDIATE → relay ON/OFF)          │
        └─────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────┐
        │  5. HTTP POST → /api/v1/telemetry       │
        │     Header: X-Device-Code               │
        │     Payload: {sensor data}              │
        └─────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────┐
        │  6. Laravel API Response                │
        │  ├─ device_controls.pump_state ◄────┐  │
        │  ├─ fuzzy_config (8 params)  ◄────┐ │  │
        │  └─ status, message          ◄────┐ │  │
        │     (For NEXT cycle)               │ │  │
        └─────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────┐
        │  7. Parse Response & Update Config      │
        │     - Update fuzzy params               │
        │     - Cek server override               │
        │     (Untuk cycle berikutnya)            │
        └─────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                      MQTT REAL-TIME COMMAND CHANNEL             │
│                    (Immediate, Independent Cycle)               │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┴──────────────────┐
        │                                         │
        ▼                                         ▼
┌─────────────────────┐              ┌──────────────────────┐
│  User Dashboard     │              │  ESP32 IoT Device    │
│  (Laravel Web)      │              │  (MQTT Client)       │
│                     │              │                      │
│ togglePump() API    │              │ mqttCallback()       │
│ ↓                   │              │ handleMQTTCommand()  │
│ Publish MQTT        ├─────────────►│ Mode = OVERRIDE      │
│ sena/rx/{code}      │  JSON CMD    │ pump_state = value   │
│ {action, state}     │              │ Timer = NOW + 5min   │
│                     │              │ PUMP CONTROL IMMED!  │
└─────────────────────┘              └──────────────────────┘
```

---

## 2. DETAIL KOMUNIKASI

### 2.1 Channel A: HTTP Telemetry (Polling Model)
**Direction**: ESP32 → Laravel  
**Frequency**: 10 detik  
**Method**: POST  
**Endpoint**: `https://domain/api/v1/telemetry`

#### Request Header
```http
X-Device-Code: {device_uuid_from_dashboard}
Content-Type: application/json
Accept: application/json
```

#### Request Body
```json
{
  "ph": 6.8,
  "soil_moisture": 45.5,
  "light_intensity": 320.0,
  "temperature": 28.5,
  "humidity": 72.0
}
```

#### Response (201 Created)
```json
{
  "status": "success",
  "message": "Telemetry data saved correctly",
  "data": { "id": 123, "device_id": 1, ... },
  "device_controls": {
    "pump_state": false
  },
  "fuzzy_config": {
    "moisture_kering_max": 40.0,
    "moisture_normal_min": 30.0,
    "moisture_normal_max": 70.0,
    "moisture_basah_min": 60.0,
    "ph_asam_max": 6.5,
    "ph_netral_min": 6.0,
    "ph_netral_max": 7.5,
    "ph_basa_min": 7.0
  }
}
```

#### Timing Issue ⚠️ (SOLVED in Updated Firmware)
- **Sebelum Fix**: Pompa dikontrol SEBELUM HTTP request  
  → Server response = 1 cycle terlambat  
- **Sesudah Fix**: Logika yang jelas:
  1. **Cycle N**: Kirim data, terima server state untuk cycle N+1
  2. **Cycle N+1**: Aplikasikan server override jika ada
  3. Timeout 5 menit untuk kembali ke fuzzy

---

### 2.2 Channel B: MQTT Command (Push Model) - ✅ NOW IMPLEMENTED
**Direction**: Laravel → ESP32  
**Frequency**: Real-time (immediate)  
**Protocol**: MQTT  
**Broker**: Configured in `config/mqtt.php`  
**Topic**: `sena/rx/{device_code}`

#### Payload A: Toggle Pump (Immediate Control)
```json
{
  "action": "toggle_pump",
  "pump_state": 1
}
```

**Logic**:
```
ESP32 receives:
  → pumpControlMode = 1 (MANUAL OVERRIDE)
  → serverPumpCommand = 1
  → serverCommandTimestamp = NOW
  → controlPump(true) ← IMMEDIATE!
  → Timeout 5 menit: kembali ke FUZZY mode
```

#### Payload B: Update Fuzzy Config (Real-time Parameter Tuning)
```json
{
  "action": "update_fuzzy",
  "moisture_kering_max": 40.0,
  "moisture_normal_min": 30.0,
  "moisture_normal_max": 70.0,
  "moisture_basah_min": 60.0,
  "ph_asam_max": 6.5,
  "ph_netral_min": 6.0,
  "ph_netral_max": 7.5,
  "ph_basa_min": 7.0
}
```

---

## 3. FUZZY LOGIC ANALYSIS

### 3.1 Fuzzification (Keanggotaan)

#### Moisture (Soil) - 3 Categories
```
Kering:  0% ────────────► 40% (peak ~30%)
Normal:              30% ──────────► 70%
Basah:                              60% ────► 100% (peak ~70%)

Membership Function: Trapezoid + Triangle
```

#### pH Tanah - 3 Categories
```
Asam:    0 ─────────────► 6.5 (peak ~5.5)
Netral:                  6.0 ──────► 7.5
Basa:                               7.0 ─────► 14 (peak ~8.0)
```

### 3.2 Rule Base (9 Inference Rules)

| No | Kelembaban | pH Tanah | Output | Pump |
|----|-----------:|----------|--------|------|
| R1 | Kering     | Netral   | TINGGI | 1.0  |
| R2 | Kering     | Asam     | SEDANG | 0.7  |
| R3 | Kering     | Basa     | SEDANG | 0.7  |
| R4 | Normal     | Netral   | RENDAH | 0.2  |
| R5 | Normal     | Asam     | SEDANG | 0.5  |
| R6 | Normal     | Basa     | SEDANG | 0.5  |
| R7 | Basah      | Netral   | MATI   | 0.0  |
| R8 | Basah      | Asam     | MATI   | 0.0  |
| R9 | Basah      | Basa     | MATI   | 0.0  |

**Logic**: MIN operator (Mamdani)  
**Defuzzification**: Weighted Average (Centroid)

### 3.3 Decision Threshold
```
output ≤ 0.5  → Pompa OFF
output > 0.5  → Pompa ON
```

---

## 4. STATE MACHINE (Pump Control)

```
┌─────────────────┐
│  FUZZY MODE     │ (Mode = 0, Autonomous)
│  (Default)      │
│                 │
│ Input: Fuzzy    │
│ Output Score    │
└────────┬────────┘
         │
         │ Server sends toggle_pump via MQTT
         │ OR Web UI togglePump() triggered
         ▼
┌─────────────────┐
│ OVERRIDE MODE   │ (Mode = 1, Manual Control)
│ (Active 5 min)  │
│                 │
│ Input: Server   │
│ Command         │
└────────┬────────┘
         │
         │ Timeout 5 menit OR
         │ next HTTP response = fuzzy
         ▼
┌─────────────────┐
│  FUZZY MODE     │ (Back to autonomous)
│  (Default)      │
└─────────────────┘
```

---

## 5. ISSUES FOUND & FIXES

### Issue #1: ❌ NO MQTT LISTENER
**Status**: ✅ FIXED

**Problem**:
- ESP32 firmware commented tentang menerima via MQTT tapi tidak ada implementasi
- Config update hanya dari HTTP response (cycle lag)
- Real-time pump toggle tidak bisa dilakukan

**Solution**:
✅ Added PubSubClient library  
✅ Setup MQTT connection dalam setup()  
✅ mqttCallback() untuk listen `sena/rx/{device_code}`  
✅ handleMQTTCommand() untuk parse JSON commands  
✅ Immediate pump control via MQTT

---

### Issue #2: ⚠️ PUMP CONTROL LOGIC UNCLEAR
**Status**: ✅ FIXED

**Problem** (Old Code):
```cpp
bool serverPumpOverride = false;
bool serverPumpState = false;

if (!serverPumpOverride) {
    controlPump(fuzzyDecision);
}
// Later...
if (srvPump != fuzzyDecision) {
    serverPumpOverride = true;
    // ...
}
```

Issues:
- Flag `serverPumpOverride` logic tidak konsisten
- Pompa dikontrol SEBELUM HTTP request (1 cycle lag)
- Tidak ada timeout untuk kembali ke fuzzy

**Solution** (New Code):
```cpp
uint8_t pumpControlMode = 0; // 0=FUZZY, 1=OVERRIDE
bool fuzzyPumpDecision = false;
bool serverPumpCommand = false;
unsigned long serverCommandTimestamp = 0;
const unsigned long OVERRIDE_TIMEOUT = 300000; // 5 menit

// Determine desired state based on mode
bool desiredPumpState;
if (pumpControlMode == 0) {
    desiredPumpState = fuzzyPumpDecision;
} else {
    desiredPumpState = serverPumpCommand;
}

// Control immediately
controlPump(desiredPumpState);

// Check timeout
if (pumpControlMode == 1) {
    if (millis() - serverCommandTimestamp > OVERRIDE_TIMEOUT) {
        pumpControlMode = 0; // Back to FUZZY
    }
}
```

---

### Issue #3: ❌ TIMING: Control Before Request
**Status**: ✅ FIXED

**Problem**:
- Pompa diatur SEBELUM telemetry dikirim
- Server response datang setelah pompa sudah aktif
- Override command selalu tertunda 1 cycle

**Solution**:
- Pump control tetap IMMEDIATE (sesuai mode)
- Server response untuk cycle BERIKUTNYA
- MQTT channel untuk kontrol real-time tanpa lag

---

### Issue #4: ⚠️ INCOMPLETE MQTT IN LARAVEL
**Status**: ℹ️ ALREADY IMPLEMENTED

**Check**:
- ✅ DeviceController.togglePump() → MQTT publish
- ✅ FuzzyConfigController.update() → MQTT publish

Routes:
```
POST   /devices/{device}/toggle-pump        (Web auth)
PUT    /devices/{device}/fuzzy               (Web auth)
```

Note: API routes untuk device control belum ada. Hanya via Web UI.

---

## 6. DATA FLOW CONTOH

### Scenario: User Toggle Pompa via Dashboard

```
Timeline:
┌─────────────────────────────────────────────────────────┐
│ T=0s: User klik "ON" tombol pompa di dashboard          │
├─────────────────────────────────────────────────────────┤
│ T=0.1s: Browser POST /devices/1/toggle-pump             │
│         → Laravel: Device.pump_state = true              │
│         → MQTT: publish sena/rx/uuid-xxx                │
│             {"action": "toggle_pump", "pump_state": 1}  │
├─────────────────────────────────────────────────────────┤
│ T=0.2s: ESP32 mqttCallback() triggered                  │
│         → handleMQTTCommand() parse JSON                 │
│         → pumpControlMode = 1 (OVERRIDE)                │
│         → serverPumpCommand = true                      │
│         → serverCommandTimestamp = 0.2s                 │
│         → controlPump(true) ← RELAY ON!                 │
│         → Serial: "[PUMP] State → ON ✓ (Mode: MANUAL)"  │
├─────────────────────────────────────────────────────────┤
│ T=10s: ESP32 Telemetry Cycle                            │
│         → Kirim sensor data                              │
│         → HTTP 201 response                              │
│         → Cek: device_controls.pump_state = ?            │
│         → Banding dengan fuzzyPumpDecision?             │
├─────────────────────────────────────────────────────────┤
│ T=300s: Override timeout                                │
│         → if (millis - serverCommandTimestamp > 5min)   │
│         → pumpControlMode = 0 (FUZZY)                   │
│         → Kembali ke autonomous mode                    │
└─────────────────────────────────────────────────────────┘
```

**Result**: 
- Kontrol pompa: ~0.2 detik (via MQTT)
- Status update: ~10 detik (next HTTP cycle)
- Mode reset: 5 menit timeout

---

## 7. API ENDPOINT MAPPING

### Current Implementation

| Endpoint | Method | Auth | Function | Channel |
|----------|--------|------|----------|---------|
| `/api/v1/telemetry` | POST | Header Code | Receive sensor data | HTTP |
| `/devices/{id}/toggle-pump` | POST | Web Auth | Control pump | MQTT |
| `/devices/{id}/fuzzy` | PUT | Web Auth | Update fuzzy config | MQTT |

### Recommendation: Add API Routes for Programmatic Control
```php
// api.php - Add these:
Route::prefix('v1')->middleware('auth:device')->group(function () {
    Route::post('/devices/{id}/control', [ApiDeviceController::class, 'control']);
    Route::put('/devices/{id}/fuzzy', [ApiFuzzyConfigController::class, 'update']);
});
```

---

## 8. HARDWARE CHECKLIST

- [x] DHT22 (Suhu & Humidity) - Pin 4
- [x] LDR (Light Intensity) - Pin 32 ADC
- [x] Soil Moisture - Pin 34 ADC
- [x] pH Sensor - Pin 35 ADC
- [x] Relay Pompa - Pin 2 (HIGH = ON)
- [x] LCD 16x2 I2C - SDA:21, SCL:22, Addr:0x27
- [ ] **MQTT Broker Connection** - Perlu dikonfigurasi

---

## 9. SETUP & DEPLOYMENT

### Arduino IDE Library Required
```
- DHT sensor library (Adafruit)
- Adafruit Unified Sensor
- LiquidCrystal I2C (Frank de Brabander)
- ArduinoJson (Benoit Blanchon)
- PubSubClient (Nick O'Leary) ← NEW
```

### Configuration File (Before Upload)
```cpp
const char* WIFI_SSID       = "YOUR_SSID";
const char* WIFI_PASSWORD   = "YOUR_PASSWORD";
const char* SERVER_URL      = "https://your-domain.com/api/v1/telemetry";
const char* DEVICE_CODE     = "UUID_FROM_DASHBOARD";
const char* MQTT_SERVER     = "mqtt-broker-ip";
const int   MQTT_PORT       = 1883;
const char* MQTT_USER       = "";  // optional
const char* MQTT_PASS       = "";  // optional
```

### Laravel Configuration (config/mqtt.php)
```php
'host'      => env('MQTT_HOST', 'localhost'),
'port'      => env('MQTT_PORT', 1883),
'client_id' => env('MQTT_CLIENT_ID', 'laravel-mqtt'),
'username'  => env('MQTT_USERNAME', ''),
'password'  => env('MQTT_PASSWORD', ''),
```

---

## 10. KESIMPULAN

✅ **Bidireksional Communication**: COMPLETE
- HTTP: Telemetry + Config Update (HTTP Response)
- MQTT: Real-time Command + Fuzzy Update

✅ **Fuzzy Logic**: CORRECT
- 9 inference rules
- Proper fuzzification & defuzzification
- Realistic membership functions

✅ **State Management**: IMPROVED
- Clear mode switching (FUZZY ↔ OVERRIDE)
- 5-minute auto-reset timeout
- Immediate MQTT response

✅ **Error Handling**: BASIC
- WiFi auto-reconnect
- MQTT auto-reconnect (10s retry)
- Device authentication via header

⚠️ **Recommendations**:
1. Setup MQTT broker (Mosquitto / cloud service)
2. Add API routes untuk programmatic device control
3. Implement fuzzy config validation di server
4. Add device health check endpoint
5. Consider end-to-end encryption untuk MQTT

---

**Last Updated**: May 22, 2026  
**Firmware Version**: esp32_sena_full.ino (UPDATED)
