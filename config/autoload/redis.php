<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use function Hyperf\Support\env;

return [
    'default' => [
        'host'    => env('REDIS_HOST', '127.0.0.1'),
        'auth'    => env('REDIS_AUTH', null),
        'port'    => (int) env('REDIS_PORT', 6379),
        'db'      => (int) env('REDIS_DB', 0),
        'timeout' => (float) env('REDIS_TIMEOUT', 2.0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 2.0,
            'wait_timeout'    => 3.0,
            'heartbeat'       => -1,
            'max_idle_time'   => 60.0,
        ],
    ],
];