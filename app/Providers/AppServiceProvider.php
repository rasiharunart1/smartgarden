<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS pada semua URL yang di-generate Laravel
        // Diperlukan jika app di-deploy di balik reverse proxy (Nginx/Cloudflare)
        if ($this->app->environment('production') || request()->isSecure()) {
            URL::forceScheme('https');
        }
    }
}
