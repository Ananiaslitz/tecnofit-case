<?php
declare(strict_types=1);

namespace HyperfTest;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PhpUnit;

abstract class TestCase extends PhpUnit
{
    use MockeryPHPUnitIntegration;
}
