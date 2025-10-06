<?php
declare(strict_types=1);

namespace Core\Shared\Http;

use Hyperf\Redis\Redis;

class IdempotencyService
{
    private int $ttlSeconds;

    public function __construct(private Redis $redis)
    {
        $this->ttlSeconds = (int) (getenv('IDEMPOTENCY_TTL_SECONDS') ?: 3600);
    }

    private function dataKey(string $key): string
    {
        return "idem:$key:data";
    }

    private function lockKey(string $key): string
    {
        return "idem:$key:lock";
    }

    public function fingerprint(array $payload): string
    {
        $normalize = function ($v) use (&$normalize) {
            if (is_array($v)) {
                ksort($v);
                return array_map($normalize, $v);
            }
            return $v;
        };
        $payload = $normalize($payload);
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function get(string $key): ?array
    {
        $json = $this->redis->get($this->dataKey($key));
        if (! $json) return null;
        $data = json_decode($json, true);
        return $data['body'] ?? null;
    }

    public function getRecord(string $key): ?array
    {
        $json = $this->redis->get($this->dataKey($key));
        return $json ? json_decode($json, true) : null;
    }

    public function acquire(string $key, string $fingerprint): bool
    {
        $lock = $this->lockKey($key);
        $ok = $this->redis->setnx($lock, $fingerprint);
        if ($ok) {
            $this->redis->expire($lock, $this->ttlSeconds);
        }
        return (bool) $ok;
    }

    public function inflightFingerprint(string $key): ?string
    {
        $v = $this->redis->get($this->lockKey($key));
        return $v ?: null;
    }

    public function store(string $key, string $fingerprint, int $status, array $headers, array $body): void
    {
        $data = [
            'fp'      => $fingerprint,
            'status'  => $status,
            'headers' => $headers,
            'body'    => $body,
        ];
        $this->redis->setex(
            $this->dataKey($key),
            $this->ttlSeconds,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $this->redis->del($this->lockKey($key));
    }

    public function put(string $key, array $body, int $status = 200, array $headers = [], ?string $fingerprint = null): void
    {
        $fp = $fingerprint ?? $this->fingerprint($body);
        $this->store($key, $fp, $status, $headers, $body);
    }
}
