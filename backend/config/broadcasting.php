<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'redis'),
    'connections' => [
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_BROADCAST_CONNECTION', 'default'),
        ],
    ],
];
