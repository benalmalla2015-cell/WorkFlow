<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Models\Setting;

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
        // Set default string length for MySQL
        Schema::defaultStringLength(191);

        // Share settings with all views (safely)
        View::composer('*', function ($view) {
            try {
                $settings = \Illuminate\Support\Facades\Cache::remember('app_settings', 300, function () {
                    return \App\Models\Setting::pluck('value', 'key')->toArray();
                });
            } catch (\Exception $e) {
                $settings = [];
            }
            $view->with('settings', $settings);
        });
    }
}
