<?php

/**
 * ============================================================================
 * 🌐 IoT SIMULATOR - ESP32 Communication Test
 * ============================================================================
 * Simulasi komunikasi bidireksional HTTP polling antara ESP32 dan Laravel
 * 
 * Features:
 * - Telemetry polling every 10 seconds
 * - Command polling every 5 seconds
 * - Fuzzy logic simulation
 * - Pump control state tracking
 * ============================================================================
 */

// ==================== CONFIGURATION ====================
$BASE_URL = 'https://moodle.mdpower.io';
$API_TELEMETRY = $BASE_URL . '/api/v1/telemetry';
$API_COMMANDS = $BASE_URL . '/api/v1/device-commands';

// Device credentials (get from dashboard)
$DEVICE_CODE = 'd228a1a3-3cd6-4949-931b-390050a67e2a'; // Change to your device code
$DEVICE_NAME = 'ESP32 Simulator - Kalicupak';

// ==================== STATE VARIABLES ====================
$pumpControlMode = 0;  // 0 = FUZZY, 1 = OVERRIDE
$pumpState = false;
$fuzzyOutput = 0.0;
$overrideTimeout = 0;
$overrideTimeoutDuration = 300; // 5 minutes

// Fuzzy parameters
$fuzzyConfig = [
    'moisture_kering_max' => 40.0,
    'moisture_normal_min' => 30.0,
    'moisture_normal_max' => 70.0,
    'moisture_basah_min' => 60.0,
    'ph_asam_max' => 6.5,
    'ph_netral_min' => 6.0,
    'ph_netral_max' => 7.5,
    'ph_basa_min' => 7.0,
];

// ==================== UTILITIES ====================

function log_info($message) {
    echo "\033[32m[INFO]\033[0m $message\n";
}

function log_warn($message) {
    echo "\033[33m[WARN]\033[0m $message\n";
}

function log_error($message) {
    echo "\033[31m[ERROR]\033[0m $message\n";
}

function log_success($message) {
    echo "\033[36m[SUCCESS]\033[0m $message\n";
}

function log_command($message) {
    echo "\033[35m[COMMAND]\033[0m $message\n";
}

function http_post($url, $data, $deviceCode) {
    $payload = json_encode($data);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Device-Code: ' . $deviceCode
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $result,
        'error' => $error
    ];
}

function http_get($url, $deviceCode) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Device-Code: ' . $deviceCode
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $result,
        'error' => $error
    ];
}

// ==================== FUZZY LOGIC ====================

function calculateMembershipKering($moisture) {
    global $fuzzyConfig;
    $max = $fuzzyConfig['moisture_kering_max'];
    if ($moisture > $max) return 0.0;
    return 1.0 - ($moisture / $max);
}

function calculateMembershipNormal($moisture) {
    global $fuzzyConfig;
    $min = $fuzzyConfig['moisture_normal_min'];
    $max = $fuzzyConfig['moisture_normal_max'];
    $mid = ($min + $max) / 2;
    
    if ($moisture < $min || $moisture > $max) return 0.0;
    
    if ($moisture <= $mid) {
        return ($moisture - $min) / ($mid - $min);
    } else {
        return ($max - $moisture) / ($max - $mid);
    }
}

function calculateMembershipBasah($moisture) {
    global $fuzzyConfig;
    $min = $fuzzyConfig['moisture_basah_min'];
    if ($moisture < $min) return 0.0;
    return ($moisture - $min) / (100 - $min);
}

function calculatePhAsam($ph) {
    global $fuzzyConfig;
    $max = $fuzzyConfig['ph_asam_max'];
    if ($ph > $max) return 0.0;
    return 1.0 - ($ph / $max);
}

function calculatePhNetral($ph) {
    global $fuzzyConfig;
    $min = $fuzzyConfig['ph_netral_min'];
    $max = $fuzzyConfig['ph_netral_max'];
    $mid = ($min + $max) / 2;
    
    if ($ph < $min || $ph > $max) return 0.0;
    
    if ($ph <= $mid) {
        return ($ph - $min) / ($mid - $min);
    } else {
        return ($max - $ph) / ($max - $mid);
    }
}

function calculatePhBasa($ph) {
    global $fuzzyConfig;
    $min = $fuzzyConfig['ph_basa_min'];
    if ($ph < $min) return 0.0;
    return ($ph - $min) / (14 - $min);
}

function calculateFuzzyLogic($moisture, $ph) {
    // Calculate membership
    $moistureKering = calculateMembershipKering($moisture);
    $moistureNormal = calculateMembershipNormal($moisture);
    $moistureBasah = calculateMembershipBasah($moisture);
    
    $phAsam = calculatePhAsam($ph);
    $phNetral = calculatePhNetral($ph);
    $phBasa = calculatePhBasa($ph);
    
    // Fuzzy inference rules (9 rules) - [strength, output]
    $rules = [
        [min($moistureKering, $phNetral), 1.0],   // R1
        [min($moistureKering, $phAsam), 0.7],     // R2
        [min($moistureKering, $phBasa), 0.7],     // R3
        [min($moistureNormal, $phNetral), 0.2],   // R4
        [min($moistureNormal, $phAsam), 0.5],     // R5
        [min($moistureNormal, $phBasa), 0.5],     // R6
        [min($moistureBasah, $phNetral), 0.0],    // R7
        [min($moistureBasah, $phAsam), 0.0],      // R8
        [min($moistureBasah, $phBasa), 0.0],      // R9
    ];
    
    // Defuzzification (weighted average)
    $sumWeighted = 0.0;
    $sumWeight = 0.0;
    
    foreach ($rules as $rule) {
        $strength = $rule[0];
        $output = $rule[1];
        $sumWeighted += $strength * $output;
        $sumWeight += $strength;
    }
    
    return $sumWeight > 0 ? $sumWeighted / $sumWeight : 0.0;
}

