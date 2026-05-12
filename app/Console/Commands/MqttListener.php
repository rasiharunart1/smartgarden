<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class MqttListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to MQTT topic for incoming telemetry data from IoT devices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $server   = config('mqtt.host');
        $port     = config('mqtt.port');
        $clientId = config('mqtt.client_id');
        $username = config('mqtt.username');
        $password = config('mqtt.password');
        $topic    = 'sena/tx/+'; // Subscribe to all devices TX topics

        $this->info("Starting MQTT Listener for topic: {$topic}");

        try {
            $mqtt = new MqttClient($server, $port, $clientId);

            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())
                ->setKeepAliveInterval(60);
            
            if (!empty($username)) {
                $connectionSettings->setUsername($username)
                                   ->setPassword($password);
            }

            $mqtt->connect($connectionSettings, true);
            $this->info("Connected to MQTT Broker at {$server}:{$port}");

            $mqtt->subscribe($topic, function (string $topic, string $message) {
                // Topic format: sena/tx/{device_code}
                $parts = explode('/', $topic);
                $deviceCode = $parts[count($parts) - 1];
                
                $data = json_decode($message, true);

                $this->line("Received message on topic [{$topic}]: {$message}");

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->processTelemetry($deviceCode, $data);
                } else {
                    $this->error("Invalid JSON payload received.");
                }
            }, 0);

            $mqtt->loop(true);
            $mqtt->disconnect();

        } catch (MqttClientException $e) {
            $this->error('MQTT Client Error: ' . $e->getMessage());
            Log::error('MQTT Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->error('General Error: ' . $e->getMessage());
            Log::error('General Error: ' . $e->getMessage());
        }
    }

    private function processTelemetry($deviceCode, $data)
    {
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device || !$device->status) {
            $this->warn("Device not found or inactive: {$deviceCode}");
            return;
        }

        try {
            // Save log
            $device->logs()->create([
                'ph' => $data['ph'] ?? null,
                'soil_moisture' => $data['soil_moisture'] ?? null,
                'light_intensity' => $data['light_intensity'] ?? null,
                'temperature' => $data['temperature'] ?? null,
                'humidity' => $data['humidity'] ?? null,
            ]);

            // Update pump state if provided
            if (isset($data['pump_state'])) {
                $device->update([
                    'pump_state'   => (bool)$data['pump_state'],
                    'last_seen_at' => now()
                ]);
            } else {
                $device->update(['last_seen_at' => now()]);
            }

            $this->info("✅ Successfully saved telemetry for device: {$deviceCode}");

        } catch (\Exception $e) {
            $this->error('Error saving data to DB: ' . $e->getMessage());
        }
    }
}
