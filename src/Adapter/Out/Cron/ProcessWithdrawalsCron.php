<?php

namespace Core\Adapter\Out\Cron;

use Core\Application\Command\ProcessScheduledWithdrawalsHandler;
use Hyperf\Crontab\Annotation\Crontab;

#[Crontab(
    rule: '* * * * *',
    name: 'process_scheduled_withdrawals',
    callback: 'handle',
    memo: 'Process scheduled PIX withdrawals',
    enable: true
)]
class ProcessWithdrawalsCron
{
    public function __construct(private ProcessScheduledWithdrawalsHandler $handler) {}

    public function handle(): void
    {
        ($this->handler)();
    }
}
