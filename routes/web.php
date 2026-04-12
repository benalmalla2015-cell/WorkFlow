<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Root redirect to login
Route::get('/', function () {
    return redirect('/login');
});

// Serve the SPA for all non-api routes
Route::get('/{any}', function () {
    $buildPath = public_path('frontend/build/index.html');
    if (File::exists($buildPath)) {
        return response(File::get($buildPath));
    }
    return view('welcome');
})->where('any', '^(?!api).*');
