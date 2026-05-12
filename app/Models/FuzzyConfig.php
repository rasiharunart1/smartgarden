<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuzzyConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'moisture_kering_max',
        'moisture_normal_min',
        'moisture_normal_max',
        'moisture_basah_min',
        'ph_asam_max',
        'ph_netral_min',
        'ph_netral_max',
        'ph_basa_min',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
