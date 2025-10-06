<?php

namespace Core\Domain\Port;

interface TxManager {
    public function transactional(callable $fn): mixed;
}