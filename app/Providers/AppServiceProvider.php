<?php

namespace App\Providers;

use App\Models\Setting;
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
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            config([
                'settings' => Setting::all([
                    'name', 'value'
                ])
                ->keyBy('name')
                ->map(fn ($setting) => $setting->value)
                ->toArray(),
            ]);
        }
    }
}
