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

use Core\Shared\Exception\BadRequestExceptionHandler;
use Core\Shared\Exception\BusinessExceptionHandler;

return [
    'handler' => [
        'http' => [
            BusinessExceptionHandler::class,
            BadRequestExceptionHandler::class,
            Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler::class,
            Core\Shared\Exception\AppExceptionHandler::class,
        ],
    ],
];
