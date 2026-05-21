# SENA — Dokumentasi Controller & Routing

## 1. UML Controller Diagram

```
┌──────────────────────────────────────────────────────────────────┐
│                     HTTP LAYER                                   │
└──────────┬───────────────────────────────────────────────────────┘
           │
     ┌─────▼──────────────────────────────────────────────┐
     │           App\Http\Controllers\Controller           │
     │                   (Base Class)                      │
     └────┬──────────┬──────────┬─────────────┬────────────┘
          │          │          │             │
   ┌──────▼───┐ ┌────▼───┐ ┌───▼────┐ ┌─────▼──────────┐
   │Dashboard │ │Device  │ │Profile │ │SystemLog       │
   │Controller│ │Control.│ │Control.│ │Controller      │
   └──────────┘ └────────┘ └────────┘ └────────────────┘
          │
   ┌──────▼────────────┐   ┌──────────────────────────────┐
   │FuzzyConfig        │   │ Api\TelemetryController      │
   │Controller         │   │ (namespace: Controllers\Api) │
   └───────────────────┘   └──────────────────────────────┘
```

---

## 2. DashboardController

**Namespace:** `App\Http\Controllers\DashboardController`

| Method | Route | Return |
|--------|-------|--------|
| `index(Request)` | GET /dashboard | View `dashboard` |
| `data(Request)` | GET /dashboard/data | JSON response |

### `index()`
- Load semua device milik user (eager load `logs`)
- Deteksi active device dari query param `?device_id=X`
- Ambil 24 log terbaru untuk chart
- Pass ke view: `$devices`, `$activeDevice`, `$recentLogs`, `$latestLog`

### `data()`
- Endpoint JSON polling (dipanggil JS setiap 5 detik)
- Return latest sensor reading + chart data (last 24 logs)
- Format: `{success, device_id, device_status, latest{...}, chart{...}}`

---

## 3. DeviceController

**Namespace:** `App\Http\Controllers\DeviceController`

| Method | Route | HTTP | Deskripsi |
|--------|-------|------|-----------|
| `index()` | /devices | GET | List paginated (10/hal) |
| `create()` | /devices/create | GET | Form tambah device |
| `store()` | /devices | POST | Simpan device baru |
| `edit()` | /devices/{id}/edit | GET | Form edit |
| `update()` | /devices/{id} | PUT | Update device |
| `destroy()` | /devices/{id} | DELETE | Hapus device + foto |
| `togglePump()` | /devices/{id}/toggle-pump | POST | Toggle relay + MQTT publish |

### Aturan Akses
Setiap method memvalidasi `$device->user_id === auth()->id()` sebelum operasi. Jika tidak cocok → `abort(403)`.

### `store()` — Validasi
```php
'device_name' => 'required|string|max:255'
'location'    => 'nullable|string|max:255'
'status'      => 'boolean'
'photo'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048'
```
UUID di-generate otomatis via `Str::uuid()`.

### `togglePump()` — Alur
1. Validasi kepemilikan device
2. Update `pump_state` di database
3. Connect MQTT broker
4. Publish JSON ke topic `sena/rx/<device_code>`
5. Disconnect MQTT
6. Return JSON `{success, pump_state}`

---

## 4. FuzzyConfigController

**Namespace:** `App\Http\Controllers\FuzzyConfigController`

| Method | Route | Deskripsi |
|--------|-------|-----------|
| `edit(Device)` | GET /devices/{id}/fuzzy | Form konfigurasi fuzzy |
| `update(Request, Device)` | PUT /devices/{id}/fuzzy | Simpan + push MQTT |

### `update()` — Validasi
```php
'moisture_kering_max' => 'required|numeric|min:0|max:100'
'moisture_normal_min' => 'required|numeric|min:0|max:100'
'moisture_normal_max' => 'required|numeric|min:0|max:100'
'moisture_basah_min'  => 'required|numeric|min:0|max:100'
'ph_asam_max'         => 'required|numeric|min:0|max:14'
'ph_netral_min'       => 'required|numeric|min:0|max:14'
'ph_netral_max'       => 'required|numeric|min:0|max:14'
'ph_basa_min'         => 'required|numeric|min:0|max:14'
```

