#!/usr/bin/env python3
"""
╔══════════════════════════════════════════════════════════╗
║         SENA Smart Garden - IoT API Tester (Python)      ║
║  Simulates ESP32 sending telemetry to the Laravel API    ║
╚══════════════════════════════════════════════════════════╝

Usage:
    python iot_tester.py                    # Interactive menu
    python iot_tester.py --mode single      # Single shot test
    python iot_tester.py --mode loop        # Continuous simulation (Ctrl+C to stop)
    python iot_tester.py --mode stress      # Stress test (10 rapid requests)
    python iot_tester.py --mode mqtt        # Test MQTT publish (needs paho-mqtt)

Requirements:
    pip install requests colorama
    pip install paho-mqtt   (optional, for MQTT test)
"""

import requests
import json
import time
import random
import argparse
import sys
from datetime import datetime

# ── Try importing colorama (optional, for colored output) ──
try:
    from colorama import Fore, Style, init as colorama_init
    colorama_init(autoreset=True)
    HAS_COLOR = True
except ImportError:
    HAS_COLOR = False

# ── Try importing paho-mqtt (optional) ──
try:
    import paho.mqtt.client as mqtt
    HAS_MQTT = True
except ImportError:
    HAS_MQTT = False


# ══════════════════════════════════════════════════════════
#  CONFIGURATION  ── Edit sesuai kebutuhan
# ══════════════════════════════════════════════════════════
CONFIG = {
    # URL target API (ganti ke URL produksi jika perlu)
    "BASE_URL_LOCAL":      "http://localhost:8000",
    "BASE_URL_PRODUCTION": "https://smartgarden.wuaze.com",

    # Device Code (UUID dari dashboard SENA)
    "DEVICE_CODE": "837c6151-3302-4a8f-8773-e5884d02c340",

    # Interval antar kiriman (detik)
    "INTERVAL_SECONDS": 5,

    # Stress test: jumlah request
    "STRESS_COUNT": 10,

    # MQTT (HiveMQ public broker)
    "MQTT_HOST":   "broker.hivemq.com",
    "MQTT_PORT":   1883,
    "MQTT_CLIENT": "sena_python_tester",
}


# ══════════════════════════════════════════════════════════
#  HELPER: Coloured Print
# ══════════════════════════════════════════════════════════
def c(text, color=""):
    if not HAS_COLOR or not color:
        return str(text)
    colors = {
        "green":  Fore.GREEN,
        "red":    Fore.RED,
        "yellow": Fore.YELLOW,
        "cyan":   Fore.CYAN,
        "blue":   Fore.BLUE,
        "magenta":Fore.MAGENTA,
        "white":  Fore.WHITE,
        "bold":   Style.BRIGHT,
    }
    return f"{colors.get(color,'')}{text}{Style.RESET_ALL}"


def print_header():
    print()
    print(c("╔══════════════════════════════════════════════════════════╗", "cyan"))
    print(c("║         SENA Smart Garden — IoT API Tester (Python)      ║", "cyan"))
    print(c("╚══════════════════════════════════════════════════════════╝", "cyan"))
    print()


def print_separator():
    print(c("─" * 62, "blue"))


def ts():
    """Timestamp saat ini."""
    return datetime.now().strftime("%H:%M:%S")


# ══════════════════════════════════════════════════════════
#  SENSOR DATA GENERATOR (simulasi nilai realistis)
# ══════════════════════════════════════════════════════════
def generate_sensor_data(scenario="normal"):
    """
    Menghasilkan data sensor yang mensimulasikan kondisi kebun.
    scenario: 'normal' | 'dry' | 'wet' | 'acidic' | 'random'
    """
    scenarios = {
        "normal": {
            "ph":              round(random.uniform(6.2, 7.2), 2),
            "soil_moisture":   round(random.uniform(45, 70), 1),
            "light_intensity": round(random.uniform(400, 900), 1),
            "temperature":     round(random.uniform(24.0, 30.0), 1),
            "humidity":        round(random.uniform(55, 75), 1),
        },
        "dry": {
            "ph":              round(random.uniform(6.0, 7.0), 2),
            "soil_moisture":   round(random.uniform(5, 30), 1),    # Sangat kering → pompa ON
            "light_intensity": round(random.uniform(700, 1000), 1),
            "temperature":     round(random.uniform(30.0, 38.0), 1),
            "humidity":        round(random.uniform(30, 50), 1),
        },
        "wet": {
            "ph":              round(random.uniform(6.5, 7.5), 2),
            "soil_moisture":   round(random.uniform(75, 95), 1),   # Sangat basah → pompa OFF
            "light_intensity": round(random.uniform(100, 400), 1),
            "temperature":     round(random.uniform(20.0, 26.0), 1),
            "humidity":        round(random.uniform(75, 95), 1),
        },
        "acidic": {
            "ph":              round(random.uniform(4.5, 5.5), 2),  # pH asam
            "soil_moisture":   round(random.uniform(35, 55), 1),
            "light_intensity": round(random.uniform(300, 700), 1),
            "temperature":     round(random.uniform(25.0, 32.0), 1),
            "humidity":        round(random.uniform(60, 80), 1),
        },
        "random": {
            "ph":              round(random.uniform(4.0, 9.0), 2),
            "soil_moisture":   round(random.uniform(0, 100), 1),
            "light_intensity": round(random.uniform(0, 1000), 1),
            "temperature":     round(random.uniform(15.0, 40.0), 1),
            "humidity":        round(random.uniform(20, 100), 1),
        },
    }
    return scenarios.get(scenario, scenarios["normal"])


