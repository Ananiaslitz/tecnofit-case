<?php

namespace Core\Domain\Port;

use Core\Domain\ValueObject\Money;

interface MailerPort
{
    public function sendWithdrawEmail(string $to, string $pixType, string $pixKey, Money $amount, \DateTimeInterface $when): void;
}
