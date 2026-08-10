<?php

namespace App\Support\Observability;

use Illuminate\Support\Str;

final class RequestIdentifier
{
    public const MAX_LENGTH = 64;

    public static function generate(): string
    {
        return (string) Str::uuid();
    }

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/D', $value) === 1
            ? $value
            : null;
    }
}
