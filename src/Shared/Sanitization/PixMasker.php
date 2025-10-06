<?php
namespace Core\Shared\Sanitization;

final class PixMasker implements PixMaskerInterface
{
    public static function mask(string $type, string $key): string
    {
        $t = strtolower(trim($type));
        $k = trim($key);

        if ($t === 'email') {
            $at = strpos($k, '@');
            if ($at === false) return self::fallback($k);
            $local = substr($k, 0, $at);
            $domain = substr($k, $at + 1);
            $keep = max(0, min(2, strlen($local)));
            $maskedLocal = substr($local, 0, $keep) . str_repeat('*', max(0, strlen($local) - $keep));
            return $maskedLocal . '@' . $domain;
        }

        if (in_array($t, ['phone','telefone','celular'], true)) {
            $digits = preg_replace('/\D+/', '', $k);
            if ($digits === '') return self::fallback($k);
            $last4 = substr($digits, -4);
            return str_repeat('*', max(0, strlen($digits) - 4)) . $last4;
        }

        if ($t === 'cpf') {
            $digits = preg_replace('/\D+/', '', $k);
            if (strlen($digits) !== 11) return self::fallback($k);
            return substr($digits, 0, 3) . '.***.***-' . substr($digits, 9, 2);
        }

        if ($t === 'cnpj') {
            $digits = preg_replace('/\D+/', '', $k);
            if (strlen($digits) !== 14) return self::fallback($k);
            return substr($digits, 0, 2) . '.***.***/****-' . substr($digits, 12, 2);
        }

        if (in_array($t, ['evp','aleatoria','random','chave_aleatoria'], true)) {
            $len = strlen($k);
            if ($len <= 10) return self::fallback($k);
            return substr($k, 0, 6) . str_repeat('*', $len - 10) . substr($k, -4);
        }

        return self::fallback($k);
    }

    private static function fallback(string $s): string
    {
        $len = strlen($s);
        if ($len <= 4) return str_repeat('*', $len);
        $keepStart = 2; $keepEnd = 2;
        $maskLen = max(0, $len - ($keepStart + $keepEnd));
        return substr($s, 0, $keepStart) . str_repeat('*', $maskLen) . substr($s, -$keepEnd);
    }
}
