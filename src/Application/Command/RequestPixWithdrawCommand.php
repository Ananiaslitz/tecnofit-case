<?php
namespace Core\Application\Command;

final class RequestPixWithdrawCommand
{
    public string $accountId;
    public string $method;
    public string $pixType;
    public string $pixKey;
    public float $amount;
    public ?string $schedule;

    public function __construct(
        string $accountId,
        ?string $method,
        string $pixType,
        string $pixKey,
        float $amount,
        ?string $schedule
    ) {
        $this->accountId = $accountId;
        $this->method    = $method ? strtoupper($method) : 'PIX';
        $this->pixType   = strtolower($pixType);
        $this->pixKey    = $pixKey;
        $this->amount    = $amount;
        $this->schedule  = $schedule;
    }

    public static function fromHttp(string $accountId, array $body): self
    {
        $method   = $body['method'] ?? 'PIX';
        $pixType  = $body['pix']['type'] ?? 'email';
        $pixKey   = $body['pix']['key']  ?? '';
        $amount   = isset($body['amount']) ? (float) $body['amount'] : 0.0;
        $schedule = $body['schedule'] ?? null;

        return new self($accountId, $method, $pixType, $pixKey, $amount, $schedule);
    }
}
