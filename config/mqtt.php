<?php

return [
    'host' => env('MQTT_HOST', 'broker.hivemq.com'),
    'port' => env('MQTT_PORT', 1883),
    'client_id' => env('MQTT_CLIENT_ID', 'sena_backend_' . uniqid()),
    'username' => env('MQTT_USERNAME', ''),
    'password' => env('MQTT_PASSWORD', ''),
];
