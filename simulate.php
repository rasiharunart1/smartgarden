<?php

$url = 'http://localhost:8000/api/v1/telemetry';
$deviceCode = '837c6151-3302-4a8f-8773-e5884d02c340';

echo "Memulai simulasi IoT Device: $deviceCode\n";
echo "Mengirim data ke $url\n\n";

// Loop indefinitely
$i = 1;
while (true) {
    $data = [
        'ph' => 6 + (mt_rand(-5, 10) / 10), // 5.5 to 7.0
        'soil_moisture' => mt_rand(50, 80),
        'light_intensity' => mt_rand(400, 1000),
        'temperature' => 24 + (mt_rand(-3, 6) / 10), // 23.7 to 24.6
        'humidity' => mt_rand(55, 75),
    ];

    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    // Set HTTP Header
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Device-Code: ' . $deviceCode
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "[$i] Payload Data: pH:{$data['ph']}, Kelembaban:{$data['soil_moisture']}%\n";
    
    if ($httpCode == 201 || $httpCode == 200) {
        $response = json_decode($result, true);
        if (isset($response['device_controls']['pump_state'])) {
            $pumpState = $response['device_controls']['pump_state'];
            $statusStr = $pumpState ? 'MENYALA (ON)' : 'MATI (OFF)';
            echo "    -> [ BERHASIL ] Status HTTP: $httpCode | Perintah Pompa dari Server: $statusStr\n";
        } else {
            echo "    -> [ BERHASIL ] Status HTTP: $httpCode | Response: $result\n";
        }
    } else {
        echo "    -> [ GAGAL ] Status HTTP: $httpCode | Response: $result\n";
    }

    echo "---------------------------------------------------------\n";
    
    $i++;
    sleep(3); // Wait 3 seconds before next telemetry ping
}
