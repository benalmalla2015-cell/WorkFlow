<?php

$executedDirectly = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__;

if ($executedDirectly) {
    $key = $_GET['key'] ?? '';
    if ($key !== 'blade20260416dayanco') {
        http_response_code(403);
        exit('Forbidden');
    }
}

$bundlePath = __DIR__ . '/deploy_blade_bundle.zip';
$results = [];

if (file_exists($bundlePath)) {
    $zip = new ZipArchive();
    $opened = $zip->open($bundlePath);

    if ($opened !== true) {
        http_response_code(500);
        exit('Unable to open deployment bundle');
    }

    if (!$zip->extractTo(__DIR__)) {
        $zip->close();
        http_response_code(500);
        exit('Unable to extract deployment bundle');
    }

    $zip->close();
    $results[] = [
        'command' => 'extract_bundle',
        'exit_code' => 0,
        'output' => 'Deployment bundle extracted successfully.',
    ];

    @unlink($bundlePath);
}

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$commands = [
    ['migrate', ['--force' => true]],
    ['optimize:clear', []],
    ['view:cache', []],
];

foreach ($commands as [$command, $parameters]) {
    $exitCode = $kernel->call($command, $parameters);
    $results[] = [
        'command' => $command,
        'exit_code' => $exitCode,
        'output' => trim($kernel->output()),
    ];
}

if ($executedDirectly) {
    header('Content-Type: text/plain; charset=utf-8');
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

if (!$executedDirectly || ($_GET['cleanup'] ?? '') === '1') {
    @unlink($bundlePath);
    @unlink(__FILE__);
}