# ══════════════════════════════════════════════════════════
#  CORE: Kirim Telemetri ke API
# ══════════════════════════════════════════════════════════
def send_telemetry(base_url, device_code, payload, request_num=1, verify_ssl=False):
    """
    POST /api/v1/telemetry
    Header: X-Device-Code: <device_code>
    Body  : JSON sensor data
    """
    endpoint = f"{base_url}/api/v1/telemetry"
    headers  = {
        "Content-Type":  "application/json",
        "Accept":        "application/json",
        "X-Device-Code": device_code,
    }

    print_separator()
    print(f"{c(f'[{ts()}]', 'yellow')} {c(f'Request #{request_num}', 'bold')} → {c(endpoint, 'cyan')}")
    print(f"  Device Code : {c(device_code, 'magenta')}")
    print(f"  Payload     : {c(json.dumps(payload), 'white')}")

    start_time = time.time()
    try:
        response = requests.post(
            endpoint,
            headers=headers,
            json=payload,
            timeout=10,
            verify=verify_ssl,
        )
        elapsed = round((time.time() - start_time) * 1000)

        _print_response(response, elapsed)
        return response

    except requests.exceptions.ConnectionError:
        print(f"  {c('✗ GAGAL', 'red')} — Tidak dapat terhubung ke server.")
        print(f"  {c('Pastikan server Laravel berjalan (php artisan serve)', 'yellow')}")
        return None
    except requests.exceptions.Timeout:
        print(f"  {c('✗ TIMEOUT', 'red')} — Server tidak merespons dalam 10 detik.")
        return None
    except Exception as e:
        print(f"  {c(f'✗ ERROR: {e}', 'red')}")
        return None


def _print_response(response, elapsed_ms):
    """Format dan tampilkan respons dari server."""
    code = response.status_code
    color = "green" if code in (200, 201) else "red"

    print(f"  {c('✔ RESPONS', color)} — HTTP {c(code, color)} ({elapsed_ms} ms)")

    try:
        data = response.json()

        # Status & message
        status  = data.get("status", "-")
        message = data.get("message", "-")
        print(f"  Status      : {c(status, 'green' if status == 'success' else 'red')}")
        print(f"  Message     : {message}")

        # Pump control dari server
        if "device_controls" in data:
            pump = data["device_controls"].get("pump_state", False)
            pump_str = c("ON  ✓ (Pompa MENYALA)", "green") if pump else c("OFF ✗ (Pompa MATI)", "yellow")
            print(f"  Pump State  : {pump_str}")

        # Fuzzy config yang dikirim balik server
        if "fuzzy_config" in data:
            fc = data["fuzzy_config"]
            print(f"  Fuzzy Config: Moisture [{fc.get('moisture_kering_max')} / {fc.get('moisture_normal_min')}–{fc.get('moisture_normal_max')} / {fc.get('moisture_basah_min')}]")
            print(f"                pH       [{fc.get('ph_asam_max')} asam / {fc.get('ph_netral_min')}–{fc.get('ph_netral_max')} netral / {fc.get('ph_basa_min')} basa]")

        # Log data yang tersimpan
        if "data" in data and data["data"]:
            log = data["data"]
            log_id = log.get("id", "-")
            print(f"  Log ID      : #{log_id} (tersimpan di database)")

    except Exception:
        print(f"  Raw Response: {response.text[:300]}")


# ══════════════════════════════════════════════════════════
#  TEST MODES
# ══════════════════════════════════════════════════════════

