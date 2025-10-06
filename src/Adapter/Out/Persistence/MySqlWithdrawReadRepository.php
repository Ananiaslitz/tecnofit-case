<?php

namespace Core\Adapter\Out\Persistence;

use Core\Adapter\Out\Persistence\Model\AccountWithdrawModel;
use Core\Domain\Port\WithdrawReadRepository;
use Core\Shared\Sanitization\PixMaskerInterface;

final class MySqlWithdrawReadRepository implements WithdrawReadRepository
{
    public function __construct(
        private AccountWithdrawModel $withdrawModel,
        private PixMaskerInterface $pixMasker
    ) {
    }

    public function listByAccount(string $accountId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $baseQuery = $this->withdrawModel->newQuery()->where('account_id', $accountId);

        $total = $baseQuery->clone()->count();

        $rows = $baseQuery->with('pix')
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = [];
        foreach ($rows as $w) {
            $pix = $w->pix;

            $items[] = [
                'id'            => $w->id,
                'account_id'    => $w->account_id,
                'method'        => $w->method,
                'amount'        => isset($w->amount) ? (float)$w->amount : (isset($w->amount_cents) ? ((int)$w->amount_cents)/100 : null),
                'amount_cents'  => isset($w->amount_cents) ? (int)$w->amount_cents : (isset($w->amount) ? (int)round(((float)$w->amount)*100) : null),
                'scheduled_for' => $w->scheduled_for ? (string)$w->scheduled_for : null,
                'processed_at'  => $w->processed_at ? (string)$w->processed_at : null,
                'done'          => (bool)$w->done,
                'error'         => (bool)$w->error,
                'error_reason'  => $w->error_reason,
                'pix'           => $pix ? [
                    'type' => $pix->type,
                    'key'  => $this->pixMasker->mask((string)$pix->type, (string)$pix->key),
                ] : null,
                'created_at'    => $w->created_at ? (string)$w->created_at : null,
                'updated_at'    => $w->updated_at ? (string)$w->updated_at : null,
            ];
        }

        return ['total' => $total, 'items' => $items];
    }
}