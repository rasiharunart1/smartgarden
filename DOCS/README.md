# SENA Smart Garden — Indeks Dokumentasi

Selamat datang di dokumentasi sistem **SENA (Smart Environment & Nature Automation)**.
Platform IoT berbasis Laravel 11 untuk pemantauan dan otomasi taman cerdas.

---

## Daftar Dokumen

| No | File | Isi |
|----|------|-----|
| 1 | [01_ARSITEKTUR_SISTEM.md](./01_ARSITEKTUR_SISTEM.md) | High-level architecture, stack teknologi, sequence diagram alur data |
| 2 | [02_DATABASE_UML.md](./02_DATABASE_UML.md) | ERD, schema tabel, UML class diagram Eloquent, migration timeline |
| 3 | [03_API_PROTOKOL.md](./03_API_PROTOKOL.md) | Dokumentasi semua endpoint, request/response format, protokol MQTT |
| 4 | [04_CONTROLLER_ROUTING.md](./04_CONTROLLER_ROUTING.md) | UML controller, method per controller, routing tree lengkap |
| 5 | [05_FUZZY_FIRMWARE.md](./05_FUZZY_FIRMWARE.md) | Fuzzy Logic Mamdani, firmware ESP32, kalibrasi sensor, pin map |
| 6 | [06_DEPLOYMENT.md](./06_DEPLOYMENT.md) | Panduan instalasi, environment config, security checklist, troubleshooting |

---

## Ringkasan Cepat

### Sistem Terdiri Dari
- **Backend**: Laravel 11 (PHP 8.2), SQLite, REST API + MQTT
- **Frontend**: Blade Templates, Alpine.js, Chart.js, Tailwind CSS
- **Hardware**: ESP32 + DHT22 + LDR + Soil Moisture + pH Sensor + Relay
- **Protokol**: HTTPS (telemetri) + MQTT (kontrol real-time)

### Alur Utama
```
ESP32 ──[HTTPS POST /api/v1/telemetry]──► Laravel ──► SQLite
                                             │
                                             └──[MQTT Publish]──► ESP32
                                                  sena/rx/<device_code>

Browser ──[GET /dashboard/data setiap 5s]──► Laravel ──► Chart.js update
```

### Fuzzy Logic (9 Rules Mamdani)
Input: Kelembapan Tanah (%) + pH Tanah → Output: Keputusan Pompa (ON/OFF)
Threshold: crisp output > 0.5 → Pompa ON
