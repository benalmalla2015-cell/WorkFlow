<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID', 'dayancosys'),
    'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', storage_path('app/firebase/service-account.json')),
    'vapid_public_key' => env('FIREBASE_VAPID_PUBLIC_KEY'),
    'web' => [
        'apiKey' => env('FIREBASE_API_KEY', env('VITE_FIREBASE_API_KEY')),
        'authDomain' => env('FIREBASE_AUTH_DOMAIN', env('VITE_FIREBASE_AUTH_DOMAIN')),
        'projectId' => env('FIREBASE_PROJECT_ID', env('VITE_FIREBASE_PROJECT_ID', 'dayancosys')),
        'storageBucket' => env('FIREBASE_STORAGE_BUCKET', env('VITE_FIREBASE_STORAGE_BUCKET')),
        'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID', env('VITE_FIREBASE_MESSAGING_SENDER_ID')),
        'appId' => env('FIREBASE_APP_ID', env('VITE_FIREBASE_APP_ID')),
        'measurementId' => env('FIREBASE_MEASUREMENT_ID', env('VITE_FIREBASE_MEASUREMENT_ID')),
    ],
];
