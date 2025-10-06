<?php

namespace Core\Domain\Port;


use Core\Domain\Entity\Account;

interface AccountRepository {
    public function byId(string $id): ?Account;
    public function lockById(string $id): ?Account;
    public function save(Account $acc): void;
}
