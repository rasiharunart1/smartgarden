<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CommandController extends Controller
{
    /**
     * Get pending commands for a device.
     * 
     * This endpoint is polled by ESP32 every 5 seconds to check
     * for pending commands like pump toggle or fuzzy config updates.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingCommands($deviceCode)
    {
        // Find device
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device || !$device->status) {
            Log::warning('Unauthorized command check: ' . $deviceCode);
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid or inactive device',
                'has_command' => false
            ], 403);
        }

        // Check cache for pending commands
        $cacheKey = 'device_command_' . $device->id;
        $command = Cache::pull($cacheKey); // Get and delete immediately

        if ($command) {
            Log::info("Command sent to device {$device->id}: " . json_encode($command));
            return response()->json([
                'status' => 'success',
                'has_command' => true,
                'command' => $command
            ], 200);
        }

        // No pending command
        return response()->json([
            'status' => 'success',
            'has_command' => false,
            'command' => null
        ], 200);
    }

    /**
     * Queue a pump toggle command for a specific device.
     * This will be returned on the next checkServerCommands() call.
     */
    public static function queuePumpToggle(Device $device, bool $state)
    {
        $command = [
            'type' => 'pump_toggle',
            'pump_state' => $state,
            'timestamp' => now()->toIso8601String()
        ];

        // Store in cache for 30 seconds (time to next poll)
        Cache::put('device_command_' . $device->id, $command, 30);

        Log::info("Queued pump toggle for device {$device->id}: " . ($state ? 'ON' : 'OFF'));

        return $command;
    }

    /**
     * Queue a fuzzy config update command for a specific device.
     */
    public static function queueFuzzyUpdate(Device $device)
    {
        $fuzzyConfig = $device->fuzzyConfig()->first();

        if (!$fuzzyConfig) {
            return null;
        }

        $command = [
            'type' => 'update_fuzzy',
            'timestamp' => now()->toIso8601String(),
            'fuzzy_config' => [
                'moisture_kering_max' => (float) $fuzzyConfig->moisture_kering_max,
                'moisture_normal_min' => (float) $fuzzyConfig->moisture_normal_min,
                'moisture_normal_max' => (float) $fuzzyConfig->moisture_normal_max,
                'moisture_basah_min' => (float) $fuzzyConfig->moisture_basah_min,
                'ph_asam_max' => (float) $fuzzyConfig->ph_asam_max,
                'ph_netral_min' => (float) $fuzzyConfig->ph_netral_min,
                'ph_netral_max' => (float) $fuzzyConfig->ph_netral_max,
                'ph_basa_min' => (float) $fuzzyConfig->ph_basa_min,
            ]
        ];

        // Store in cache for 30 seconds
        Cache::put('device_command_' . $device->id, $command, 30);

        Log::info("Queued fuzzy config update for device {$device->id}");

        return $command;
    }
}
