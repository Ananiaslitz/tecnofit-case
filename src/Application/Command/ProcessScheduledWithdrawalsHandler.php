<?php

namespace Core\Application\Command;

use Core\Adapter\Out\Observability\RichEvent;
use Core\Adapter\Out\Observability\RichEventEmitter;
use Core\Domain\Port\AccountRepository;
use Core\Domain\Port\Clock;
use Core\Domain\Port\TxManager;
use Core\Domain\Port\WithdrawRepository;
use Core\Domain\Service\WithdrawalDomainService;

class ProcessScheduledWithdrawalsHandler
{
    public function __construct(
        private WithdrawalDomainService $domain,
        private AccountRepository $accounts,
        private WithdrawRepository $withdraws,
        private TxManager $tx,
        private Clock $clock,
        private RichEventEmitter $events,
    ) {}

    public function process(): int
    {
        return $this->__invoke();
    }

    public function __invoke(): int
    {
        $now = $this->clock->now();
        $pendings = $this->withdraws->findDueScheduled($now);
        $count = 0;

        foreach ($pendings as $w) {
            $this->tx->transactional(function () use ($w, &$count) {
                $result = $this->domain->processScheduled(
                    $this->accounts,
                    $this->withdraws,
                    $w->id
                );
                $count++;

                $this->events->emit(new RichEvent(
                    'withdraw.processed',
                    '1.0',
                    $result->error ? 'warning' : 'info',
                    'Withdraw processed (scheduled)',
                    attrs: [
                        'account.id' => $result->accountId,
                        'withdraw.id' => $result->id,
                        'withdraw.amount_cents' => (int) $result->amount->cents(),
                        'outcome' => $result->error ? 'failed' : 'success',
                        'error_reason' => $result->errorReason,
                    ],
                    meta: ['component' => 'domain', 'operation' => 'process_scheduled']
                ));
            });
        }

        return $count;
    }
}
