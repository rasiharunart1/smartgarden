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

        // Kalau user toggle pompa manual, otomatis set ke mode manual
        $device->update([
            'pump_state' => $validated['pump_state'],
            'control_mode' => 1, // Manual mode
        ]);

        // Queue command untuk device ambil di next polling cycle
        \App\Http\Controllers\Api\CommandController::queuePumpToggle($device, $validated['pump_state']);

        return response()->json([
            'success' => true,
            'pump_state' => $device->pump_state,
            'control_mode' => $device->control_mode,
        ]);
    }

    /**
     * Set control mode: 0 = Auto (Fuzzy Logic), 1 = Manual (Dashboard)
     */
    public function setControlMode(Device $device, Request $request)
    {
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'control_mode' => 'required|integer|in:0,1',
        ]);

        $updateData = ['control_mode' => $validated['control_mode']];

        // Jika switch ke Auto mode, biarkan fuzzy logic yang tentukan pompa
        // Reset pump_state agar ESP32 pakai keputusan fuzzy di cycle berikutnya
        if ($validated['control_mode'] == 0) {
            $updateData['pump_state'] = false;
        }

        $device->update($updateData);

        // Queue command ke ESP32 agar langsung switch mode
        \App\Http\Controllers\Api\CommandController::queueModeChange($device, $validated['control_mode']);

        return response()->json([
            'success' => true,
            'control_mode' => $device->control_mode,
            'pump_state' => $device->pump_state,
        ]);
    }
}
