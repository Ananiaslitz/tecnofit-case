<?php
namespace Core\Adapter\In\Cli;

use Core\Application\Command\ProcessScheduledWithdrawalsHandler;
use Hyperf\Command\Command as HyperfCommand;
use OpenTelemetry\API\Globals;

class ProcessScheduledWithdrawalsCommand extends HyperfCommand
{
    public function __construct(private ProcessScheduledWithdrawalsHandler $handler)
    {
        parent::__construct('withdrawals:process');
    }

    protected function configure()
    {
        $this->setName('withdrawals:process');
        $this->setDescription('Process scheduled PIX withdrawals.');
    }

    public function handle()
    {
        $tracer = Globals::tracerProvider()->getTracer('cli');
        $span   = $tracer->spanBuilder('withdrawals.process_batch')->startSpan();
        $scope  = $span->activate();

        try {
            $processed = $this->handler->process();
            $span->setAttribute('withdraw.processed_count', (int)$processed);
            $this->line("processed={$processed}");
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
