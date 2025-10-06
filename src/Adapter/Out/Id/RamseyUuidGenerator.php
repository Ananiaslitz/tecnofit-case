<?php

namespace Core\Adapter\Out\Id;

use Core\Domain\Port\IdGenerator;
use Ramsey\Uuid\Uuid;

final class RamseyUuidGenerator implements IdGenerator
{
    public function uuid(): string
    {
        return Uuid::uuid4()->toString();
    }
}
