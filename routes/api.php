<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Telemetry endpoint (device sends sensor data)
    Route::post('/telemetry', [\App\Http\Controllers\Api\TelemetryController::class, 'store']);
    
    // Command polling endpoint (device checks for pending commands)
    Route::get('/device-commands/{deviceCode}', [\App\Http\Controllers\Api\CommandController::class, 'getPendingCommands']);
});
