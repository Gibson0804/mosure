<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'sql_logging' => [
        'enabled' => env('SQL_LOG_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
        'full' => env('SQL_LOG_FULL', env('APP_ENV', 'production') === 'local'),
        'slow_ms' => (int) env('SQL_LOG_SLOW_MS', 500),
        'include_bindings' => env('SQL_LOG_BINDINGS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        // 默认的日志堆栈
        'stack' => [
            'driver' => 'stack',
            'name' => 'base',
            'channels' => ['base'],
            'ignore_exceptions' => false,
        ],

        // 请求日志 - 记录所有HTTP请求
        'request' => [
            'driver' => 'daily',
            'name' => 'request',
            'path' => storage_path('logs/request.log'),
            'level' => 'info',
            'days' => 30,
            'formatter' => App\Logging\CustomLogFormatter::class,
            'formatter_with' => [
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ],

        // 业务日志 - 记录业务逻辑
        'base' => [
            'driver' => 'daily',
            'name' => 'base',
            'path' => storage_path('logs/base.log'),
            'level' => 'info',
            'days' => 90,
            'formatter' => App\Logging\CustomLogFormatter::class,
            'formatter_with' => [
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ],

        // SQL日志 - 记录所有SQL查询
        'sql' => [
            'driver' => 'daily',
            'name' => 'sql',
            'path' => storage_path('logs/sql.log'),
            'level' => 'debug',
            'days' => 7, // SQL日志通常保留较短时间
            'formatter' => App\Logging\CustomLogFormatter::class,
        ],

        // 慢查询日志 - 仅记录超过阈值的 SQL
        'slow_query' => [
            'driver' => 'daily',
            'name' => 'slow_query',
            'path' => storage_path('logs/slow_query.log'),
            'level' => 'warning',
            'days' => 14,
            'formatter' => App\Logging\CustomLogFormatter::class,
            'formatter_with' => [
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ],

        // 安全日志 - 记录鉴权、权限、安全拦截等事件
        'security' => [
            'driver' => 'daily',
            'name' => 'security',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 180,
            'formatter' => App\Logging\CustomLogFormatter::class,
            'formatter_with' => [
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
