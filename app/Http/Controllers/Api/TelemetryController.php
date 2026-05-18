<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceLogResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelemetryController extends Controller
{
    /**
     * Store telemetry data from IoT devices.
     */
    public function store(Request $request)
    {
        // Require Device Code passed in headers or body
        $deviceCode = $request->header('X-Device-Code') ?? $request->input('device_code');

        if (!$deviceCode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Device Code missing'
            ], 401);
        }

        // Validate device exists and is active
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device || !$device->status) {
            Log::warning('Unauthorized telemetry attempt with code: ' . $deviceCode);
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid or inactive device'
            ], 403);
        }

        // Validate sensor payload
        $validated = $request->validate([
            'ph' => 'nullable|numeric|between:0,14',
            'soil_moisture' => 'nullable|numeric|between:0,100',
            'light_intensity' => 'nullable|numeric',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric|between:0,100',
        ]);

        try {
            // Store Log Data
            $log = $device->logs()->create($validated);
            
            // Update Heartbeat
            $device->update(['last_seen_at' => now()]);

            // Load fuzzy config (create default jika belum ada)
            $fuzzyConfig = $device->fuzzyConfig()->firstOrCreate(
                ['device_id' => $device->id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Telemetry data saved correctly',
                'data'    => new DeviceLogResource($log),
                'device_controls' => [
                    'pump_state' => $device->pump_state,
                ],
                'fuzzy_config' => [
                    'moisture_kering_max' => (float) $fuzzyConfig->moisture_kering_max,
                    'moisture_normal_min' => (float) $fuzzyConfig->moisture_normal_min,
                    'moisture_normal_max' => (float) $fuzzyConfig->moisture_normal_max,
                    'moisture_basah_min'  => (float) $fuzzyConfig->moisture_basah_min,
                    'ph_asam_max'         => (float) $fuzzyConfig->ph_asam_max,
                    'ph_netral_min'       => (float) $fuzzyConfig->ph_netral_min,
                    'ph_netral_max'       => (float) $fuzzyConfig->ph_netral_max,
                    'ph_basa_min'         => (float) $fuzzyConfig->ph_basa_min,
                ],
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error saving telemetry: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Internal Server Error'
            ], 500);
        }
    }
}
