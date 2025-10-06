<?php
// config/autoload/cache.php
return [
    'default' => [
        'driver' => getenv('CACHE_DRIVER', 'file'),
        'prefix' => 'app_',
    ],
    'drivers' => [
        'file' => [
            'driver' => 'file',
            'path'   => BASE_PATH . '/runtime/cache',
        ],
    ],
];
