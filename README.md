# 🌿 Smart Farming IoT Monitoring System

![GitHub License](https://img.shields.io/badge/license-MIT-blue.svg)
![Laravel Version](https://img.shields.io/badge/laravel-11.x-red.svg)
![Tailwind CSS](https://img.shields.io/badge/tailwind-3.x-38b2ac.svg)
![Design](https://img.shields.io/badge/design-Glassmorphism-purple.svg)

A modern, visually stunning IoT monitoring platform for Smart Farming. Built with **Laravel 11** and a custom **Glassmorphism** design system, this platform allows users to track sensor data from multiple IoT devices in real-time, control equipment, and analyze historical trends.

---

## ✨ Key Features

### 🖥️ Real-time Glassmorphism Dashboard
- **Dynamic Gauges:** 5 real-time sensor trackers (pH Tanah, Kelembaban Tanah, Cahaya, Suhu Udara, Kelembaban Udara).
- **Interactive Charts:** Historical trend-line using Chart.js with dual Y-axis scaling for high-value Light (Lux) data.
- **AJAX Polling:** Dashboard data updates every 3 seconds without refreshing the page.
- **Device Switcher:** Seamlessly switch between multiple registered IoT devices.

### 📡 Hardware & Firmware Support
- **ESP32 Ready:** Optimized firmware for ESP32 microcontrollers with HTTPS secure communication.
- **Sensor Integration:** Native support for:
  - **DHT22 (AM2302):** High-precision Air Temperature & Humidity.
  - **BH1750 (GY-302):** Digital I2C Light Intensity sensor.
  - **Analog Soil Moisture:** Plant hydration level monitoring.
  - **Analog pH Sensor:** Soil acidity/alkalinity tracking.
- **SSL Flexibility:** Uses `setInsecure()` logic for easy deployment on HTTPS domains without manual SSL certificate management.

### 🎮 Smart Controls
- **Persisted Pump Control:** A togglable water pump switch with state persistence in the database.
- **Bi-directional Sync:** Commands sent from the dashboard are received by the ESP32 in real-time during telemetry pings.
- **Visual Feedback:** Animated spinning icons and glow effects when equipment is active.

### � Management & Analysis
- **Device CRUD:** Full management of IoT devices with auto-generated UUIDs for API security.
- **System Logs:** Comprehensive telemetry logs with filtering and search capabilities.
- **Excel/CSV Export:** One-click data export for further analysis in Microsoft Excel, with smart filtering by device.

---

## 🛠️ Tech Stack

- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** Tailwind CSS, Glassmorphism UI
- **Charts:** Chart.js
- **Icons:** Lucide Icons
- **Database:** SQLite (Default)
- **Authentication:** Laravel Breeze

---

## 🚀 Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourusername/smart-farming-iot.git
   cd smart-farming-iot
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Environment Setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Migration:**
   ```bash
   # Make sure database/database.sqlite exists
   php artisan migrate
   ```

5. **Start the server:**
   ```bash
   php artisan serve
   ```

---

## 🛰️ Simulating IoT Data

To see the dashboard in action without a physical device, run the included simulator:

```bash
php simulate.php
```

This will start sending randomized sensor data to your local server every 3 seconds. You can then toggle the **Pump Switch** on the dashboard and see the simulator receive the "ON/OFF" command in real-time.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