Setelah disimpan ke DB, config di-push via MQTT ke ESP32 dengan `action: update_fuzzy`.

---

## 5. SystemLogController

**Namespace:** `App\Http\Controllers\SystemLogController`

| Method | Route | Deskripsi |
|--------|-------|-----------|
| `index(Request)` | GET /logs | Tabel log (paginated 20) |
| `export(Request)` | GET /logs/export | Download CSV |

### Filter
Keduanya mendukung query param `?device_id=X` untuk filter per device.

### `export()` — CSV Header
```
Timestamp | Device Name | Temperature (C) | Humidity (%) | Soil Moisture (%) | Light Intensity (Lux) | pH
```

---

## 6. ProfileController

**Namespace:** `App\Http\Controllers\ProfileController`

| Method | Route | HTTP | Deskripsi |
|--------|-------|------|-----------|
| `edit()` | /profile | GET | Form profil |
| `update()` | /profile | PATCH | Update nama/email/avatar |
| `destroy()` | /profile | DELETE | Hapus akun & avatar |

### Avatar Logic
- Upload → simpan ke `storage/public/avatars/`
- Hapus avatar lama otomatis sebelum upload baru
- Flag `remove_avatar` dalam request → hapus tanpa ganti
- Fallback ke `ui-avatars.com` jika tidak ada foto

---

## 7. Api\TelemetryController

**Namespace:** `App\Http\Controllers\Api\TelemetryController`

| Method | Route | Deskripsi |
|--------|-------|-----------|
| `store(Request)` | POST /api/v1/telemetry | Ingest data sensor IoT |

### Alur Internal
```
1. Ambil X-Device-Code dari header (atau body)
2. Cari Device berdasarkan device_code
3. Validasi device aktif (status = true)
4. Validate sensor payload
5. Simpan DeviceLog
6. Update last_seen_at (heartbeat)
7. firstOrCreate FuzzyConfig
8. Return 201 dengan data + device_controls + fuzzy_config
```

---

## 8. Routing Tree Lengkap

```
/
├── GET  /                    → redirect ke /dashboard atau /login
│
├── [Auth Routes - Breeze]
│   ├── GET  /login
│   ├── POST /login
│   ├── POST /logout
│   ├── GET  /register
│   └── POST /register
│
├── [Middleware: auth, verified]
│   ├── GET  /dashboard                      → DashboardController@index
│   └── GET  /dashboard/data                 → DashboardController@data
│
├── [Middleware: auth]
│   ├── GET    /profile                      → ProfileController@edit
│   ├── PATCH  /profile                      → ProfileController@update
│   ├── DELETE /profile                      → ProfileController@destroy
│   │
│   ├── [Resource: devices]
│   │   ├── GET    /devices                  → DeviceController@index
│   │   ├── GET    /devices/create           → DeviceController@create
│   │   ├── POST   /devices                  → DeviceController@store
│   │   ├── GET    /devices/{device}/edit    → DeviceController@edit
│   │   ├── PUT    /devices/{device}         → DeviceController@update
│   │   └── DELETE /devices/{device}         → DeviceController@destroy
│   │
│   ├── POST /devices/{device}/toggle-pump   → DeviceController@togglePump
│   ├── GET  /devices/{device}/fuzzy         → FuzzyConfigController@edit
│   ├── PUT  /devices/{device}/fuzzy         → FuzzyConfigController@update
│   │
│   ├── GET  /logs                           → SystemLogController@index
│   └── GET  /logs/export                    → SystemLogController@export
│
└── [API Routes - prefix: /api]
    ├── GET  /api/user                       → Sanctum (auth:sanctum)
    └── [prefix: /api/v1]
        └── POST /api/v1/telemetry           → Api\TelemetryController@store
```
