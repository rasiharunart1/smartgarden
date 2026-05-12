<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fuzzy_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            
            // Kelembapan Tanah (Soil Moisture)
            $table->float('moisture_kering_max')->default(40);
            $table->float('moisture_normal_min')->default(30);
            $table->float('moisture_normal_max')->default(70);
            $table->float('moisture_basah_min')->default(60);
            
            // pH Tanah
            $table->float('ph_asam_max')->default(6.5);
            $table->float('ph_netral_min')->default(6.0);
            $table->float('ph_netral_max')->default(7.5);
            $table->float('ph_basa_min')->default(7.0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuzzy_configs');
    }
};
