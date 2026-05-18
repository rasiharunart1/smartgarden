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
        'photo',
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
     * Determine if the device is currently online based on heartbeat.
     */
    public function getIsOnlineAttribute()
    {
        // Online if we received telemetry within the last 5 minutes
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) <= 5;
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
