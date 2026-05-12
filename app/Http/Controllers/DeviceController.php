<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    /**
     * Display a listing of the user's devices.
     */
    public function index(Request $request)
    {
        $devices = $request->user()->devices()->paginate(10);
        return view('devices.index', compact('devices'));
    }

    /**
     * Show the form for creating a new device.
     */
    public function create()
    {
        return view('devices.create');
    }

    /**
     * Store a newly created device in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'status' => 'boolean',
        ]);

        // Auto-generate a unique device_code
        $validated['device_code'] = Str::uuid()->toString();
        $validated['status'] = $request->has('status'); // simple boolean check

        $request->user()->devices()->create($validated);

        return redirect()->route('devices.index')->with('success', 'Device created successfully!');
    }

    /**
     * Show the form for editing the specified device.
     */
    public function edit(Device $device, Request $request)
    {
        // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        return view('devices.edit', compact('device'));
    }

    /**
     * Update the specified device in storage.
     */
    public function update(Request $request, Device $device)
    {
        // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);
        
        $validated['status'] = $request->has('status');

        $device->update($validated);

        return redirect()->route('devices.index')->with('success', 'Device updated successfully!');
    }

    /**
     * Remove the specified device from storage.
     */
    public function destroy(Device $device, Request $request)
    {
         // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Device deleted successfully!');
    }

    /**
     * Toggles the power state of the pump for a specific device.
     */
    public function togglePump(Device $device, Request $request)
    {
        // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'pump_state' => 'required|boolean'
        ]);

        $device->update([
            'pump_state' => $validated['pump_state']
        ]);

        try {
            $mqtt = new \PhpMqtt\Client\MqttClient(config('mqtt.host'), config('mqtt.port'), config('mqtt.client_id'));
            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())->setKeepAliveInterval(60);
            if (!empty(config('mqtt.username'))) {
                $connectionSettings->setUsername(config('mqtt.username'))->setPassword(config('mqtt.password'));
            }
            $mqtt->connect($connectionSettings, true);
            $mqtt->publish('sena/rx/' . $device->device_code, json_encode([
                'action' => 'toggle_pump',
                'pump_state' => $device->pump_state ? 1 : 0
            ]), 0);
            $mqtt->disconnect();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MQTT Publish Error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'pump_state' => $device->pump_state
        ]);
    }
}
