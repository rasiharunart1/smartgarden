# SENA — Panduan Deployment & Konfigurasi

## 1. Persyaratan Server

| Kebutuhan | Minimum |
|-----------|---------|
| PHP | 8.2+ |
| Ekstensi PHP | pdo, pdo_sqlite, mbstring, openssl, tokenizer, xml, ctype, fileinfo |
| Composer | 2.x |
| Node.js | 18+ (untuk build assets) |
| Storage | Writable pada `storage/` dan `bootstrap/cache/` |
| Web Server | Apache (`.htaccess` sudah tersedia) / Nginx |

---

## 2. Environment Variables (`.env`)

### Konfigurasi Wajib

```env
APP_NAME="SENA Smart Garden"
APP_ENV=production
APP_KEY=base64:...        # Generate: php artisan key:generate
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=sqlite
# Atau MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=sena_db
# DB_USERNAME=root
# DB_PASSWORD=secret

FILESYSTEM_DISK=public
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# MQTT Broker
MQTT_HOST=broker.hivemq.com
MQTT_PORT=1883
MQTT_CLIENT_ID=sena-server-${APP_NAME}
MQTT_USERNAME=
MQTT_PASSWORD=
```

---

## 3. Langkah Instalasi

```bash
# 1. Clone / upload project
git clone <repo> /path/to/sena
cd /path/to/sena

# 2. Install PHP dependencies
composer install --optimize-autoloader --no-dev

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Jalankan migrasi database
php artisan migrate --force

# 5. Buat symlink storage
php artisan storage:link

# 6. Build frontend assets
npm install
npm run build

# 7. Optimasi cache (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Set permission
chmod -R 775 storage bootstrap/cache
```

---

## 4. Konfigurasi MQTT (`config/mqtt.php`)

> File ini harus dibuat jika belum ada. Dibaca oleh `DeviceController` dan `FuzzyConfigController`.

```php
<?php
return [
    'host'      => env('MQTT_HOST', 'broker.hivemq.com'),
    'port'      => env('MQTT_PORT', 1883),
    'client_id' => env('MQTT_CLIENT_ID', 'sena-server'),
    'username'  => env('MQTT_USERNAME', ''),
    'password'  => env('MQTT_PASSWORD', ''),
];
```

---

## 5. Konfigurasi Firmware ESP32

Edit bagian User Configuration di `esp32_sena_full.ino`:

```cpp
const char* WIFI_SSID     = "NAMA_WIFI_ANDA";
const char* WIFI_PASSWORD = "PASSWORD_WIFI";
const char* SERVER_URL    = "https://yourdomain.com/api/v1/telemetry";
const char* DEVICE_CODE   = "UUID-DARI-DASHBOARD"; // Salin dari halaman /devices

const unsigned long TELEMETRY_INTERVAL = 10000; // 10 detik
```

### Library yang Wajib Diinstall (Arduino Library Manager)
- `DHT sensor library` by Adafruit
- `Adafruit Unified Sensor` by Adafruit
- `LiquidCrystal I2C` by Frank de Brabander
- `ArduinoJson` by Benoit Blanchon

---

## 6. Diagram Deployment

```
┌─────────────────────────────────────────────────────┐
│                  Production Server                  │
│                                                     │
│  ┌─────────────┐    ┌──────────────┐                │
│  │   Apache /  │    │  Laravel 11  │                │
│  │   Nginx     │───►│  (PHP 8.2)   │                │
│  │   :80/:443  │    │              │                │
│  └─────────────┘    └──────┬───────┘                │
│                            │                        │
│                   ┌────────▼───────┐                │
│                   │   SQLite DB    │                │
│                   │ database/      │                │
│                   │ database.sqlite│                │
│                   └────────────────┘                │
│                                                     │
│  ┌──────────────────────────────────┐               │
│  │ storage/app/public/              │               │
│  │   avatars/   ← foto user         │               │
│  │   devices/   ← foto device       │               │
│  │ (linked → public/storage/)       │               │
│  └──────────────────────────────────┘               │
└─────────────────────────────────────────────────────┘
           ▲                        ▲
           │ HTTPS                  │ MQTT (port 1883/8883)
           │                        │
    ┌──────┴──────┐        ┌────────┴──────────────┐
    │   Browser   │        │  MQTT Broker (HiveMQ) │
    │  (Dashboard)│        │  broker.hivemq.com    │
    └─────────────┘        └───────────────────────┘
                                    ▲
                                    │ MQTT Subscribe
                                    │
                           ┌────────┴──────┐
                           │    ESP32      │
                           │  (lapangan)   │
                           └───────────────┘
```

---

## 7. Security Checklist

| Item | Status | Keterangan |
|------|--------|------------|
| HTTPS enforced | ✅ | `wifiClient.setInsecure()` hanya untuk dev |
| Device autentikasi | ✅ | X-Device-Code UUID unik per device |
| User autentikasi | ✅ | Laravel Breeze (session-based) |
| CSRF Protection | ✅ | Semua form web dilindungi CSRF token |
| Ownership check | ✅ | Device CRUD cek `user_id === auth->id()` |
| Password hashing | ✅ | Bcrypt via Laravel `hashed` cast |
| File upload validation | ✅ | mimes: jpeg,png,jpg,webp; max 2MB |
| SQL Injection | ✅ | Eloquent ORM + prepared statements |
| SSL ESP32 production | ⚠️ | Ganti `setInsecure()` dengan `setCACert()` |
| MQTT Auth production | ⚠️ | Tambahkan username/password MQTT |

---

## 8. Troubleshooting Umum

### ESP32 → HTTP 401
- Pastikan `DEVICE_CODE` di firmware = UUID yang ada di dashboard
- Pastikan device `status = true` (aktif) di halaman /devices

### ESP32 → HTTP 403
- Device ditemukan tapi dinonaktifkan. Aktifkan kembali via dashboard.

### Dashboard tidak update
- Cek apakah `/dashboard/data` mengembalikan response sukses
- Cek `last_seen_at` di database — jika > 5 menit, device dianggap offline

### MQTT Publish gagal
- Cek `storage/logs/laravel.log` untuk error MQTT
- Verifikasi `MQTT_HOST`, `MQTT_PORT`, dan kredensial di `.env`
- Pompa tetap terupdate via DB meskipun MQTT gagal

### Foto tidak muncul
- Pastikan `php artisan storage:link` sudah dijalankan
- Cek direktori `storage/app/public/` memiliki permission write
