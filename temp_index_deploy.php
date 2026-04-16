<?php
if (file_exists(__DIR__ . '/deploy_runner_blade.php')) {
    require __DIR__ . '/deploy_runner_blade.php';
}

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = '/home/u859266589/domains/dayancosys.com/laravel_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require '/home/u859266589/domains/dayancosys.com/laravel_app/vendor/autoload.php';

if (!isset($app) || !is_object($app) || !method_exists($app, 'make')) {
    $app = require '/home/u859266589/domains/dayancosys.com/laravel_app/bootstrap/app.php';
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);
