<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class FuzzyConfigController extends Controller
{
    /**
     * Show the form for editing the fuzzy config of a device.
     */
    public function edit(Device $device, Request $request)
    {
        // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        // Get the config or create a default one
        $fuzzyConfig = $device->fuzzyConfig()->firstOrCreate([
            'device_id' => $device->id
        ]);

        return view('devices.fuzzy', compact('device', 'fuzzyConfig'));
    }

    /**
     * Update the fuzzy config in storage.
     */
    public function update(Request $request, Device $device)
    {
        // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'moisture_kering_max' => 'required|numeric|min:0|max:100',
            'moisture_normal_min' => 'required|numeric|min:0|max:100',
            'moisture_normal_max' => 'required|numeric|min:0|max:100',
            'moisture_basah_min' => 'required|numeric|min:0|max:100',
            'ph_asam_max' => 'required|numeric|min:0|max:14',
            'ph_netral_min' => 'required|numeric|min:0|max:14',
            'ph_netral_max' => 'required|numeric|min:0|max:14',
            'ph_basa_min' => 'required|numeric|min:0|max:14',
        ]);

        $fuzzyConfig = $device->fuzzyConfig()->updateOrCreate(
            ['device_id' => $device->id],
            $validated
        );

        try {
            $mqtt = new \PhpMqtt\Client\MqttClient(config('mqtt.host'), config('mqtt.port'), config('mqtt.client_id'));
            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())->setKeepAliveInterval(60);
            if (!empty(config('mqtt.username'))) {
                $connectionSettings->setUsername(config('mqtt.username'))->setPassword(config('mqtt.password'));
            }
            $mqtt->connect($connectionSettings, true);
            
            $payload = array_merge(['action' => 'update_fuzzy'], $fuzzyConfig->toArray());
            unset($payload['id'], $payload['device_id'], $payload['created_at'], $payload['updated_at']);

            $mqtt->publish('sena/rx/' . $device->device_code, json_encode($payload), 0);
            $mqtt->disconnect();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MQTT Publish Error: ' . $e->getMessage());
        }

        return redirect()->route('devices.fuzzy', $device)->with('success', 'Fuzzy Logic Settings updated successfully!');
    }
}
