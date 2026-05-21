# SENA — Dokumentasi API & Protokol Komunikasi

## 1. Ringkasan Endpoint

| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| POST | `/api/v1/telemetry` | X-Device-Code | Kirim data sensor dari IoT |
| GET | `/dashboard` | Session (auth) | Halaman dashboard utama |
| GET | `/dashboard/data` | Session (auth) | Polling data JSON real-time |
| GET | `/devices` | Session (auth) | Daftar semua perangkat |
| POST | `/devices` | Session (auth) | Tambah perangkat baru |
| GET | `/devices/{id}/edit` | Session (auth) | Form edit perangkat |
| PUT | `/devices/{id}` | Session (auth) | Update data perangkat |
| DELETE | `/devices/{id}` | Session (auth) | Hapus perangkat |
| POST | `/devices/{id}/toggle-pump` | Session (auth) | Toggle relay pompa |
| GET | `/devices/{id}/fuzzy` | Session (auth) | Form konfigurasi fuzzy |
| PUT | `/devices/{id}/fuzzy` | Session (auth) | Simpan konfigurasi fuzzy |
| GET | `/logs` | Session (auth) | Log telemetri (paginated) |
| GET | `/logs/export` | Session (auth) | Export CSV |
| GET | `/profile` | Session (auth) | Form profil user |
| PATCH | `/profile` | Session (auth) | Update profil |
| DELETE | `/profile` | Session (auth) | Hapus akun |

---

## 2. API IoT: POST `/api/v1/telemetry`

### Autentikasi
Header wajib:
```
X-Device-Code: <UUID device dari dashboard>
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "ph": 6.82,
  "soil_moisture": 45.5,
  "light_intensity": 320.0,
  "temperature": 28.5,
  "humidity": 75.2
}
```

### Validasi
| Field | Aturan |
|-------|--------|
| ph | nullable, numeric, antara 0–14 |
| soil_moisture | nullable, numeric, antara 0–100 |
| light_intensity | nullable, numeric |
| temperature | nullable, numeric |
| humidity | nullable, numeric, antara 0–100 |

### Response 201 — Sukses
```json
{
  "status": "success",
  "message": "Telemetry data saved correctly",
  "data": {
    "id": 42,
    "device_id": 3,
    "ph": 6.82,
    "soil_moisture": 45.5,
    "light_intensity": 320.0,
    "temperature": 28.5,
    "humidity": 75.2,
    "created_at": "2026-05-20T07:30:00+07:00",
    "updated_at": "2026-05-20T07:30:00+07:00"
  },
  "device_controls": {
    "pump_state": false
  },
  "fuzzy_config": {
    "moisture_kering_max": 40.0,
    "moisture_normal_min": 30.0,
    "moisture_normal_max": 70.0,
    "moisture_basah_min":  60.0,
    "ph_asam_max":         6.5,
    "ph_netral_min":       6.0,
    "ph_netral_max":       7.5,
    "ph_basa_min":         7.0
  }
}
```

### Response Error
| HTTP | Kondisi |
|------|---------|
| 401 | Header X-Device-Code tidak ada |
| 403 | Device code tidak ditemukan atau device nonaktif |
| 422 | Validasi payload gagal |
| 500 | Server error (lihat storage/logs/laravel.log) |

---

## 3. API Dashboard: GET `/dashboard/data`

### Autentikasi
Cookie session (harus sudah login via web).

### Query Parameter
| Parameter | Wajib | Keterangan |
|-----------|-------|------------|
| device_id | Tidak | ID device yang dipilih. Default: device pertama user |

### Response 200
```json
{
  "success": true,
  "device_id": 3,
  "device_status": true,
  "latest": {
    "ph": 6.82,
    "soil_moisture": 45.5,
    "light_intensity": 320.0,
    "temperature": 28.5,
    "humidity": 75.2,
    "time_ago": "2 minutes ago",
    "timestamp": "14:28"
  },
  "chart": {
    "labels": ["14:05", "14:10", "14:15", "..."],
    "temperature": [27.5, 28.0, 28.5, "..."],
    "humidity": [74.0, 75.0, 75.2, "..."],
    "soil_moisture": [44.0, 45.0, 45.5, "..."],
    "ph": [6.75, 6.80, 6.82, "..."],
    "light_intensity": [310.0, 315.0, 320.0, "..."]
  }
}
```

