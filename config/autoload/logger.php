<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Core\Shared\Observability\TraceContextLogProcessor;

return [
    'default' => [
        'handlers' => [
            [
                'class' => StreamHandler::class,
                'constructor' => [
                    'stream' => 'php://stdout',
                    'level'  => Level::Info,
                ],
            ],
        ],
    ],
];
