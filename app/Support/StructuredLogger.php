<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class StructuredLogger
{
    public static function debug(string $event, array $context = [], ?string $channel = null): void
    {
        self::write('debug', $event, $context, $channel);
    }

    public static function info(string $event, array $context = [], ?string $channel = null): void
    {
        self::write('info', $event, $context, $channel);
    }

    public static function warning(string $event, array $context = [], ?string $channel = null): void
    {
        self::write('warning', $event, $context, $channel);
    }

    public static function error(string $event, array $context = [], ?string $channel = null): void
    {
        self::write('error', $event, $context, $channel);
    }

    public static function securityInfo(string $event, array $context = []): void
    {
        self::write('info', $event, $context, 'security');
    }

    public static function securityWarning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context, 'security');
    }

    public static function securityError(string $event, array $context = []): void
    {
        self::write('error', $event, $context, 'security');
    }

    private static function write(string $level, string $event, array $context = [], ?string $channel = null): void
    {
        $payload = array_merge(self::baseContext(), LogSanitizer::sanitize($context));

        if ($channel) {
            Log::channel($channel)->{$level}($event, $payload);

            return;
        }

        Log::{$level}($event, $payload);
    }

    private static function baseContext(): array
    {
        return [
            'request_id' => RequestId::current(),
            'app_env' => app()->environment(),
        ];
    }
}
