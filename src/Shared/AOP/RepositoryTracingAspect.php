<?php

namespace Core\Shared\AOP;

use Core\Adapter\Out\Persistence\MySqlAccountRepository;
use Core\Adapter\Out\Persistence\MySqlWithdrawRepository;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

class RepositoryTracingAspect extends AbstractAspect
{
    public array $classes = [
        MySqlAccountRepository::class . '::byId',
        MySqlAccountRepository::class . '::save',

        MySqlWithdrawRepository::class . '::byId',
        MySqlWithdrawRepository::class . '::save',
        MySqlWithdrawRepository::class . '::dueIds',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $repo = $proceedingJoinPoint->className;
        $method = $proceedingJoinPoint->methodName;

        $tracer = Globals::tracerProvider()->getTracer('repository');
        $span = $tracer->spanBuilder("repo {$repo}::{$method}")
            ->setAttribute('code.namespace', $repo)
            ->setAttribute('code.function', $method)
            ->setAttribute('db.system', 'mysql')
            ->startSpan();

        $scope = $span->activate();
        try {
            $args = $proceedingJoinPoint->arguments['keys'] ?? [];

            if ($repo === MySqlAccountRepository::class && $method === 'byId') {
                if (isset($args['id']))   $span->setAttribute('account.id', (string)$args['id']);
                if (isset($args['forUpdate'])) $span->setAttribute('db.lock_for_update', (bool)$args['forUpdate']);
            }

            if ($repo === MySqlAccountRepository::class && $method === 'save') {
                if (isset($args['acc']))  $span->setAttribute('account.id', (string)$args['acc']->id);
            }

            if ($repo === MySqlWithdrawRepository::class && $method === 'byId') {
                if (isset($args['id']))   $span->setAttribute('withdraw.id', (string)$args['id']);
            }

            if ($repo === MySqlWithdrawRepository::class && $method === 'save') {
                if (isset($args['w'])) {
                    $span->setAttribute('withdraw.id', (string)$args['w']->id);
                    $span->setAttribute('withdraw.scheduled', (bool)$args['w']->scheduled);
                }
            }

            if ($repo === MySqlWithdrawRepository::class && $method === 'dueIds') {
                if (isset($args['now']))  $span->setAttribute('scheduled.cutoff', $args['now']->format('Y-m-d H:i:s'));
            }

            $result = $proceedingJoinPoint->process();

            if ($repo === MySqlWithdrawRepository::class && $method === 'dueIds') {
                $span->setAttribute('withdraw.due_count', is_array($result) ? count($result) : 0);
            }
            if ($repo === MySqlAccountRepository::class && $method === 'byId') {
                $span->setAttribute('db.hit', $result ? 1 : 0);
            }
            if ($repo === MySqlWithdrawRepository::class && $method === 'byId') {
                $span->setAttribute('db.hit', $result ? 1 : 0);
                if ($result) {
                    $span->setAttribute('withdraw.done', (int)$result->done);
                    $span->setAttribute('withdraw.error', (int)$result->error);
                }
            }

            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
