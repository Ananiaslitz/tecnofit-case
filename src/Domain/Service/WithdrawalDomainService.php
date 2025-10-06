<?php
declare(strict_types=1);

namespace Core\Domain\Service;

use Core\Domain\Port\AccountRepository;
use Core\Domain\Port\WithdrawRepository;
use Core\Domain\Port\IdGenerator;
use Core\Domain\Port\Clock;
use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;
use Core\Domain\ValueObject\Schedule;
use Core\Shared\Exception\BusinessException;

class WithdrawalDomainService
{
    public function request(
        AccountRepository $accounts,
        WithdrawRepository $withdraws,
        IdGenerator $ids,
        Clock $clock,
        string $accountId,
        Money $amount,
        PixKey $pix,
        Schedule $schedule
    ): string {
        $wid = $ids->uuid();

        $withdraws->save(
            id: $wid,
            accountId: $accountId,
            amount: $amount,
            method: 'PIX',
            scheduled: $schedule->isScheduled(),
            scheduledFor: $schedule->scheduledAt(),
            pix: $pix
        );

        if (! $schedule->isScheduled()) {
            $acc = $accounts->byId($accountId, true);
            if (! $acc) {
                $withdraws->markFailed($wid, 'ACCOUNT_NOT_FOUND');
                return $wid;
            }

            try {
                $acc->debit($amount);
            } catch (BusinessException $e) {
                $withdraws->markFailed($wid, $e->getMessage());
                return $wid;
            }

            $accounts->save($acc);
            $withdraws->markDone($wid);
        }
        return $wid;
    }

    public function processScheduled(
        AccountRepository $accounts,
        WithdrawRepository $withdraws,
        string $withdrawId
    ) {
        $w = $withdraws->byId($withdrawId);
        if (! $w) {
            return null;
        }
        if ($w->done) {
            return $w;
        }

        $acc = $accounts->byId($w->accountId, true);
        if (! $acc) {
            $withdraws->markFailed($w->id, 'ACCOUNT_NOT_FOUND');
            return $withdraws->byId($w->id);
        }

        if (! $acc->balance->gte($w->amount)) {
            $withdraws->markFailed($w->id, 'INSUFFICIENT_FUNDS');
            return $withdraws->byId($w->id);
        }

        $acc->debit($w->amount);
        $accounts->save($acc);
        $withdraws->markDone($w->id);

        return $withdraws->byId($w->id);
    }
}