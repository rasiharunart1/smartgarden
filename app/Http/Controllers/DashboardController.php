<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the user dashboard.
     */
    public function index(Request $request)
    {
        // Get devices for the current user
        $devices = $request->user()->devices()->with('logs')->get();

        // Check if there's a selected device id in query params
        $activeDeviceId = $request->query('device_id');

        $activeDevice = null;
        if ($activeDeviceId) {
            $activeDevice = $devices->where('id', $activeDeviceId)->first();
        } 
        
        if (!$activeDevice && $devices->isNotEmpty()) {
            $activeDevice = $devices->first();
        }

        // Get recent logs for the active device
        $recentLogs = [];
        $latestLog = null;

        if ($activeDevice) {
            $recentLogs = $activeDevice->logs()
                ->orderBy('created_at', 'desc')
                ->take(24) // Last 24 records
                ->get()
                ->reverse()
                ->values();
                
            $latestLog = $recentLogs->last();
        }

        return view('dashboard', compact('devices', 'activeDevice', 'recentLogs', 'latestLog'));
    }

    /**
     * Return dashboard data as JSON for real-time polling.
     */
    public function data(Request $request)
    {
        // Check if there's a selected device id in query params
        $activeDeviceId = $request->query('device_id');
        
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $devices = $user->devices();
        $activeDevice = null;
        
        if ($activeDeviceId) {
            $activeDevice = $devices->where('id', $activeDeviceId)->first();
        } else {
            $activeDevice = $devices->first();
        }

        if (!$activeDevice) {
            return response()->json(['success' => false, 'message' => 'No devices found']);
        }

        // Get recent logs for the active device
        $recentLogs = $activeDevice->logs()
            ->orderBy('created_at', 'desc')
            ->take(24) // Last 24 records
            ->get()
            ->reverse()
            ->values();
            
        $latestLog = $recentLogs->last();

        // Format data for response
        return response()->json([
            'success' => true,
            'device_id' => $activeDevice->id,
            'device_status' => $activeDevice->status,
            'latest' => $latestLog ? [
                'ph' => $latestLog->ph,
                'soil_moisture' => $latestLog->soil_moisture,
                'light_intensity' => $latestLog->light_intensity,
                'temperature' => $latestLog->temperature,
                'humidity' => $latestLog->humidity,
                'time_ago' => $latestLog->created_at->diffForHumans(),
                'timestamp' => $latestLog->created_at->format('H:i')
            ] : null,
            'chart' => [
                'labels' => $recentLogs->pluck('created_at')->map(fn($date) => $date->format('H:i')),
                'temperature' => $recentLogs->pluck('temperature'),
                'humidity' => $recentLogs->pluck('humidity'),
                'soil_moisture' => $recentLogs->pluck('soil_moisture'),
                'ph' => $recentLogs->pluck('ph'),
                'light_intensity' => $recentLogs->pluck('light_intensity'),
            ]
        ]);
    }
}
