# SENA — Database & UML Diagram

## 1. Entity Relationship Diagram (ERD)

```
┌──────────────────────────────────┐
│              users               │
├──────────────────────────────────┤
│ PK  id              BIGINT       │
│     name            VARCHAR(255) │
│     email           VARCHAR(255) │◄── UNIQUE
│     email_verified_at TIMESTAMP  │
│     password        VARCHAR(255) │
│     avatar          VARCHAR(255) │ nullable
│     remember_token  VARCHAR(100) │
│     created_at      TIMESTAMP    │
│     updated_at      TIMESTAMP    │
└────────────────┬─────────────────┘
                 │ 1
                 │ hasMany
                 │ N
┌────────────────▼─────────────────┐
│             devices              │
├──────────────────────────────────┤
│ PK  id              BIGINT       │
│ FK  user_id         BIGINT       │──► users.id (CASCADE DELETE)
│     device_name     VARCHAR(255) │
│     device_code     VARCHAR(255) │◄── UNIQUE (UUID)
│     location        VARCHAR(255) │ nullable
│     status          BOOLEAN      │ default: true
│     pump_state      BOOLEAN      │ default: false
│     control_mode    VARCHAR(255) │ nullable
│     rssi            INT          │ nullable
│     last_seen_at    TIMESTAMP    │ nullable
│     photo           VARCHAR(255) │ nullable
│     created_at      TIMESTAMP    │
│     updated_at      TIMESTAMP    │
└──────────┬──────────────┬────────┘
           │ 1            │ 1
      hasMany          hasOne
           │ N            │ 1
┌──────────▼───┐  ┌───────▼────────────────────────┐
│ device_logs  │  │         fuzzy_configs           │
├──────────────┤  ├────────────────────────────────┤
│ PK id BIGINT │  │ PK id              BIGINT       │
│ FK device_id │  │ FK device_id       BIGINT       │──► devices.id (CASCADE)
│    ph        │  │    moisture_kering_max FLOAT    │ default: 40
│    soil_     │  │    moisture_normal_min FLOAT    │ default: 30
│    moisture  │  │    moisture_normal_max FLOAT    │ default: 70
│    light_    │  │    moisture_basah_min  FLOAT    │ default: 60
│    intensity │  │    ph_asam_max         FLOAT    │ default: 6.5
│    tempera.. │  │    ph_netral_min       FLOAT    │ default: 6.0
│    humidity  │  │    ph_netral_max       FLOAT    │ default: 7.5
│    created_at│  │    ph_basa_min         FLOAT    │ default: 7.0
│    updated_at│  │    created_at      TIMESTAMP    │
└──────────────┘  │    updated_at      TIMESTAMP    │
                  └────────────────────────────────┘
```

---

## 2. Schema Detail Tiap Tabel

### Table: `users`

| Kolom | Tipe | Constraint | Keterangan |
|-------|------|------------|------------|
| id | BIGINT UNSIGNED | PK, AI | Auto increment |
| name | VARCHAR(255) | NOT NULL | Nama lengkap |
| email | VARCHAR(255) | UNIQUE, NOT NULL | Email login |
| email_verified_at | TIMESTAMP | NULL | Verifikasi email |
| password | VARCHAR(255) | NOT NULL | Hash bcrypt |
| avatar | VARCHAR(255) | NULL | Path storage/avatars/ |
| remember_token | VARCHAR(100) | NULL | Session token |
| created_at / updated_at | TIMESTAMP | - | Otomatis Laravel |

### Table: `devices`

| Kolom | Tipe | Constraint | Keterangan |
|-------|------|------------|------------|
| id | BIGINT UNSIGNED | PK, AI | - |
| user_id | BIGINT UNSIGNED | FK → users | Pemilik device |
| device_name | VARCHAR(255) | NOT NULL | Nama tampilan |
| device_code | VARCHAR(255) | UNIQUE | UUID otomatis saat create |
| location | VARCHAR(255) | NULL | Lokasi fisik |
| status | BOOLEAN | DEFAULT true | Aktif/nonaktif |
| pump_state | BOOLEAN | DEFAULT false | Status relay pompa |
| control_mode | VARCHAR(255) | NULL | Mode kontrol |
| rssi | INT | NULL | Kekuatan sinyal WiFi |
| last_seen_at | TIMESTAMP | NULL | Heartbeat terakhir |
| photo | VARCHAR(255) | NULL | Path storage/devices/ |

### Table: `device_logs`

