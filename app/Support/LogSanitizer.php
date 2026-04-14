<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

class LogSanitizer
{
    /**
     * @var string[]
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'passwd',
        'pwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'api-token',
        'api_token',
        'authorization',
        'cookie',
        'set-cookie',
        'session',
        'session_key',
        'dbpwd',
        'db_password',
        'mail_password',
        'smtp_password',
        'private_key',
        'access_key',
        'credential',
    ];

    public static function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::shouldMask($key)) {
            return self::maskedValue($value);
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $itemKey => $itemValue) {
                $result[$itemKey] = self::sanitize($itemValue, is_string($itemKey) ? $itemKey : null);
            }

            return $result;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Arrayable) {
            return self::sanitize($value->toArray(), $key);
        }

        if ($value instanceof JsonSerializable) {
            return self::sanitize($value->jsonSerialize(), $key);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return self::sanitize($value->toArray(), $key);
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return ['class' => get_class($value)];
        }

        return $value;
    }

    private static function shouldMask(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $candidate) {
            if ($normalized === $candidate || str_contains($normalized, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function maskedValue(mixed $value): string
    {
        if (is_array($value)) {
            return '[REDACTED_ARRAY]';
        }

        if (is_object($value)) {
            return '[REDACTED_OBJECT]';
        }

        return '[REDACTED]';
    }
}
