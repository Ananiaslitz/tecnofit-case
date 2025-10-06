<?php
namespace Core\Shared\Transaction;

use Core\Domain\Port\TxManager;
use Hyperf\DbConnection\Db;

final class HyperfTxManager implements TxManager
{
    public function transactional(callable $fn): mixed
    {
        return Db::transaction(static fn () => $fn());
    }
}