---

## 4. API Toggle Pompa: POST `/devices/{device}/toggle-pump`

### Request Body
```json
{ "pump_state": true }
```

### Response 200
```json
{ "success": true, "pump_state": true }
```

> Setelah DB diperbarui, server mempublish perintah MQTT ke broker.

---

## 5. Protokol MQTT

### Konfigurasi Broker
| Parameter | Nilai |
|-----------|-------|
| Broker | `broker.hivemq.com` (default, konfigurasi via .env) |
| Port | 1883 (plain) / 8883 (TLS) |
| Client ID | Dikonfigurasi via `config/mqtt.php` |
| Auth | Username/Password opsional (dari .env) |

### Topic Struktur

```
sena/
  tx/<device_code>   ← Pesan DARI ESP32 ke server (reserved)
  rx/<device_code>   ← Pesan DARI server KE ESP32 (command)
```

### Payload: Toggle Pompa (Server → ESP32)
```json
{
  "action": "toggle_pump",
  "pump_state": 1
}
```

### Payload: Update Fuzzy Config (Server → ESP32)
```json
{
  "action": "update_fuzzy",
  "moisture_kering_max": 40.0,
  "moisture_normal_min": 30.0,
  "moisture_normal_max": 70.0,
  "moisture_basah_min":  60.0,
  "ph_asam_max":         6.5,
  "ph_netral_min":       6.0,
  "ph_netral_max":       7.5,
  "ph_basa_min":         7.0
}
```

---

## 6. Sequence Diagram: Registrasi & Onboarding Device

```
User Browser          Laravel Web         Database
    │                     │                   │
    │── POST /register ───►│                   │
    │   {name,email,pwd}   │                   │
    │                     │── Create user ─────►│
    │◄── Redirect login ──│                   │
    │                     │                   │
    │── POST /login ───────►│                   │
    │                     │── Auth check ───────►│
    │◄── Redirect /dashboard                   │
    │                     │                   │
    │── GET /devices/create►│                   │
    │◄── Form HTML ─────── │                   │
    │                     │                   │
    │── POST /devices ─────►│                   │
    │   {device_name,loc}  │── Generate UUID ───│
    │                     │── Create device ───►│
    │◄── Redirect devices/ │                   │
    │                     │                   │
    │── GET /devices ───────►│                   │
    │◄── Tampil device_code │                   │
    │   (copy ke firmware) │                   │
```

---

## 7. Sequence Diagram: Siklus Telemetri Lengkap

```
ESP32              Laravel API          Database         Dashboard Browser
  │                    │                   │                    │
  │ (setiap 10 detik)  │                   │                    │
  │── Baca semua sensor│                   │                    │
  │── Jalankan Fuzzy ──│                   │                    │
  │── Kontrol Pompa ───│                   │                    │
  │                    │                   │                    │
  │── POST /api/v1/telemetry               │                    │
  │   Header: X-Device-Code               │                    │
  │   Body: {sensor data}                 │                    │
  │                    │── Find Device ────►│                    │
  │                    │── Create Log ──────►│                    │
  │                    │── Update heartbeat─►│                    │
  │                    │── Load FuzzyConfig─►│                    │
  │◄── 201 Response ───│                   │                    │
  │   {device_controls,│                   │                    │
  │    fuzzy_config}   │                   │                    │
  │── Update fuzzy param                  │                    │
  │── Apply pump override                 │                    │
  │                    │                   │                    │
  │                    │                   │   (setiap 5 detik) │
  │                    │                   │◄── GET /dashboard/data
  │                    │                   │── Query last 24 logs
  │                    │                   │──────────────────►│
  │                    │                   │   JSON response    │
  │                    │                   │   Update charts ──►│
```
