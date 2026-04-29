<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'WorkFlow'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => 'Asia/Riyadh',
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => 'en',
    'available_locales' => [
        'ar' => 'العربية',
        'en' => 'English',
    ],
    'faker_locale' => 'en_US',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
    ],
    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),
    'aliases' => Facade::defaultAliases()->merge([
        'Excel' => Maatwebsite\Excel\Facades\Excel::class,
        'PDF'   => Barryvdh\DomPDF\Facade\Pdf::class,
        'QrCode' => SimpleSoftwareIO\QrCode\Facades\QrCode::class,
        'Image' => Intervention\Image\Facades\Image::class,
    ])->toArray(),
];
