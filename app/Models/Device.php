<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_name',
        'device_code',
        'location',
        'status',
        'pump_state',
        'control_mode',
        'photo',
        'last_seen_at',
    ];

    /**
     * Get the full URL for the device photo.
     */
    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo && \Storage::disk('public')->exists($this->photo)) {
            return asset('storage/' . $this->photo);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->device_name) . '&background=064e3b&color=34d399&size=128&rounded=true&bold=true';
    }

    protected $casts = [
        'status' => 'boolean',
        'pump_state' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Determine if the device is currently online.
     * Online = menerima data telemetri dalam 2 menit terakhir.
     * Cek last_seen_at (heartbeat), fallback ke log terakhir.
     */
    public function getIsOnlineAttribute(): bool
    {
        $threshold = 2; // menit

        // Prioritas 1: cek last_seen_at (di-update setiap telemetry masuk)
        if ($this->last_seen_at) {
            return $this->last_seen_at->diffInMinutes(now()) <= $threshold;
        }

        // Fallback: cek timestamp log terakhir
        $latestLog = $this->logs()->latest()->first();
        if ($latestLog) {
            return $latestLog->created_at->diffInMinutes(now()) <= $threshold;
        }

        return false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(DeviceLog::class);
    }

    public function fuzzyConfig()
    {
        return $this->hasOne(FuzzyConfig::class);
    }
}
