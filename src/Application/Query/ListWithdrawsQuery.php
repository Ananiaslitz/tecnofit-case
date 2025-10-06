<?php

namespace Core\Application\Query;

final class ListWithdrawsQuery
{
    public function __construct(
        public string $accountId,
        public int $page = 1,
        public int $perPage = 20
    ) {}
}