def mode_single(base_url, device_code):
    """Kirim satu request dengan data acak."""
    print(c("\n▶  Mode: SINGLE SHOT", "bold"))
    payload = generate_sensor_data("normal")
    send_telemetry(base_url, device_code, payload, request_num=1)
    print()


def mode_loop(base_url, device_code):
    """Kirim terus-menerus setiap N detik, seperti ESP32 asli."""
    interval = CONFIG["INTERVAL_SECONDS"]
    scenarios = ["normal", "dry", "wet", "acidic", "normal", "normal"]

    print(c(f"\n▶  Mode: LOOP SIMULASI (interval {interval}s, tekan Ctrl+C untuk berhenti)", "bold"))
    print(c("   Skenario: normal → dry → wet → acidic → ...", "yellow"))

    i = 1
    try:
        while True:
            scenario = scenarios[(i - 1) % len(scenarios)]
            payload  = generate_sensor_data(scenario)
            print(f"\n  {c(f'Skenario: {scenario.upper()}', 'magenta')}")
            send_telemetry(base_url, device_code, payload, request_num=i)
            i += 1
            print(f"\n  {c(f'Menunggu {interval} detik...', 'yellow')}")
            time.sleep(interval)
    except KeyboardInterrupt:
        print(c("\n\n  ⏹  Loop dihentikan oleh pengguna.", "yellow"))


def mode_stress(base_url, device_code):
    """Kirim N request berurutan secepat mungkin."""
    count = CONFIG["STRESS_COUNT"]
    print(c(f"\n▶  Mode: STRESS TEST ({count} request rapid-fire)", "bold"))

    results = {"success": 0, "fail": 0, "total_ms": 0}
    for i in range(1, count + 1):
        payload = generate_sensor_data("random")
        start   = time.time()
        resp    = send_telemetry(base_url, device_code, payload, request_num=i)
        elapsed = (time.time() - start) * 1000

        if resp and resp.status_code in (200, 201):
            results["success"] += 1
        else:
            results["fail"] += 1
        results["total_ms"] += elapsed

    # Ringkasan
    print_separator()
    avg_ms = round(results["total_ms"] / count)
    print(c(f"\n  HASIL STRESS TEST ({count} request):", "bold"))
    print(f"  ✔ Berhasil : {c(results['success'], 'green')}")
    print(f"  ✗ Gagal    : {c(results['fail'], 'red')}")
    print(f"  ⏱ Rata-rata: {c(avg_ms, 'cyan')} ms/request\n")


def mode_validation(base_url, device_code):
    """Test validasi — kirim data yang sengaja invalid."""
    print(c("\n▶  Mode: VALIDASI INPUT", "bold"))
    tests = [
        {"name": "pH di luar range (>14)",       "payload": {"ph": 15.0, "soil_moisture": 50}},
        {"name": "soil_moisture negatif",         "payload": {"ph": 7.0, "soil_moisture": -5}},
        {"name": "Device Code salah (401/403)",   "payload": {"ph": 7.0, "soil_moisture": 50}, "bad_code": True},
        {"name": "Payload kosong",                "payload": {}},
        {"name": "Data valid (harusnya 201)",     "payload": generate_sensor_data("normal")},
    ]

    for i, test in enumerate(tests, 1):
        dc = "INVALID-CODE-00000" if test.get("bad_code") else device_code
        test_name = test.get("name")
        print(f"\n  {c(f'TEST {i}: {test_name}', 'magenta')}")
        send_telemetry(base_url, dc, test["payload"], request_num=i)

    print()


