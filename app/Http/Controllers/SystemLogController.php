<?php

namespace App\Http\Controllers;

use App\Models\DeviceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemLogController extends Controller
{
    /**
     * Display a listing of the sensor logs grouped by device.
     */
    public function index(Request $request)
    {
        // Get user's devices
        $userDevices = Auth::user()->devices->pluck('id');

        // Fetch logs for those devices with pagination
        $query = DeviceLog::with('device')
            ->whereIn('device_id', $userDevices)
            ->orderBy('created_at', 'desc');

        // Optional filtering by device
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        $logs = $query->paginate(20)->withQueryString();
        $devices = Auth::user()->devices;
        
        return view('logs.index', compact('logs', 'devices'));
    }

    /**
     * Export telemetry logs to CSV format.
     */
    public function export(Request $request)
    {
        // Get user's devices
        $userDevices = Auth::user()->devices->pluck('id');

        // Fetch logs for those devices
        $query = DeviceLog::with('device')
            ->whereIn('device_id', $userDevices)
            ->orderBy('created_at', 'desc');

        // Optional filtering by device
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        $logs = $query->get();

        $filename = "telemetry_logs_" . date('Y-m-d_H-i-s') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Timestamp', 'Device Name', 'Temperature (C)', 'Humidity (%)', 'Soil Moisture (%)', 'Light Intensity (Lux)', 'pH'];

        $callback = function() use($logs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($logs as $log) {
                $row = [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->device->device_name,
                    $log->temperature ?? '',
                    $log->humidity ?? '',
                    $log->soil_moisture ?? '',
                    $log->light_intensity ?? '',
                    $log->ph ?? ''
                ];
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
