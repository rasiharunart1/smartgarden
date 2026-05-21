# SENA — Dokumentasi Fuzzy Logic & Firmware ESP32

## 1. Gambaran Umum Fuzzy Logic

Sistem menggunakan **Fuzzy Logic Mamdani** untuk memutuskan kapan pompa air dinyalakan secara otomatis. Keputusan didasarkan pada dua variabel input: **Kelembapan Tanah** dan **pH Tanah**.

Fuzzy Logic berjalan di **dua tempat**:
1. **ESP32 (lokal)** — Berjalan setiap siklus telemetri (setiap 10 detik). Dapat beroperasi offline tanpa server.
2. **Server** — Menyimpan dan mendistribusikan konfigurasi parameter fuzzy ke device via response API dan MQTT.

---

## 2. Variabel Input Fuzzy

### Input 1: Kelembapan Tanah (`soil_moisture`, 0–100%)

```
Derajat
Keanggotaan
   1.0 ┤████████\         /████████
       │        \       /
   0.5 ┤         \     /
       │          \   /
   0.0 ┤           \_/
       └─────────────────────────────► %
           0   30  40  50  60  70  100

       [Kering]  [Normal]  [Basah]
```

| Himpunan | Fungsi | Parameter Default |
|----------|--------|-------------------|
| Kering | Trapezoid turun | max = 40% |
| Normal | Segitiga | min=30%, max=70% |
| Basah | Trapezoid naik | min = 60% |

### Input 2: pH Tanah (`ph`, 0–14)

```
Derajat
Keanggotaan
   1.0 ┤██████\       /███████
       │       \     /
   0.5 ┤        \   /
       │         \_/
   0.0 ┤
       └──────────────────────────────► pH
            0  6.0 6.5 7.0 7.5  14

          [Asam]  [Netral]  [Basa]
```

| Himpunan | Fungsi | Parameter Default |
|----------|--------|-------------------|
| Asam | Trapezoid turun | max = 6.5 |
| Netral | Segitiga | min=6.0, max=7.5 |
| Basa | Trapezoid naik | min = 7.0 |

---

## 3. Rule Base (9 Rules Mamdani)

| Rule | Moisture | pH | Output Pompa | Nilai Crisp |
|------|----------|----|--------------|-------------|
| R1 | Kering | Netral | TINGGI | 1.0 |
| R2 | Kering | Asam | SEDANG | 0.7 |
| R3 | Kering | Basa | SEDANG | 0.7 |
| R4 | Normal | Netral | RENDAH | 0.2 |
| R5 | Normal | Asam | SEDANG | 0.5 |
| R6 | Normal | Basa | SEDANG | 0.5 |
| R7 | Basah | Netral | MATI | 0.0 |
| R8 | Basah | Asam | MATI | 0.0 |
| R9 | Basah | Basa | MATI | 0.0 |

**Threshold keputusan:** Output crisp > 0.5 → **POMPA ON**

---

## 4. Proses Defuzzifikasi (Weighted Average)

```
Output = Σ(rule_strength × output_crisp_value) / Σ(rule_strength)

Contoh:
  Moisture = 35% (Kering=0.5, Normal=0.5, Basah=0.0)
  pH       = 6.8 (Asam=0.0, Netral=0.8, Basa=0.2)

  R1 = min(0.5, 0.8) = 0.5 × 1.0 = 0.50
  R2 = min(0.5, 0.0) = 0.0 × 0.7 = 0.00
  R3 = min(0.5, 0.2) = 0.2 × 0.7 = 0.14
  R4 = min(0.5, 0.8) = 0.5 × 0.2 = 0.10
  ...

  Output = (0.50 + 0.14 + 0.10 + ...) / (0.5 + 0.2 + 0.5 + ...)
         ≈ 0.72 → POMPA ON
```

---

## 5. Diagram Alur Firmware ESP32

