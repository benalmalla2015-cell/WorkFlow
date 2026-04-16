<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID', 'dayancosys'),
    'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', storage_path('app/firebase/service-account.json')),
    'vapid_public_key' => env('FIREBASE_VAPID_PUBLIC_KEY'),
    'web' => [
        'apiKey' => env('VITE_FIREBASE_API_KEY'),
        'authDomain' => env('VITE_FIREBASE_AUTH_DOMAIN'),
        'projectId' => env('VITE_FIREBASE_PROJECT_ID', env('FIREBASE_PROJECT_ID', 'dayancosys')),
        'storageBucket' => env('VITE_FIREBASE_STORAGE_BUCKET'),
        'messagingSenderId' => env('VITE_FIREBASE_MESSAGING_SENDER_ID'),
        'appId' => env('VITE_FIREBASE_APP_ID'),
        'measurementId' => env('VITE_FIREBASE_MEASUREMENT_ID'),
    ],
];
