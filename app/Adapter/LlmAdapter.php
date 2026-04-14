<?php

namespace App\Adapter;

use App\Services\SystemConfigService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use OpenAI;

class LlmAdapter
{
    protected string $providerName = 'unknown';

    protected string $completionUrl = '';

    protected string $model = '';

    protected string $responseFormat = 'json';

    protected string $apiKey = '';

    protected int $timeoutSec = 300;

    protected int $maxTokens = 8192;

    protected $client = null;

    public function __construct(string $providerKey, $model = null, $responseFormat = 'json')
    {
        $this->providerName = $providerKey;
        $this->responseFormat = $responseFormat ?: 'json';

        $this->loadConfig($providerKey);
        $this->initClient();

        if ($model) {
            $this->model = (string) $model;
        }
    }

    public static function getModels(string $providerKey): array
    {
        try {
            $cfg = app(SystemConfigService::class)->getConfigRaw();
            $prov = Arr::get($cfg, 'ai_providers.'.$providerKey, []);

            $url = (string) ($prov['completion_url'] ?? '');
            $key = (string) ($prov['api_key'] ?? '');

            // echo rtrim($url, '/') . PHP_EOL;exit;

            $client = OpenAI::factory()
                ->withApiKey($key)
                ->withBaseUri(rtrim($url, '/'))
                ->make();

            $response = $client->models()->list();
            $models = array_map(fn ($m) => $m->id, $response->data);

            $currentModel = (string) ($prov['model'] ?? '');
            if ($currentModel !== '' && ! in_array($currentModel, $models, true)) {
                $models[] = $currentModel;
            }

            return $models;
        } catch (\Throwable $e) {
            Log::warning($providerKey.'_list_models_failed', ['err' => $e->getMessage()]);

            $cfg = app(SystemConfigService::class)->getConfigRaw();
            $prov = Arr::get($cfg, 'ai_providers.'.$providerKey, []);

            return (array) ($prov['models'] ?? []);
        }
    }

    protected function loadConfig(string $providerKey): void
    {
        try {
            $cfg = app(SystemConfigService::class)->getConfigRaw();
            $prov = Arr::get($cfg, 'ai_providers.'.$providerKey, []);
            $url = (string) ($prov['completion_url'] ?? '');
            $key = (string) ($prov['api_key'] ?? '');
            $m = (string) ($prov['model'] ?? '');
            $timeoutSec = (int) ($prov['timeout_sec'] ?? 300);
            $maxTokens = (int) ($prov['max_tokens'] ?? 8192);

            if ($url !== '') {
                $this->completionUrl = rtrim($url, '/');
            }
            if ($m !== '') {
                $this->model = $m;
            }
            if ($timeoutSec > 0) {
                $this->timeoutSec = $timeoutSec;
            }
            if ($maxTokens > 0) {
                $this->maxTokens = $maxTokens;
            }

            $this->apiKey = $key;
        } catch (\Throwable $e) {
            Log::warning($this->providerName.'_init_config_failed', ['err' => $e->getMessage()]);
        }
    }

    protected function initClient(): void
    {
        Log::info($this->providerName.'_init', [
            'provider' => $this->providerName,
            'baseUrl' => $this->completionUrl,
            'model' => $this->model,
        ]);

        $this->client = OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withBaseUri($this->completionUrl)
            ->withHttpClient(new \GuzzleHttp\Client([
                'timeout' => $this->timeoutSec,
                'connect_timeout' => 30,
            ]))
            ->make();
    }

    public function chat(array $messages, array $tools = [])
    {
        try {
            $params = [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ];

            if (! empty($tools)) {
                $params['tools'] = $tools;
                $params['tool_choice'] = 'auto';
            }

            Log::info($this->providerName.'_api_request', [
                'url' => $this->completionUrl,
                'model' => $this->model,
                'has_tools' => ! empty($tools),
                'tools_count' => count($tools),
                'api_key_preview' => $this->apiKey ? substr($this->apiKey, 0, 8).'...' : 'EMPTY',
            ]);

            $response = $this->client->chat()->create($params);

            Log::info($this->providerName.'_chat_success', [
                'has_content' => ! empty($response->choices[0]->message->content),
                'has_tool_calls' => ! empty($response->choices[0]->message->toolCalls),
            ]);

            return $response;

        } catch (\Throwable $e) {
            Log::error($this->providerName.'_chat_error', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
            throw $e;
        }
    }
}
