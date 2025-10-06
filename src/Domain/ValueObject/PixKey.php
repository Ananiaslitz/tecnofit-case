<?php
declare(strict_types=1);

namespace Core\Domain\ValueObject;

use Core\Shared\Exception\BusinessException;

final class PixKey
{
    public function __construct(
        private string $type,
        private string $key
    ) {
        $this->type = strtolower(trim($this->type));
        $this->key  = trim($this->key);

        if (!in_array($this->type, ['email', 'phone', 'random'], true)) {
            throw new BusinessException('Unsupported PIX key type.');
        }

        if ($this->type === 'email' && !filter_var($this->key, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException('Invalid PIX e-mail.');
        }

        if ($this->type === 'phone' && strlen(preg_replace('/\D+/', '', $this->key)) < 8) {
            throw new BusinessException('Invalid PIX phone.');
        }

        if ($this->type === 'random' && strlen($this->key) < 16) {
            throw new BusinessException('Invalid PIX random key.');
        }
    }

    public function type(): string
    {
        return $this->type;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function mask(): string
    {
        return match ($this->type) {
            'email'  => $this->maskEmail($this->key),
            'phone'  => $this->maskPhone($this->key),
            'random' => $this->maskRandom($this->key),
            default  => '***',
        };
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = explode('@', $email, 2);
        if ($user === '') {
            return '***@' . $domain;
        }
        $keep = min(3, max(1, (int) floor(strlen($user) / 2)));
        return substr($user, 0, $keep) . str_repeat('*', max(3, strlen($user) - $keep)) . '@' . $domain;
    }

    private function maskPhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }
        $prefix = substr($digits, 0, 2);
        $suffix = substr($digits, -2);
        $masked = $prefix . str_repeat('*', max(2, strlen($digits) - 4)) . $suffix;
        return '+' . $masked;
    }

    private function maskRandom(string $key): string
    {
        $len = strlen($key);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }
        $prefix = substr($key, 0, 3);
        $suffix = substr($key, -3);
        return $prefix . str_repeat('*', $len - 6) . $suffix;
    }
}