| Kolom | Tipe | Constraint | Keterangan |
|-------|------|------------|------------|
| id | BIGINT UNSIGNED | PK, AI | - |
| device_id | BIGINT UNSIGNED | FK → devices | Perangkat pengirim |
| ph | DECIMAL(5,2) | NULL | Nilai pH tanah (0–14) |
| soil_moisture | DECIMAL(5,2) | NULL | Kelembapan tanah (0–100%) |
| light_intensity | DECIMAL(8,2) | NULL | Intensitas cahaya (lux) |
| temperature | DECIMAL(5,2) | NULL | Suhu udara (°C) |
| humidity | DECIMAL(5,2) | NULL | Kelembapan udara (0–100%) |
| created_at | TIMESTAMP | INDEX | Waktu pengiriman |

### Table: `fuzzy_configs`

| Kolom | Tipe | Default | Keterangan |
|-------|------|---------|------------|
| moisture_kering_max | FLOAT | 40 | Batas atas zona "Kering" |
| moisture_normal_min | FLOAT | 30 | Batas bawah zona "Normal" |
| moisture_normal_max | FLOAT | 70 | Batas atas zona "Normal" |
| moisture_basah_min | FLOAT | 60 | Batas bawah zona "Basah" |
| ph_asam_max | FLOAT | 6.5 | Batas atas zona "Asam" |
| ph_netral_min | FLOAT | 6.0 | Batas bawah zona "Netral" |
| ph_netral_max | FLOAT | 7.5 | Batas atas zona "Netral" |
| ph_basa_min | FLOAT | 7.0 | Batas bawah zona "Basa" |

---

## 3. UML Class Diagram (Eloquent Models)

```
┌─────────────────────────────────────────┐
│              User                       │
├─────────────────────────────────────────┤
│ - fillable: [name, email, pwd, avatar]  │
│ - hidden: [password, remember_token]    │
│ - casts: {email_verified_at: datetime,  │
│           password: hashed}             │
├─────────────────────────────────────────┤
│ + getAvatarUrlAttribute(): string       │
│ + devices(): HasMany                    │
└────────────────────┬────────────────────┘
                     │ 1..* (HasMany)
                     │
┌────────────────────▼────────────────────┐
│              Device                     │
├─────────────────────────────────────────┤
│ - fillable: [user_id, device_name,      │
│   device_code, location, status,        │
│   pump_state, photo]                    │
│ - casts: {status: boolean,              │
│           pump_state: boolean,          │
│           last_seen_at: datetime}       │
├─────────────────────────────────────────┤
│ + getPhotoUrlAttribute(): string        │
│ + getIsOnlineAttribute(): bool          │
│   (online jika last_seen_at <= 5 menit) │
│ + user(): BelongsTo                     │
│ + logs(): HasMany                       │
│ + fuzzyConfig(): HasOne                 │
└──────┬───────────────────────┬──────────┘
       │ 0..* (HasMany)        │ 0..1 (HasOne)
       │                       │
┌──────▼──────────┐  ┌─────────▼──────────────────────┐
│   DeviceLog     │  │         FuzzyConfig             │
├─────────────────┤  ├────────────────────────────────┤
│ - fillable:     │  │ - fillable:                     │
│   [device_id,   │  │   [device_id,                   │
│    ph,          │  │    moisture_kering_max,          │
│    soil_moist.. │  │    moisture_normal_min,          │
│    light_int.., │  │    moisture_normal_max,          │
│    temperature, │  │    moisture_basah_min,           │
│    humidity]    │  │    ph_asam_max,                  │
├─────────────────┤  │    ph_netral_min,                │
│ + device():     │  │    ph_netral_max,                │
│   BelongsTo     │  │    ph_basa_min]                  │
└─────────────────┘  ├────────────────────────────────┤
                     │ + device(): BelongsTo           │
                     └────────────────────────────────┘
```

---

## 4. Migration Timeline

| Urutan | File Migration | Aksi |
|--------|----------------|------|
| 1 | `0001_01_01_000000_create_users_table` | Buat tabel users, sessions, password_reset_tokens |
| 2 | `0001_01_01_000001_create_cache_table` | Cache store di DB |
| 3 | `0001_01_01_000002_create_jobs_table` | Queue jobs |
| 4 | `2026_03_05_095653_create_devices_table` | Tabel devices dasar |
| 5 | `2026_03_05_095654_create_device_logs_table` | Tabel telemetri sensor |
| 6 | `2026_03_05_100913_create_personal_access_tokens_table` | Sanctum tokens |
| 7 | `2026_03_05_103737_add_pump_state_to_devices` | Kolom pump_state |
| 8 | `2026_04_18_174609_create_fuzzy_configs_table` | Konfigurasi fuzzy logic |
| 9 | `2026_04_19_053409_add_last_seen_at_to_devices` | Heartbeat kolom |
| 10 | `2026_04_19_121631_add_control_mode_and_rssi` | Mode & RSSI kolom |
| 11 | `2026_05_12_150918_add_photo_to_devices` | Foto device |
| 12 | `2026_05_12_151155_add_avatar_to_users` | Foto profil user |
