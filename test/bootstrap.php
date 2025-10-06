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

use Hyperf\Context\Context;
use OpenTelemetry\API\Trace\Span;

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

Swoole\Runtime::enableCoroutine(true);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', Hyperf\Engine\DefaultOption::hookFlags());

Hyperf\Di\ClassLoader::init();

$container = require BASE_PATH . '/config/container.php';

putenv('APP_ENV=testing');

if (! getenv('DB_DATABASE')) {
    putenv('DB_HOST=mysql');
    putenv('DB_PORT=3306');
    putenv('DB_DATABASE=tecnofit');
    putenv('DB_USERNAME=root');
    putenv('DB_PASSWORD=secret');
}

Mockery::getConfiguration()->allowMockingNonExistentMethods(true);

putenv('MAIL_MOCK=true');
static $aliasesInitialized = false;
if (!$aliasesInitialized) {
    $aliasesInitialized = true;
    Mockery::mock('alias:' . Span::class);
    Mockery::mock('alias:' . Context::class);
}

@exec('rm -rf ' . escapeshellarg(__DIR__ . '/../runtime/container'));

passthru('php ' . __DIR__ . '/../bin/hyperf.php migrate');

$container->get(Hyperf\Contract\ApplicationInterface::class);
