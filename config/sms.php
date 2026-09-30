<?php

return [
    'default' => env('SMS_DRIVER', 'log'),

    'drivers' => [
        'panel' => [
            'base_url'    => env('SMS_PANEL_URL'),
            'endpoint'    => env('SMS_PANEL_ENDPOINT'),
            'api_key'     => env('SMS_PANEL_API_KEY'),
            'line_number' => env('SMS_PANEL_LINE', 1000002121),
        ],
    ],
];
