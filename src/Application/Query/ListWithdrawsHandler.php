<?php

namespace Core\Application\Query;

use Core\Domain\Port\WithdrawReadRepository;

class ListWithdrawsHandler
{
    public function __construct(private WithdrawReadRepository $repo) {}

    public function __invoke(ListWithdrawsQuery $q): array
    {
        $page = max(1, $q->page);
        $perPage = max(1, min(100, $q->perPage));

        $res = $this->repo->listByAccount($q->accountId, $page, $perPage);

        return [
            'ok'       => true,
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $res['total'],
            'items'    => $res['items'],
        ];
    }
}
