<?php
namespace Core\Adapter\Out\Persistence;

use Core\Adapter\Out\Persistence\Model\AccountWithdrawModel;
use Core\Adapter\Out\Persistence\Model\AccountWithdrawPixModel;
use Core\Domain\Entity\Withdraw;
use Core\Domain\Port\Clock;
use Core\Domain\Port\WithdrawRepository;
use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;
use DateTimeImmutable;

final class MySqlWithdrawRepository implements WithdrawRepository
{
    public function __construct(
        private AccountWithdrawModel $withdrawModel,
        private AccountWithdrawPixModel $pixModel,
        private Clock $clock,
    ) {
    }

    public function save(string $id, string $accountId, Money $amount, string $method, bool $scheduled, ?\DateTimeInterface $scheduledFor, ?PixKey $pix): void
    {
        $this->withdrawModel->newQuery()->updateOrCreate(
            ['id' => $id],
            [
                'account_id'    => $accountId,
                'amount'        => $amount->amount(),
                'amount_cents'  => $amount->cents(),
                'method'        => $method,
                'scheduled'     => $scheduled,
                'scheduled_for' => $scheduledFor?->format('Y-m-d H:i:s'),
            ]
        );

        if ($pix) {
            $this->pixModel->newQuery()->updateOrCreate(
                ['account_withdraw_id' => $id],
                ['type' => $pix->type(), 'key' => $pix->key()]
            );
        }
    }

    public function byId(string $id): ?Withdraw
    {
        $w = $this->withdrawModel->newQuery()->with('pix')->find($id);

        if (! $w || !$w->pix) {
            return null;
        }

        $pixVo = new PixKey($w->pix->type, $w->pix->key);

        return new Withdraw(
            id: $w->id,
            accountId: $w->account_id,
            amount: Money::fromCents((int) $w->amount_cents),
            pix: $pixVo,
            scheduled: (bool) $w->scheduled,
            scheduledFor: $w->scheduled_for ? new \DateTimeImmutable($w->scheduled_for) : null,
            method: $w->method,
            done: (bool) $w->done,
            error: (bool) $w->error,
            errorReason: $w->error_reason
        );
    }
    public function markDone(string $id): void
    {
        $this->withdrawModel->newQuery()->where('id', $id)->update([
            'done'         => true,
            'error'        => false,
            'error_reason' => null,
            'updated_at'   => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(string $id, string $reason): void
    {
        $this->withdrawModel->newQuery()->where('id', $id)->update([
            'done'         => true,
            'error'        => true,
            'error_reason' => $reason,
            'updated_at'   => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findDueScheduled(\DateTimeInterface $now, int $limit = 100): array
    {
        $rows = $this->withdrawModel->newQuery()
            ->has('pix')
            ->with('pix')
            ->where('scheduled', 1)
            ->where('done', 0)
            ->where('scheduled_for', '<=', $now->format('Y-m-d H:i:s'))
            ->orderBy('scheduled_for', 'asc')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $w) {
            $pixVo = new PixKey($w->pix->type, $w->pix->key);

            $out[] = new Withdraw(
                id: $w->id,
                accountId: $w->account_id,
                amount: Money::fromCents((int) $w->amount_cents),
                pix: $pixVo,
                scheduled: (bool) $w->scheduled,
                scheduledFor: $w->scheduled_for ? new \DateTimeImmutable($w->scheduled_for) : null,
                method: $w->method, done: (bool) $w->done,
                error: (bool) $w->error, errorReason: $w->error_reason
            );
        }

        return $out;
    }
}