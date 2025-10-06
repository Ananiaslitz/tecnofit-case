<?php
namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Logger\LoggerFactory;

#[Listener]
class DbQueryExecutedListener
{
    public function __construct(private LoggerFactory $loggerFactory) {}

    public function process(object $event): void
    {
        if (! $event instanceof QueryExecuted) return;

        $sql = $event->sql;
        $bindings = $event->bindings ?? [];
        $time = number_format($event->time / 1000, 2);

        $this->loggerFactory->get('sql')->info(
            sprintf('[%s] %s', $time, $sql),
            ['bindings' => $bindings]
        );
    }
}
