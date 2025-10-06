<?php
declare(strict_types=1);

namespace Core\Shared\AOP;

use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Annotation\Aspect;   // só o Aspect
use OpenTelemetry\API\Globals;
use Throwable;

#[Aspect]
final class TracingAspect extends AbstractAspect
{
    public array $classes = [
        'Core\\Application\\Command\\*Handler::__invoke',
        'Core\\Application\\Query\\*Handler::__invoke',
        'Core\\Domain\\Service\\*::*',
        'Core\\Adapter\\Out\\Persistence\\*Repository::*',
    ];

    public function process(ProceedingJoinPoint $proceeding)
    {
        $class  = $proceeding->className;
        $method = $proceeding->methodName;

        $tracer = Globals::tracerProvider()->getTracer('app');
        $span = $tracer->spanBuilder($this->spanName($class, $method))
            ->setAttribute('code.namespace', $class)
            ->setAttribute('code.function', $method)
            ->startSpan();

        $scope = $span->activate();

        try {
            $span->setAttribute('app.args.count', count($proceeding->getArguments()));
            $result = $proceeding->process();
            $span->setAttribute(
                'app.result.type',
                is_object($result) ? get_class($result) : gettype($result)
            );
            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setAttribute('error', true);
            $span->setAttribute('exception.class', get_class($e));
            $span->setAttribute('exception.message', $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function spanName(string $class, string $method): string
    {
        return 'app.' . str_replace('\\', '.', $class) . '.' . $method;
    }
}
