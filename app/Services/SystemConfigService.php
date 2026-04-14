<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemConfigService
{
    private const DEFAULT_CONFIG = [
        'ai_providers' => [
            'active_provider' => 'zhipu',
            'zhipu' => [
                'label' => '智谱（BigModel）',
                'completion_url' => 'https://open.bigmodel.cn/api/paas/v4',
                'api_key' => '',
                'model' => 'glm-4-flash',
                'models' => ['glm-4', 'glm-4-air', 'glm-4-airx', 'glm-4-flash', 'glm-4-plus'],
            ],
            'deepseek' => [
                'label' => 'DeepSeek',
                'completion_url' => 'https://api.deepseek.com/v1',
                'api_key' => '',
                'model' => 'deepseek-chat',
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
            ],
            'tencent' => [
                'label' => '腾讯混元（Hunyuan）',
                'completion_url' => 'https://api.hunyuan.cloud.tencent.com/v1',
                'api_key' => '',
                'model' => 'hunyuan-turbos-latest',
                'models' => ['hunyuan-turbos-latest', 'hunyuan-standard-256k', 'hunyuan-standard'],
            ],
            'alibaba' => [
                'label' => '阿里百炼（DashScope）',
                'completion_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
                'api_key' => '',
                'model' => 'qwen-turbo',
                'models' => ['qwen-turbo', 'qwen-plus', 'qwen-max', 'qwen-long'],
            ],
            'kimi' => [
                'label' => 'Kimi（月之暗面）',
                'completion_url' => 'https://api.moonshot.cn/v1',
                'api_key' => '',
                'model' => 'moonshot-v1-8k',
                'models' => ['moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k'],
            ],
            'custom' => [
                'label' => '🔧 自定义（OpenAI兼容）',
                'completion_url' => '',
                'api_key' => '',
                'model' => '',
                'models' => [],
            ],
        ],
        'storage' => [
            'default' => 'local',
            's3' => [
                'provider' => 'generic',
                'key' => '',
                'secret' => '',
                'region' => '',
                'bucket' => '',
                'endpoint' => '',
                'url' => '',
                'use_path_style_endpoint' => false,
            ],
            'cos' => [
                'secret_id' => '',
                'secret_key' => '',
                'region' => 'ap-guangzhou',
                'bucket' => '',
                'cdn_url' => '',
            ],
        ],
        'mail' => [
            'mailer' => 'smtp',
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_address' => '',
            'from_name' => '',
        ],
        'security' => [
            'session_lifetime' => 120,
            'password_min_length' => 8,
        ],
    ];

    private array $secretPaths = [
        'ai_providers.zhipu.api_key',
        'ai_providers.deepseek.api_key',
        'ai_providers.tencent.api_key',
        'ai_providers.alibaba.api_key',
        'ai_providers.kimi.api_key',
        'ai_providers.custom.api_key',
        'mail.password',
        'storage.s3.key',
        'storage.s3.secret',
        'storage.cos.secret_id',
        'storage.cos.secret_key',
    ];

    public function getConfig(): array
    {
        $result = $this->loadConfig();

        // 掩码敏感字段
        foreach ($this->secretPaths as $path) {
            $raw = Arr::get($result, $path);
            if (is_string($raw) && $raw !== '') {
                Arr::set($result, $path, $this->maskSecret($raw));
            }
        }

        return $result;
    }

    public function getConfigRaw(): array
    {
        return $this->loadConfig();
    }

    public function saveConfig(array $configs): array
    {
        $this->ensureTable();

        $configs = $this->normalizeIncomingConfigs($configs);

        if (empty($configs)) {
            return $this->getConfig();
        }

        $current = $this->getConfig();
        $entries = [];

        foreach ($configs as $group => $values) {
            if (! is_array($values)) {
                continue;
            }
            if (! array_key_exists($group, $current)) {
                continue;
            }
            $existingGroup = isset($current[$group]) && is_array($current[$group]) ? $current[$group] : [];
            $mergedGroup = $this->mergeGroupValues($existingGroup, $values);
            $entries = array_merge($entries, $this->flattenConfigEntries([$group => $mergedGroup]));
        }

        if (empty($entries)) {
            return $this->getConfig();
        }

        DB::transaction(function () use ($entries) {
            foreach ($entries as $entry) {
                $group = $entry['group'];
                $key = $entry['key'];
                $val = $entry['value'];

                $isSecret = in_array(trim($group.'.'.$key, '.'), $this->secretPaths, true);

                $row = SystemSetting::query()->where('config_group', $group)->where('config_key', $key)->first();

                // 跳过掩码占位（视为不修改）
                if ($isSecret && is_string($val) && Str::startsWith($val, '****')) {
                    if ($row) {
                        continue;
                    } else {
                        // 新建但仍是掩码，跳过不保存
                        continue;
                    }
                }

                // 跳过空值，避免数据库约束错误
                if ($val === null || $val === '') {
                    if ($row) {
                        // 如果已存在记录且值为空，删除该记录
                        $row->delete();
                    }

                    continue;
                }

                $payload = [
                    'config_group' => $group,
                    'config_key' => $key,
                ];

                if ($isSecret && is_string($val) && $val !== '') {
                    $payload['config_value'] = Crypt::encryptString($val);
                    $payload['is_encrypted'] = 1;
                } else {
                    $payload['config_value'] = $val;
                    $payload['is_encrypted'] = 0;
                }

                if ($row) {
                    $row->fill($payload)->save();
                } else {
                    SystemSetting::query()->create($payload);
                }
            }
        });

        return $this->getConfig();
    }

    public function testMail(array $mail): array
    {
        $host = (string) ($mail['host'] ?? '');
        $port = (int) ($mail['port'] ?? 0);
        if ($host === '' || $port <= 0) {
            return ['ok' => false, 'message' => '请填写正确的 SMTP 主机与端口'];
        }
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, 5.0);
        if ($fp) {
            fclose($fp);

            return ['ok' => true, 'message' => 'TCP 连接成功（未验证账号/加密）。'];
        }

        return ['ok' => false, 'message' => '无法连接到 SMTP 服务器: '.($errstr ?: 'unknown')];
    }

    public function testProvider(string $provider, array $cfg): array
    {
        try {
            if ($provider === 'zhipu') {
                $key = $this->resolveProviderApiKey($provider, $cfg, '请填写有效的 智谱 API Key');
                if (! is_string($key)) {
                    return $key;
                }

                $completionUrl = (string) ($cfg['completion_url'] ?? 'https://open.bigmodel.cn/api/paas/v4');
                $model = (string) ($cfg['model'] ?? 'glm-4-flash');

                return $this->testOpenAiCompatibleProvider($completionUrl, $key, $model, '智谱 可用');
            }

            if ($provider === 'deepseek') {
                $key = $this->resolveProviderApiKey($provider, $cfg, '请填写有效的 DeepSeek API Key');
                if (! is_string($key)) {
                    return $key;
                }

                $completionUrl = (string) ($cfg['completion_url'] ?? 'https://api.deepseek.com/v1');
                $model = (string) ($cfg['model'] ?? 'deepseek-chat');

                return $this->testOpenAiCompatibleProvider($completionUrl, $key, $model, 'DeepSeek 可用');
            }

            if ($provider === 'tencent') {
                $key = $this->resolveProviderApiKey($provider, $cfg, '请填写有效的 腾讯混元 API Key');
                if (! is_string($key)) {
                    return $key;
                }

                $completionUrl = (string) ($cfg['completion_url'] ?? 'https://api.hunyuan.cloud.tencent.com/v1');
                $model = (string) ($cfg['model'] ?? 'hunyuan-turbos-latest');

                return $this->testOpenAiCompatibleProvider($completionUrl, $key, $model, '腾讯混元 可用');
            }

            if ($provider === 'alibaba') {
                $key = $this->resolveProviderApiKey($provider, $cfg, '请填写有效的 阿里百炼 API Key');
                if (! is_string($key)) {
                    return $key;
                }

                $completionUrl = (string) ($cfg['completion_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1');
                $model = (string) ($cfg['model'] ?? 'qwen-turbo');

                return $this->testOpenAiCompatibleProvider($completionUrl, $key, $model, '阿里百炼 可用');
            }

            if ($provider === 'kimi') {
                $key = $this->resolveProviderApiKey($provider, $cfg, '请填写有效的 Kimi API Key');
                if (! is_string($key)) {
                    return $key;
                }

                $completionUrl = (string) ($cfg['completion_url'] ?? 'https://api.moonshot.cn/v1');
                $model = (string) ($cfg['model'] ?? 'moonshot-v1-8k');

                return $this->testOpenAiCompatibleProvider($completionUrl, $key, $model, 'Kimi 可用');
            }

            if ($provider === 'custom') {
                $key = $this->resolveProviderApiKey($provider, $cfg, '请填写有效的 自定义 API Key');
                if (! is_string($key)) {
                    return $key;
                }

                $completionUrl = (string) ($cfg['completion_url'] ?? '');
                $model = (string) ($cfg['model'] ?? '');
                if ($completionUrl === '') {
                    return ['ok' => false, 'message' => '请填写有效的 API 地址'];
                }
                if ($model === '') {
                    return ['ok' => false, 'message' => '请填写有效的模型名称'];
                }

                return $this->testOpenAiCompatibleProvider($completionUrl, $key, $model, '自定义模型服务 可用');
            }

            return ['ok' => false, 'message' => '未知 Provider'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => '测试失败: '.$e->getMessage()];
        }
    }

    public function testStorage(array $storage): array
    {
        $current = $this->getConfigRaw();
        $merged = $this->mergeGroupValues(
            isset($current['storage']) && is_array($current['storage']) ? $current['storage'] : [],
            $storage
        );
        $normalized = $this->normalizeConfig(['storage' => $merged]);
        $storageConfig = $this->resolveMaskedStorageSecrets($normalized['storage'] ?? [], $current['storage'] ?? []);
        $default = (string) ($storageConfig['default'] ?? 'local');

        if ($default === 'local') {
            $disk = Storage::disk('public');
            $probePath = 'healthchecks/system-storage-'.Str::uuid().'.txt';
            $content = 'ok '.now()->toDateTimeString();

            $disk->put($probePath, $content);
            $disk->delete($probePath);

            return [
                'ok' => true,
                'message' => '本地存储可用',
                'disk' => 'public',
            ];
        }

        if ($default !== 's3') {
            return ['ok' => false, 'message' => '暂不支持该存储类型'];
        }

        $s3 = isset($storageConfig['s3']) && is_array($storageConfig['s3']) ? $storageConfig['s3'] : [];
        foreach (['key', 'secret', 'bucket'] as $field) {
            if (trim((string) ($s3[$field] ?? '')) === '') {
                return ['ok' => false, 'message' => '请完整填写 S3 存储的 Key、Secret 与 Bucket'];
            }
        }

        $diskConfig = [
            'driver' => 's3',
            'key' => (string) ($s3['key'] ?? ''),
            'secret' => (string) ($s3['secret'] ?? ''),
            'region' => trim((string) ($s3['region'] ?? '')) !== '' ? (string) $s3['region'] : 'us-east-1',
            'bucket' => (string) ($s3['bucket'] ?? ''),
            'endpoint' => trim((string) ($s3['endpoint'] ?? '')) !== '' ? (string) $s3['endpoint'] : null,
            'url' => trim((string) ($s3['url'] ?? '')) !== '' ? rtrim((string) $s3['url'], '/') : null,
            'use_path_style_endpoint' => filter_var($s3['use_path_style_endpoint'] ?? false, FILTER_VALIDATE_BOOL),
            'throw' => true,
        ];

        $disk = Storage::build($diskConfig);
        $probePath = 'healthchecks/system-storage-'.Str::uuid().'.txt';
        $content = 'ok '.now()->toDateTimeString();

        $disk->put($probePath, $content);
        $exists = $disk->exists($probePath);
        $disk->delete($probePath);

        if (! $exists) {
            return ['ok' => false, 'message' => '存储写入后校验失败，请检查 Bucket 权限配置'];
        }

        return [
            'ok' => true,
            'message' => 'S3 兼容对象存储连接成功',
            'provider' => (string) ($s3['provider'] ?? 'generic'),
            'bucket' => (string) ($s3['bucket'] ?? ''),
            'endpoint' => (string) ($s3['endpoint'] ?? ''),
        ];
    }

    private function structuredDefaults(): array
    {
        return json_decode(json_encode(self::DEFAULT_CONFIG), true);
    }

    private function loadConfig(): array
    {
        $this->ensureTable();
        $result = $this->structuredDefaults();

        $stored = SystemSetting::query()->get();
        foreach ($stored as $item) {
            $group = $item->config_group;
            $key = $item->config_key;
            $value = $item->config_value;
            if ($item->is_encrypted && $value !== null && $value !== '') {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    Log::warning('system_setting_decrypt_failed', ['group' => $group, 'key' => $key]);
                    $value = '';
                }
            }
            if ($group) {
                Arr::set($result, $group.'.'.$key, $value);
            } else {
                Arr::set($result, $key, $value);
            }
        }

        return $this->normalizeConfig($result);
    }

    private function flattenConfigEntries(array $config): array
    {
        $entries = [];
        $flatten = function ($array, $prefix = '', &$output = []) use (&$flatten) {
            foreach ($array as $key => $value) {
                $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                if (is_array($value) && $value === []) {
                    // Treat empty arrays as empty values so persistence removes old rows
                    // instead of trying to insert a PHP array into a text column.
                    $output[$fullKey] = null;
                } elseif (is_array($value)) {
                    $flatten($value, $fullKey, $output);
                } else {
                    $output[$fullKey] = $value;
                }
            }

            return $output;
        };

        $flat = $flatten($config);

        foreach ($flat as $dotKey => $value) {
            $parts = explode('.', $dotKey);
            $configKey = array_pop($parts);
            $group = count($parts) > 0 ? implode('.', $parts) : null;
            $entries[] = [
                'group' => $group,
                'key' => (string) $configKey,
                'value' => $value,
            ];
        }

        return $entries;
    }

    private function mergeGroupValues(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssocArray($value)) {
                    $existing[$key] = $this->mergeGroupValues(
                        isset($existing[$key]) && is_array($existing[$key]) ? $existing[$key] : [],
                        $value
                    );
                } else {
                    $existing[$key] = $value;
                }
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    private function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function normalizeConfig(array $config): array
    {
        $storage = Arr::get($config, 'storage', []);
        if (! is_array($storage)) {
            return $config;
        }

        $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];
        $cos = isset($storage['cos']) && is_array($storage['cos']) ? $storage['cos'] : [];
        $default = (string) ($storage['default'] ?? 'local');

        if (($default === 'cos' || ! $this->hasUsableS3Config($s3)) && $this->hasLegacyCosConfig($cos)) {
            $s3 = $this->mergeLegacyCosIntoS3($s3, $cos);
        }

        if ($default === 'cos') {
            $default = 's3';
        }

        $storage['default'] = in_array($default, ['local', 's3'], true) ? $default : 'local';
        $storage['s3'] = $this->normalizeS3Settings($s3);

        Arr::set($config, 'storage', $storage);

        return $config;
    }

    private function normalizeIncomingConfigs(array $configs): array
    {
        if (! isset($configs['storage']) || ! is_array($configs['storage'])) {
            return $configs;
        }

        $storage = $configs['storage'];
        $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];
        $cos = isset($storage['cos']) && is_array($storage['cos']) ? $storage['cos'] : [];
        $default = (string) ($storage['default'] ?? 'local');

        if (($default === 'cos' || ! $this->hasUsableS3Config($s3)) && $this->hasLegacyCosConfig($cos)) {
            $s3 = $this->mergeLegacyCosIntoS3($s3, $cos);
        }

        $storage['default'] = $default === 'cos' ? 's3' : $default;
        $storage['s3'] = $this->normalizeS3Settings($s3);
        $configs['storage'] = $storage;

        return $configs;
    }

    private function normalizeS3Settings(array $settings): array
    {
        $provider = (string) ($settings['provider'] ?? '');
        if ($provider === '') {
            $provider = $this->inferStorageProvider($settings);
        }

        $region = trim((string) ($settings['region'] ?? ''));
        if ($provider === 'cos' && $region === '') {
            $region = 'ap-guangzhou';
        }

        $endpoint = trim((string) ($settings['endpoint'] ?? ''));
        if ($provider === 'cos' && $endpoint === '' && $region !== '') {
            $endpoint = 'https://cos.'.$region.'.myqcloud.com';
        }

        $url = trim((string) ($settings['url'] ?? ''));

        return [
            'provider' => $provider !== '' ? $provider : 'generic',
            'key' => (string) ($settings['key'] ?? ''),
            'secret' => (string) ($settings['secret'] ?? ''),
            'region' => $region,
            'bucket' => trim((string) ($settings['bucket'] ?? '')),
            'endpoint' => $endpoint,
            'url' => $url !== '' ? rtrim($url, '/') : '',
            'use_path_style_endpoint' => filter_var($settings['use_path_style_endpoint'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    private function inferStorageProvider(array $settings): string
    {
        if (! $this->hasUsableS3Config($settings)) {
            return 'generic';
        }

        $endpoint = strtolower(trim((string) ($settings['endpoint'] ?? '')));
        if (str_contains($endpoint, 'myqcloud.com')) {
            return 'cos';
        }
        if (str_contains($endpoint, 'aliyuncs.com') || str_contains($endpoint, 'aliyun.com')) {
            return 'aliyun';
        }
        if (str_contains($endpoint, 'qiniucs.com') || str_contains($endpoint, 'qiniuapi.com')) {
            return 'qiniu';
        }
        if ($endpoint === '' || str_contains($endpoint, 'amazonaws.com')) {
            return 'aws';
        }

        return 'generic';
    }

    private function hasUsableS3Config(array $settings): bool
    {
        foreach (['key', 'secret', 'bucket', 'endpoint', 'url', 'region'] as $field) {
            if (trim((string) ($settings[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function hasLegacyCosConfig(array $settings): bool
    {
        foreach (['secret_id', 'secret_key', 'bucket', 'cdn_url', 'region'] as $field) {
            if (trim((string) ($settings[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolveMaskedStorageSecrets(array $storage, array $currentStorage): array
    {
        $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];
        $currentS3 = isset($currentStorage['s3']) && is_array($currentStorage['s3']) ? $currentStorage['s3'] : [];

        foreach (['key', 'secret'] as $field) {
            $value = (string) ($s3[$field] ?? '');
            if ($value !== '' && Str::startsWith($value, '****')) {
                $s3[$field] = (string) ($currentS3[$field] ?? '');
            }
        }

        $storage['s3'] = $s3;

        return $storage;
    }

    private function mergeLegacyCosIntoS3(array $s3, array $cos): array
    {
        $region = trim((string) ($cos['region'] ?? 'ap-guangzhou'));

        $merged = $s3;
        $merged['provider'] = trim((string) ($s3['provider'] ?? '')) !== '' ? (string) $s3['provider'] : 'cos';
        $merged['key'] = trim((string) ($s3['key'] ?? '')) !== '' ? (string) $s3['key'] : (string) ($cos['secret_id'] ?? '');
        $merged['secret'] = trim((string) ($s3['secret'] ?? '')) !== '' ? (string) $s3['secret'] : (string) ($cos['secret_key'] ?? '');
        $merged['region'] = trim((string) ($s3['region'] ?? '')) !== '' ? (string) $s3['region'] : $region;
        $merged['bucket'] = trim((string) ($s3['bucket'] ?? '')) !== '' ? (string) $s3['bucket'] : (string) ($cos['bucket'] ?? '');
        $merged['endpoint'] = trim((string) ($s3['endpoint'] ?? '')) !== ''
            ? (string) $s3['endpoint']
            : ($region !== '' ? 'https://cos.'.$region.'.myqcloud.com' : '');
        $merged['url'] = trim((string) ($s3['url'] ?? '')) !== '' ? (string) $s3['url'] : (string) ($cos['cdn_url'] ?? '');
        $merged['use_path_style_endpoint'] = array_key_exists('use_path_style_endpoint', $s3)
            ? filter_var($s3['use_path_style_endpoint'], FILTER_VALIDATE_BOOL)
            : false;

        return $merged;
    }

    private function resolveProviderApiKey(string $provider, array $cfg, string $emptyMessage)
    {
        $key = (string) ($cfg['api_key'] ?? '');
        if ($key !== '' && ! Str::startsWith($key, '****')) {
            return $key;
        }

        $savedRaw = $this->getConfigRaw();
        $savedKey = (string) Arr::get($savedRaw, 'ai_providers.'.$provider.'.api_key', '');
        if ($savedKey === '') {
            return ['ok' => false, 'message' => $emptyMessage];
        }

        return $savedKey;
    }

    private function testOpenAiCompatibleProvider(string $baseUrl, string $key, string $model, string $successMessage): array
    {
        $url = $this->buildChatCompletionsUrl($baseUrl);
        $payload = json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => 'ping']],
        ], JSON_UNESCAPED_UNICODE);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$key}\r\nAccept: application/json\r\nContent-Type: application/json\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
                'content' => $payload,
            ],
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);
        $statusLine = is_array($http_response_header ?? null) ? ($http_response_header[0] ?? '') : '';
        if (strpos($statusLine, '200') !== false) {
            return ['ok' => true, 'message' => $successMessage];
        }

        $msg = '';
        try {
            $arr = json_decode((string) $res, true);
            if (is_array($arr)) {
                $msg = (string) ($arr['error']['message'] ?? $arr['message'] ?? '');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return ['ok' => false, 'message' => ($msg !== '' ? $msg : ($statusLine ?: '请求失败'))];
    }

    private function buildChatCompletionsUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }
        if (preg_match('#/chat/completions$#', $baseUrl)) {
            return $baseUrl;
        }

        return $baseUrl.'/chat/completions';
    }

    private function ensureTable(): void
    {
        $tableName = (new SystemSetting)->getTable();
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, SystemSetting::getTableSchema());
        }
    }

    private function maskSecret(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return '****';
        }

        return '****'.substr($value, -4);
    }
}
