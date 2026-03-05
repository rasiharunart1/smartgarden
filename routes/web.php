<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SystemLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/data', [DashboardController::class, 'data'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.data');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Device CRUD
    Route::resource('devices', DeviceController::class);
    Route::post('/devices/{device}/toggle-pump', [DeviceController::class, 'togglePump'])->name('devices.toggle_pump');
    
    // Logs
    Route::get('/logs', [SystemLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/export', [SystemLogController::class, 'export'])->name('logs.export');
});

require __DIR__.'/auth.php';
