<?php

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'cookie' => env('SESSION_COOKIE', 'ppic_session'),
    'path' => '/',
    'http_only' => true,
    'same_site' => 'lax',
];
