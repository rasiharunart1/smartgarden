# 📋 DOKUMENTASI KOMUNIKASI IoT - HTTP POLLING MODEL

**Status**: ✅ **FINAL - HTTP CLIENT ONLY**  
**Date**: May 22, 2026  
**Approach**: Pure REST API + Cache-based Command Queue

---

## QUICK SUMMARY

```
┌─────────────────────┐         ┌──────────────────────┐
│   ESP32 Device      │         │   Laravel Backend    │
│   (IoT Node)        │         │   (Web Server)       │
└─────────────────────┘         └──────────────────────┘
         │                               │
    Every 10s│                           │
         ├─ Read Sensors                 │
         ├─ Calculate Fuzzy              │
         ├─ Control Pump                 │
         └─► HTTP POST /api/v1/telemetry─┤
                                         │◄─── Get Device State
                                         │
    Every 5s│                            │
         └─► HTTP GET /api/v1/device-commands/{code}
                                         │
                                         ├─ Check Redis Cache
                                    ┌────┴────┐
                                    │          │
                                    ▼          ▼
                            No Command    Has Command
                            (continue)    (pump_toggle,
                                         update_fuzzy)
                                    │
                                    ▼
                            Queue deleted,
                            Device updates
                            immediately
```

---

## ENDPOINTS

### 1. POST /api/v1/telemetry
**Purpose**: Send sensor data and receive device state  
**Frequency**: Every 10 seconds  
**Authentication**: Header `X-Device-Code`

**Request**:
```json
{
  "ph": 6.8,
  "soil_moisture": 45.5,
  "light_intensity": 320.0,
  "temperature": 28.5,
  "humidity": 72.0
}
```

**Response** (201 Created):
```json
{
  "status": "success",
  "message": "Telemetry data saved correctly",
  "data": {...}
}
```

---

### 2. GET /api/v1/device-commands/{deviceCode}
**Purpose**: Poll for pending commands (pump toggle, fuzzy config update)  
**Frequency**: Every 5 seconds  
**Authentication**: Header `X-Device-Code`  
**Storage**: Redis Cache (TTL: 30 seconds)

**Response - No Command** (200 OK):
```json
{
  "status": "success",
  "has_command": false,
  "command": null
}
```

**Response - Pump Toggle** (200 OK):
```json
{
  "status": "success",
  "has_command": true,
  "command": {
    "type": "pump_toggle",
    "pump_state": true,
    "timestamp": "2026-05-22T10:30:00Z"
  }
}
```

**Response - Fuzzy Update** (200 OK):
```json
{
  "status": "success",
  "has_command": true,
  "command": {
    "type": "update_fuzzy",
    "timestamp": "2026-05-22T10:30:00Z",
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
}
```

---

## FIRMWARE FLOW

### Telemetry Cycle (10s)
```
sendTelemetry():
  1. Read all sensors
  2. Calculate fuzzyLogic(soil, ph)
  3. Determine pump state based on mode
  4. Control pump IMMEDIATELY
  5. HTTP POST telemetry to server
  6. Parse response & update next cycle state
```

### Command Polling (5s - Independent)
```
checkServerCommands():
  1. HTTP GET /api/v1/device-commands/{code}
  2. Parse response:
     - If has_command == true:
       a. type == "pump_toggle" → pumpControlMode=OVERRIDE, controlPump() NOW
       b. type == "update_fuzzy" → update fuzzy parameters
  3. Continue loop
```

---

## PUMP STATE MACHINE

```
FUZZY MODE (Default)           OVERRIDE MODE (Manual)
   │                                  │
   │ No server command                │ User toggles pump
   │ (polling returns false)          │ → queuePumpToggle()
   │                                  │ → command in Redis (TTL 30s)
   ├──────────────────────────────────┤
   │                                  │
   │ Next polling cycle (within 5s)   │
   │ ← Receive pump_toggle command    │
   │                                  │
   └──────────────────────────────────┘
                    │
              pumpControlMode = 1
              serverCommandTimestamp = NOW
              controlPump(newState) ← IMMEDIATE
              
              Timeout check every loop:
              if (millis - serverCommandTimestamp > 5 min):
                 pumpControlMode = 0
                 (Back to FUZZY)
```

---

## TIMING ANALYSIS

| Event | Timeline | Notes |
|-------|----------|-------|
| User clicks "Toggle" | T=0ms | Web UI optimistic update |
| API processes request | T=100ms | Queue command in Redis |
| Next ESP32 poll cycle | T=max 5s | GET /device-commands |
| Relay switches | T≤5.1s | controlPump() called |
| **Total Latency** | **≤5.1s** | Acceptable for agriculture |

---

## ARCHITECTURE ADVANTAGES

✅ **No external dependencies**  
   - No MQTT broker needed
   - Just HTTP/HTTPS

✅ **Familiar technology**  
   - Laravel Redis cache
   - REST API

✅ **Simple debugging**  
   - HTTP requests visible in logs
   - No protocol translation

✅ **Scalable**  
   - Each device polls independently
   - Cache cleanup automatic (TTL)

⚠️ **Latency trade-off**  
   - 5 seconds vs <100ms (MQTT)
   - Acceptable for smart farming

---

## SETUP CHECKLIST

- [ ] ESP32 configured with WiFi credentials
- [ ] Laravel API endpoints created
- [ ] Redis/Cache configured in Laravel
- [ ] Devices registered in dashboard with UUID
- [ ] SSL certificates trusted (or setInsecure() for dev)
- [ ] TELEMETRY_INTERVAL = 10000ms
- [ ] COMMAND_CHECK_INTERVAL = 5000ms

---

**Implementation**: See esp32_sena_full.ino + Laravel Controllers  
**Last Update**: May 22, 2026
