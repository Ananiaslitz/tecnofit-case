<?php
namespace Core\Adapter\Out\Persistence;

use Core\Adapter\Out\Persistence\Model\AccountModel;
use Core\Domain\Entity\Account;
use Core\Domain\Port\AccountRepository;
use Core\Domain\ValueObject\Money;
use Core\Shared\Exception\BusinessException;

final class MySqlAccountRepository implements AccountRepository
{
    public function __construct(private AccountModel $model) {}

    public function byId(string $id, bool $forUpdate = false): ?Account {
        $q = $this->model->newQuery()->where('id', $id);
        if ($forUpdate) { $q->lockForUpdate(); }
        $row = $q->first();
        if (! $row) return null;

        return new Account(
            id: $row->id,
            name: $row->name,
            balance: Money::fromCentsForBalance((int)$row->balance_cents)
        );
    }

    public function save(Account $acc): void {
        $this->model->newQuery()->updateOrCreate(
            ['id' => $acc->id],
            [
                'name' => $acc->name,
                'balance_cents' => $acc->balance->cents(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public function lockById(string $id): Account
    {
        $m = $this->model->newQuery()->lockForUpdate()->find($id);
        if (! $m) {
            throw new BusinessException('Account not found');
        }

        return new Account(
            id: $m->id,
            name: $m->name,
            balance: Money::fromCentsForBalance((int) $m->balance_cents)
        );
    }
}