```
                    ┌──────────────┐
                    │    SETUP     │
                    │  Init GPIO   │
                    │  Init DHT22  │
                    │  Init LCD    │
                    │  Connect WiFi│
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │    LOOP      │◄─────────────────────┐
                    │   (terus)    │                      │
                    └──────┬───────┘                      │
                           │                              │
              ┌────────────▼──────────────┐               │
              │    reconnectWiFi()        │               │
              │  Cek WiFi tiap 5 detik    │               │
              └────────────┬──────────────┘               │
                           │                              │
              ┌────────────▼──────────────┐               │
              │    updateLCD()            │               │
              │  Rotate layar tiap 3 detik│               │
              │  (Screen 0-3)             │               │
              └────────────┬──────────────┘               │
                           │                              │
         ┌─────────────────▼──────────────────────┐       │
         │    Cek: millis() >= TELEMETRY_INTERVAL? │       │
         │             (10 detik)                  │       │
         └──────┬────────────────┬─────────────────┘       │
                │ YA             │ TIDAK                   │
                ▼                └────────────────────────►┘
    ┌───────────────────────┐
    │ WiFi tersambung?      │
    └──────┬────────────────┘
           │ YA                   │ TIDAK
           ▼                      ▼
  ┌─────────────────┐   ┌──────────────────────────┐
  │ sendTelemetry() │   │ runFuzzyLogic (offline)  │
  │                 │   │ controlPump jika !override│
  │ 1. Baca Sensor  │   └──────────────────────────┘
  │    DHT22, LDR   │
  │    Soil, pH     │
  │                 │
  │ 2. runFuzzy()   │
  │    → crisp output
  │                 │
  │ 3. Kontrol Pompa│
  │    (jika !srv   │
  │     override)   │
  │                 │
  │ 4. HTTP POST    │
  │    → API server │
  │                 │
  │ 5. Parse Response│
  │    a. Update    │
  │       fuzzy cfg │
  │    b. Apply srv │
  │       pump cmd  │
  └─────────────────┘
```

---

## 6. Pin Map ESP32

| Pin | GPIO | Fungsi | Tipe |
|-----|------|--------|------|
| DHTPIN | 4 | DHT22 Data | Digital Input |
| LDR_PIN | 32 | LDR Analog | ADC1_CH4 |
| SOIL_PIN | 34 | Soil Moisture | ADC1_CH6 |
| PH_PIN | 35 | pH Sensor | ADC1_CH7 |
| PUMP_PIN | 2 | Relay Pompa | Digital Output |
| SDA | 21 | I2C Data (LCD) | I2C |
| SCL | 22 | I2C Clock (LCD) | I2C |

---

## 7. Kalibrasi Sensor

### Soil Moisture
```
ADC Kering (udara) = 3200  → 0%
ADC Basah (terendam) = 900 → 100%
Formula: moisture = map(ADC, 3200, 900, 0, 100)
```

### pH Sensor (pH-4502C)
```
Tegangan referensi pH 7.0 = 2.50V
Slope = 0.18 V/unit pH
Formula: pH = 7.0 - ((2.50 - Volt) / 0.18)
ADC → Volt: volt = ADC × (3.3 / 4095)
```

### LDR (Intensitas Cahaya)
```
ADC Terang = 200   → 1000 lux
ADC Gelap  = 4000  → 0 lux
Formula: lux = map(ADC, 200, 4000, 1000, 0)
(mapping terbalik: ADC rendah = lebih terang)
```

---

## 8. Mode Kontrol Pompa

| Mode | Prioritas | Trigger |
|------|-----------|---------|
| **Manual (Server Override)** | Tertinggi | Dashboard user klik toggle |
| **Fuzzy Logic Lokal** | Default | Output fuzzy > 0.5 |
| **Offline Fuzzy** | Fallback | WiFi terputus, fuzzy tetap berjalan |

### Override Logic
```
serverPumpOverride = true  → ESP32 ikuti pump_state dari server
serverPumpOverride = false → ESP32 ikuti hasil fuzzy logic

Override dilepas jika server_pump_state == fuzzy_decision
(server dan lokal setuju → kembalikan ke mode fuzzy)
```

---

## 9. LCD Display (4 Layar, Bergantian 3 Detik)

| Layar | Baris 1 | Baris 2 |
|-------|---------|---------|
| 0 | `Suhu: 28.5°C` | `Hum: 75.2%` |
| 1 | `Tanah: 45.5%` | `pH   : 6.82` |
| 2 | `Cahaya: 320 lux` | `Pompa : ON` |
| 3 | `WiFi: OK` | `Mode: FUZZY` |
