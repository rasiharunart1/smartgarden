<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = $request->user()->devices()->paginate(10);
        return view('devices.index', compact('devices'));
    }

    public function create()
    {
        return view('devices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'location'    => 'nullable|string|max:255',
            'status'      => 'boolean',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $validated['device_code'] = Str::uuid()->toString();
        $validated['status']      = $request->has('status');

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('devices', 'public');
        }

        $request->user()->devices()->create($validated);

        return redirect()->route('devices.index')->with('success', 'Device berhasil ditambahkan!');
    }

    public function edit(Device $device, Request $request)
    {
        if ($device->user_id !== $request->user()->id) abort(403);
        return view('devices.edit', compact('device'));
    }

    public function update(Request $request, Device $device)
    {
        if ($device->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'location'    => 'nullable|string|max:255',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $validated['status'] = $request->has('status');

        if ($request->hasFile('photo')) {
            // Hapus foto lama
            if ($device->photo) {
                Storage::disk('public')->delete($device->photo);
            }
            $validated['photo'] = $request->file('photo')->store('devices', 'public');
        }

        // Hapus foto jika user centang "remove photo"
        if ($request->boolean('remove_photo') && $device->photo) {
            Storage::disk('public')->delete($device->photo);
            $validated['photo'] = null;
        }

        $device->update($validated);

        return redirect()->route('devices.index')->with('success', 'Device berhasil diperbarui!');
    }

    public function destroy(Device $device, Request $request)
    {
        if ($device->user_id !== $request->user()->id) abort(403);

        // Hapus foto jika ada
        if ($device->photo) {
            Storage::disk('public')->delete($device->photo);
        }

        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Device berhasil dihapus!');
    }

    public function togglePump(Device $device, Request $request)
    {
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate(['pump_state' => 'required|boolean']);

        $device->update(['pump_state' => $validated['pump_state']]);

        try {
            $mqtt = new \PhpMqtt\Client\MqttClient(config('mqtt.host'), config('mqtt.port'), config('mqtt.client_id'));
            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())->setKeepAliveInterval(60);
            if (!empty(config('mqtt.username'))) {
                $connectionSettings->setUsername(config('mqtt.username'))->setPassword(config('mqtt.password'));
            }
            $mqtt->connect($connectionSettings, true);
            $mqtt->publish('sena/rx/' . $device->device_code, json_encode([
                'action'     => 'toggle_pump',
                'pump_state' => $device->pump_state ? 1 : 0
            ]), 0);
            $mqtt->disconnect();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MQTT Publish Error: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'pump_state' => $device->pump_state]);
    }
}