// ==================== MAIN LOOP ====================

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       🌱 IoT SIMULATOR - HTTP POLLING MODEL              ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║ Device: " . str_pad($DEVICE_NAME, 47) . "║\n";
echo "║ Code:   " . str_pad($DEVICE_CODE, 47) . "║\n";
echo "║ Server: " . str_pad($BASE_URL, 47) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

log_info("Starting IoT Simulator...");
log_info("Telemetry: Every 10 seconds");
log_info("Commands:  Every 5 seconds\n");

// Timing variables
$lastTelemetry = 0;
$lastCommand = 0;
$cycleCount = 0;
$commandCount = 0;

// Main loop
while (true) {
    $now = time();
    $cycleCount++;
    
    // ==================== TELEMETRY CYCLE (10s) ====================
    if ($now - $lastTelemetry >= 10) {
        $lastTelemetry = $now;
        
        echo "\n" . str_repeat("─", 62) . "\n";
        echo "\033[1;37m[CYCLE $cycleCount]\033[0m Telemetry Sent @ " . date('H:i:s') . "\n";
        echo str_repeat("─", 62) . "\n";
        
        // Generate sensor data
        $moisture = mt_rand(25, 85);
        $ph = 5.5 + (mt_rand(0, 30) / 10);
        $temperature = 24 + (mt_rand(-3, 6) / 10);
        $humidity = mt_rand(55, 75);
        $light = mt_rand(300, 1000);
        
        // Calculate fuzzy logic
        $fuzzyOutput = calculateFuzzyLogic($moisture, $ph);
        
        // Determine pump state based on mode
        if ($pumpControlMode == 0) {
            // FUZZY MODE
            $pumpState = $fuzzyOutput > 0.5;
            $modeStr = "FUZZY";
        } else {
            // OVERRIDE MODE
            $modeStr = "OVERRIDE";
            
            // Check timeout
            if ($now - $overrideTimeout > $overrideTimeoutDuration) {
                $pumpControlMode = 0;
                log_warn("Override timeout! Back to FUZZY mode");
                $modeStr = "FUZZY";
            }
        }
        
        // Prepare telemetry payload
        $telemetryData = [
            'ph' => round($ph, 1),
            'soil_moisture' => $moisture,
            'light_intensity' => $light,
            'temperature' => round($temperature, 1),
            'humidity' => $humidity,
        ];
        
        // Send telemetry
        $response = http_post($API_TELEMETRY, $telemetryData, $DEVICE_CODE);
        
        if ($response['error']) {
            log_error("Telemetry failed: " . $response['error']);
        } elseif ($response['code'] == 201 || $response['code'] == 200) {
            log_success("Telemetry sent (HTTP {$response['code']})");
            
            echo "  Sensors:\n";
            echo "    • Moisture: {$moisture}% | Status: ";
            if ($moisture < 40) echo "🔴 KERING";
            elseif ($moisture < 70) echo "🟡 NORMAL";
            else echo "🟢 BASAH";
            echo "\n";
            echo "    • pH: " . round($ph, 1) . " | Status: ";
            if ($ph < 6.5) echo "🔴 ASAM";
            elseif ($ph < 7.5) echo "🟡 NETRAL";
            else echo "🔵 BASA";
            echo "\n";
            echo "    • Temperature: " . round($temperature, 1) . "°C\n";
            echo "    • Humidity: {$humidity}%\n";
            echo "    • Light: {$light} lux\n";
            
            echo "  Fuzzy Logic:\n";
            echo "    • Output: " . round($fuzzyOutput, 3) . "\n";
            echo "    • Decision: " . ($fuzzyOutput > 0.5 ? "💧 PUMP ON" : "🚫 PUMP OFF") . "\n";
            
            echo "  Pump Control:\n";
            echo "    • Mode: $modeStr\n";
            echo "    • State: " . ($pumpState ? "✅ ON" : "❌ OFF") . "\n";
        } else {
            log_error("Telemetry failed (HTTP {$response['code']}): {$response['body']}");
        }
    }
    
    // ==================== COMMAND POLLING (5s) ====================
    if ($now - $lastCommand >= 5) {
        $lastCommand = $now;
        $commandCount++;
        
        echo "\n\033[1;36m[POLL #$commandCount]\033[0m Command check @ " . date('H:i:s') . "\n";
        
        // Send GET request
        $commandUrl = $API_COMMANDS . '/' . $DEVICE_CODE;
        $response = http_get($commandUrl, $DEVICE_CODE);
        
        if ($response['error']) {
            log_error("Command poll failed: " . $response['error']);
        } elseif ($response['code'] == 200) {
            $commandResponse = json_decode($response['body'], true);
            
            if (isset($commandResponse['has_command']) && $commandResponse['has_command']) {
                $command = $commandResponse['command'];
                log_command("Command received!");
                
                if ($command['type'] == 'pump_toggle') {
                    $pumpControlMode = 1; // OVERRIDE
                    $pumpState = (bool)$command['pump_state'];
                    $overrideTimeout = $now;
                    
                    echo "    Type: PUMP_TOGGLE\n";
                    echo "    State: " . ($pumpState ? "💧 ON" : "🚫 OFF") . "\n";
                    echo "    Duration: 5 minutes\n";
                    
                } elseif ($command['type'] == 'update_fuzzy') {
                    echo "    Type: UPDATE_FUZZY\n";
                    echo "    Applied parameters: " . count($command['fuzzy_config']) . " configs\n";
                }
            } else {
                log_info("No pending commands");
            }
        } else {
            log_error("Command poll failed (HTTP {$response['code']}): {$response['body']}");
        }
    }
    
    sleep(1);
}
