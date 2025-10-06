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


use Core\Shared\AOP\TracingAspect;

$enableTracing = (bool) getenv('ENABLE_TRACING', true);

return [
    TracingAspect::class
];