def mode_mqtt(device_code):
    """Publish data sensor ke MQTT broker (HiveMQ)."""
    if not HAS_MQTT:
        print(c("\n✗ paho-mqtt tidak terinstal!", "red"))
        print(c("  Jalankan: pip install paho-mqtt\n", "yellow"))
        return

    host      = CONFIG["MQTT_HOST"]
    port      = CONFIG["MQTT_PORT"]
    client_id = CONFIG["MQTT_CLIENT"]
    topic_tx  = f"sena/tx/{device_code}"  # ESP32 mengirim ke sini
    topic_rx  = f"sena/rx/{device_code}"  # Server kirim perintah ke sini

    print(c(f"\n▶  Mode: MQTT TEST → {host}:{port}", "bold"))
    print(f"  Topic Publish : {c(topic_tx, 'cyan')}")
    print(f"  Topic Subscribe: {c(topic_rx, 'cyan')}")

    received = []

    def on_connect(client, userdata, flags, rc):
        if rc == 0:
            print(f"\n  {c('✔ Terhubung ke MQTT broker', 'green')}")
            client.subscribe(topic_rx)
            print(f"  Subscribed ke {c(topic_rx, 'cyan')}")
        else:
            print(c(f"  ✗ Gagal connect, kode: {rc}", "red"))

    def on_message(client, userdata, msg):
        payload = msg.payload.decode()
        print(f"\n  {c('◀ MQTT RECEIVED', 'green')} [{msg.topic}]")
        try:
            data = json.loads(payload)
            print(f"  Payload: {json.dumps(data, indent=4)}")
        except Exception:
            print(f"  Payload: {payload}")
        received.append(payload)

    client = mqtt.Client(client_id)
    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(host, port, 60)
        client.loop_start()
        time.sleep(1)

        for i in range(3):
            payload = generate_sensor_data("normal")
            msg     = json.dumps(payload)
            result  = client.publish(topic_tx, msg)
            print(f"\n  {c(f'▶ MQTT PUBLISH #{i+1}', 'cyan')} → {topic_tx}")
            print(f"  Payload: {msg}")
            print(f"  Status : {'OK' if result.rc == 0 else 'GAGAL'}")
            time.sleep(2)

        print(f"\n  {c('Menunggu pesan masuk 5 detik...', 'yellow')}")
        time.sleep(5)
        client.loop_stop()
        client.disconnect()
        print(c("\n  ✔ MQTT test selesai.", "green"))

    except Exception as e:
        print(c(f"\n  ✗ MQTT Error: {e}", "red"))


# ══════════════════════════════════════════════════════════
#  INTERACTIVE MENU
# ══════════════════════════════════════════════════════════
def interactive_menu():
    print_header()

    # Pilih environment
    print(c("  [1] Local (localhost:8000)", "white"))
    print(c("  [2] Production (smartgarden.wuaze.com)", "white"))
    env_choice = input(c("\n  Target server [1/2, default=1]: ", "yellow")).strip() or "1"
    base_url = CONFIG["BASE_URL_PRODUCTION"] if env_choice == "2" else CONFIG["BASE_URL_LOCAL"]

    # Device code
    dc_default = CONFIG["DEVICE_CODE"]
    device_code = input(c(f"  Device Code [{dc_default}]: ", "yellow")).strip() or dc_default

    print(f"\n  {c('URL', 'cyan')}: {base_url}")
    print(f"  {c('Device', 'cyan')}: {device_code}\n")

    # Pilih mode
    print(c("  Pilih mode test:", "bold"))
    print("  [1] Single Shot       — Kirim 1 request")
    print("  [2] Loop Simulasi     — Kirim terus (seperti ESP32)")
    print("  [3] Stress Test       — 10 request rapid")
    print("  [4] Validasi Input    — Test error handling")
    print("  [5] MQTT Test         — Publish ke HiveMQ")
    print("  [0] Keluar")

    choice = input(c("\n  Pilihan [0-5]: ", "yellow")).strip()

    modes = {
        "1": lambda: mode_single(base_url, device_code),
        "2": lambda: mode_loop(base_url, device_code),
        "3": lambda: mode_stress(base_url, device_code),
        "4": lambda: mode_validation(base_url, device_code),
        "5": lambda: mode_mqtt(device_code),
    }

    if choice == "0":
        print(c("\n  Keluar. Sampai jumpa!\n", "cyan"))
        sys.exit(0)
    elif choice in modes:
        modes[choice]()
    else:
        print(c("\n  Pilihan tidak valid.\n", "red"))


# ══════════════════════════════════════════════════════════
#  ENTRY POINT
# ══════════════════════════════════════════════════════════
def main():
    parser = argparse.ArgumentParser(description="SENA IoT API Tester")
    parser.add_argument("--mode", choices=["single", "loop", "stress", "validate", "mqtt"],
                        help="Mode test (tanpa argumen = menu interaktif)")
    parser.add_argument("--url",  default=CONFIG["BASE_URL_LOCAL"],  help="Base URL server")
    parser.add_argument("--code", default=CONFIG["DEVICE_CODE"],     help="Device code (UUID)")
    args = parser.parse_args()

    if not args.mode:
        # Mode interaktif
        interactive_menu()
        return

    print_header()
    print(f"  URL    : {c(args.url, 'cyan')}")
    print(f"  Device : {c(args.code, 'magenta')}")

    if args.mode == "single":
        mode_single(args.url, args.code)
    elif args.mode == "loop":
        mode_loop(args.url, args.code)
    elif args.mode == "stress":
        mode_stress(args.url, args.code)
    elif args.mode == "validate":
        mode_validation(args.url, args.code)
    elif args.mode == "mqtt":
        mode_mqtt(args.code)


if __name__ == "__main__":
    main()
