<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestId
{
    private static ?string $fallbackId = null;

    public static function current(): string
    {
        $requestId = self::fromContext()
            ?? self::fromRequest()
            ?? self::fromSession()
            ?? self::fallback();

        self::share($requestId);

        return $requestId;
    }

    public static function make(?string $requestId = null): string
    {
        return $requestId && $requestId !== ''
            ? $requestId
            : str_replace('-', '', (string) Str::uuid());
    }

    public static function share(string $requestId): void
    {
        Log::withContext(['request_id' => $requestId]);
    }

    private static function fromContext(): ?string
    {
        try {
            $shared = Log::sharedContext();

            if (isset($shared['request_id']) && is_string($shared['request_id']) && $shared['request_id'] !== '') {
                return $shared['request_id'];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    private static function fromRequest(): ?string
    {
        try {
            if (app()->bound('request')) {
                $requestId = request()->header('X-Request-ID');

                if (is_string($requestId) && $requestId !== '') {
                    return $requestId;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    private static function fromSession(): ?string
    {
        try {
            if (function_exists('session')) {
                $requestId = session('X-Request-ID');

                if (is_string($requestId) && $requestId !== '') {
                    return $requestId;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    private static function fallback(): string
    {
        if (self::$fallbackId === null) {
            self::$fallbackId = 'cli-'.substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
        }

        return self::$fallbackId;
    }
}
