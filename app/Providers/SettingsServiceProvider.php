<?php

namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (config('signals.installed')) {
            try {
                app(SettingsService::class)->load();
            } catch (\Exception) {
                // Silently fail during migrations or when the table doesn't exist yet
            }
        }
    }
}
