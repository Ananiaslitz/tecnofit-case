<?php

namespace Core\Shared\Sanitization;

interface PixMaskerInterface
{
    public static function mask(string $type, string $key): string;
}