<?php

namespace App\Logging;

use App\Support\LogSanitizer;
use App\Support\RequestId;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

class CustomLogFormatter extends LineFormatter
{
    /**
     * 格式化日志记录
     *
     * @param  LogRecord  $record
     * @return string
     */
    public function __construct(?string $dateFormat = 'Y-m-d H:i:s')
    {
        parent::__construct(
            format: null,
            dateFormat: $dateFormat,
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true
        );
    }

    public function format(LogRecord $record): string
    {
        $context = LogSanitizer::sanitize($record->context);
        $extra = LogSanitizer::sanitize($record->extra);

        $requestId = isset($context['request_id']) && is_string($context['request_id']) && $context['request_id'] !== ''
            ? $context['request_id']
            : RequestId::current();
        unset($context['request_id']);

        $payload = [
            'timestamp' => $record->datetime->format($this->dateFormat),
            'channel' => $record->channel,
            'level' => strtolower($record->level->getName()),
            'event' => (string) $record->message,
            'request_id' => $requestId,
        ];

        foreach (['user_id', 'project_prefix', 'app_env'] as $sharedKey) {
            if (array_key_exists($sharedKey, $context)) {
                $payload[$sharedKey] = $context[$sharedKey];
                unset($context[$sharedKey]);
            }
        }

        if (! empty($context)) {
            $payload['context'] = $context;
        }

        if (! empty($extra)) {
            $payload['extra'] = $extra;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
