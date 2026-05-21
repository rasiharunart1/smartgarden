# SENA Smart Garden — Dokumentasi Arsitektur Sistem

## 1. Overview Sistem

**SENA (Smart Environment & Nature Automation)** adalah platform IoT berbasis Laravel 11 untuk pemantauan dan kontrol taman cerdas secara real-time. Sistem menghubungkan perangkat ESP32 di lapangan dengan dashboard web melalui HTTP REST API dan MQTT broker.

---

## 2. Arsitektur Sistem (High-Level)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        LAPANGAN (EDGE)                              │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  ESP32 Node (esp32_sena_full.ino)                            │   │
│  │                                                              │   │
│  │  Sensor:                    Aktuator:                        │   │
│  │  • DHT22 (Suhu & Humid)     • Relay Pompa Air (GPIO 2)       │   │
│  │  • LDR   (Cahaya, GPIO 32)  • LCD 16x2 I2C (SDA=21,SCL=22)  │   │
│  │  • Soil Moisture (GPIO 34)                                   │   │
│  │  • pH-4502C (GPIO 35)                                        │   │
│  │                                                              │   │
│  │  Logic: Fuzzy Mamdani (lokal, offline-capable)               │   │
│  └────────────────────────────┬─────────────────────────────────┘   │
│                               │ WiFi / HTTPS                        │
└───────────────────────────────┼─────────────────────────────────────┘
                                │
                    ┌───────────▼────────────┐
                    │   Internet / Network   │
                    └───────────┬────────────┘
                                │
         ┌──────────────────────┼──────────────────────┐
         │ HTTPS POST           │                       │ MQTT Pub/Sub
         ▼                      ▼                       ▼
┌──────────────────┐   ┌─────────────────┐   ┌──────────────────────┐
│  Laravel 11 API  │   │  Laravel Web    │   │   MQTT Broker        │
│  /api/v1/        │   │  (Dashboard)    │   │  (broker.hivemq.com) │
│  telemetry       │   │                 │   │                      │
│                  │   │  Blade + Alpine │   │  Topics:             │
│  Auth: X-Device  │   │  Chart.js       │   │  sena/tx/<code>      │
│  -Code header    │   │  Polling 5s     │   │  sena/rx/<code>      │
└────────┬─────────┘   └─────────────────┘   └──────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        DATABASE (SQLite)                            │
│                                                                     │
│  users ──< devices ──< device_logs                                  │
│                  └──── fuzzy_configs                                 │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. Stack Teknologi

| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend Framework | Laravel | 11.x |
| PHP | PHP | ^8.2 |
| Database | SQLite | 3.x |
| Autentikasi Web | Laravel Breeze | 2.3 |
| Autentikasi API Token | Laravel Sanctum | 4.x |
| MQTT Client (Server) | php-mqtt/client | 2.3 |
| Frontend CSS | Tailwind CSS | 3.x |
| Frontend JS | Alpine.js + Chart.js | - |
| Build Tool | Vite | - |
| Mikrokontroler | ESP32 (Arduino Framework) | - |
| IoT Protocol | HTTPS + MQTT (TLS) | - |

---

## 4. Alur Data Telemetri

```
ESP32                          Laravel API                      Database
  │                                │                               │
  │── [Baca Sensor] ───────────────│                               │
  │   DHT22, LDR, Soil, pH         │                               │
  │                                │                               │
  │── [Fuzzy Logic] ───────────────│                               │
  │   Hitung keputusan pompa       │                               │
  │                                │                               │
  │── POST /api/v1/telemetry ──────►│                               │
  │   Header: X-Device-Code        │                               │
  │   Body: {ph, soil_moisture,    │                               │
  │          light_intensity,      │                               │
  │          temperature, humidity}│                               │
  │                                │── Validate device_code ──────►│
  │                                │── Save DeviceLog ─────────────►│
  │                                │── Update last_seen_at ─────────►│
  │                                │── Load FuzzyConfig ────────────►│
  │                                │◄──────────────────────────────│
  │◄── 201 Response ───────────────│                               │
  │   {status, data,               │                               │
  │    device_controls:{pump_state}│                               │
  │    fuzzy_config:{...}}         │                               │
  │                                │                               │
  │── [Apply server commands] ─────│                               │
  │   Override / update fuzzy      │                               │
  │                                │                               │
```

---

## 5. Alur Dashboard Real-Time

```
Browser                         Laravel Web                     Database
  │                                │                               │
  │── GET /dashboard ─────────────►│                               │
  │                                │── Query devices+logs ─────────►│
  │                                │◄──────────────────────────────│
  │◄── Blade HTML ─────────────────│                               │
  │                                │                               │
  │  (Setiap 5 detik via JS)        │                               │
  │── GET /dashboard/data ─────────►│                               │
  │   ?device_id=X                 │── Query last 24 logs ──────────►│
  │                                │◄──────────────────────────────│
  │◄── JSON {latest, chart} ───────│                               │
  │   Update Chart.js & cards      │                               │
```

---

## 6. Alur Kontrol Pompa via MQTT

```
Browser (Dashboard)       Laravel Web          MQTT Broker        ESP32
      │                       │                     │               │
      │── POST /devices/{id}  │                     │               │
      │   /toggle-pump ───────►│                     │               │
      │   {pump_state: true}   │                     │               │
      │                       │── DB update ─────────│               │
      │                       │   pump_state=true    │               │
      │                       │                     │               │
      │                       │── MQTT Publish ──────►│               │
      │                       │   Topic: sena/rx/<code>              │
      │                       │   {action:toggle_pump,pump_state:1}  │
      │                       │                     │── Subscribe ──►│
      │◄── JSON response ──────│                     │   & Execute   │
      │   {success, pump_state}│                     │               │
```
