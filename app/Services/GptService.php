<?php

namespace App\Services;

use App\Adapter\LlmAdapter;
use App\Ai\AiToolRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use OpenAI\Responses\Chat\CreateResponse;

class GptService extends BaseService
{
    public $historyService;

    private string $responseFormat = 'json';

    public function __construct(AiHistoryService $historyService)
    {
        $this->historyService = $historyService;
    }

    private function resolveActiveProviderAndModel(): array
    {
        $sys = [];
        try {
            $sys = app(\App\Services\SystemConfigService::class)->getConfigRaw();
        } catch (\Throwable $e) {
            // ignore
        }

        $active = (string) Arr::get($sys, 'ai_providers.active_provider', '');
        if ($active === '') {
            throw new \InvalidArgumentException('未配置AI提供商');
        }

        $model = (string) Arr::get($sys, 'ai_providers.'.$active.'.model', '');
        if ($model === '') {
            throw new \InvalidArgumentException('未配置AI模型，请在系统设置中设置模型');
        }

        return [$active, $model];
    }

    public function getGptService($model, string $responseFormat = 'json')
    {
        $this->responseFormat = $responseFormat;
        [$active, $resolvedModel] = $this->resolveActiveProviderAndModel();

        return new LlmAdapter($active, $resolvedModel, $responseFormat);
    }

    public function listModels()
    {
        try {
            $sys = app(SystemConfigService::class)->getConfigRaw();
            $active = (string) Arr::get($sys, 'ai_providers.active_provider', 'zhipu');

            return LlmAdapter::getModels($active);
        } catch (\Throwable $e) {
            return LlmAdapter::getModels('zhipu');
        }
    }

    public function chat($model, array $messages, $userId = null, string $question = '', bool $noCache = false, string $responseFormat = 'json', bool $throwOnError = false)
    {
        [$activeProvider, $resolvedModel] = $this->resolveActiveProviderAndModel();

        $messagesJson = json_encode($messages, JSON_UNESCAPED_UNICODE);
        Log::info('GptService_chat_messages: '.$messagesJson);
        if (! is_string($messagesJson)) {
            $messagesJson = '[]';
        }

        try {
            $response = $this->getGptService($resolvedModel, $responseFormat)->chat($messages);
            $result = $this->responseFormat($response);
            // todo::改成异步
            $this->historyService->save($resolvedModel, $userId, (string) $question, json_encode($messages, JSON_UNESCAPED_UNICODE), $response->toArray());

            Log::info('GptService_chat_result: '.json_encode($result, JSON_UNESCAPED_UNICODE));

            return $result;
        } catch (\Throwable $e) {
            Log::error('GptService_chat_error '.$e->getMessage(), [
                'provider' => (string) $activeProvider,
                'model' => (string) $resolvedModel,
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            if ($throwOnError) {
                throw $e;
            }

            return null;
        }
    }

    public function chatWithTools(
        array $messages,
        array $tools = [],
        ?int $userId = null,
        string $question = '',
        int $maxIterations = 5
    ): array {
        [$activeProvider, $resolvedModel] = $this->resolveActiveProviderAndModel();

        $formattedTools = AiToolRegistry::formatToolsToChatTool($tools);

        Log::info('GptService_chatWithTools_start', [
            'provider' => $activeProvider,
            'model' => $resolvedModel,
            'tools_count' => count($tools),
            'formatted_tools' => $formattedTools,
            'messages_count' => count($messages),
            'first_message' => $messages[0] ?? null,
        ]);

        try {
            $adapter = $this->getGptService($resolvedModel, 'text');
            $iteration = 0;
            $content = '';
            $executedToolCallsInLastIteration = false;

            while ($iteration < $maxIterations) {
                $iteration++;
                $executedToolCallsInLastIteration = false;

                Log::info('GptService_chatWithTools_iteration', [
                    'iteration' => $iteration,
                    'messages_count' => count($messages),
                ]);

                $response = $adapter->chat($messages, $formattedTools);
                Log::info('GptService_chatWithTools_response'.json_encode($response->toArray(), JSON_UNESCAPED_UNICODE));

                $result = $this->responseFormat($response);

                // todo::改成异步
                $this->historyService->save($resolvedModel, $userId, (string) $question, json_encode($messages, JSON_UNESCAPED_UNICODE), $response->toArray());

                $toolCalls = $result['tool_calls'] ?? [];
                $content = $result['content'] ?? $result['text'] ?? '';

                if (empty($toolCalls)) {
                    Log::info('GptService_chatWithTools_end', [
                        'iterations' => $iteration,
                        'content_len' => strlen($content ?? ''),
                    ]);

                    return [
                        'content' => $content ?? '',
                        'messages' => $messages,
                        'iterations' => $iteration,
                    ];
                }

                Log::info('GptService_chatWithTools_tool_calls', [
                    'count' => count($toolCalls),
                ]);

                foreach ($toolCalls as $tc) {
                    $toolName = $tc->function->name ?? '';
                    $arguments = $tc->function->arguments ?? '{}';
                    $toolCallId = $tc->id ?? '';

                    if (! $toolName) {
                        continue;
                    }

                    $messages[] = [
                        'role' => 'assistant',
                        'content' => '',
                        'tool_calls' => [
                            [
                                'id' => $toolCallId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => $arguments,
                                ],
                            ],
                        ],
                    ];

                    try {
                        $params = json_decode($arguments, true) ?: [];
                        $toolResult = AiToolRegistry::invoke($toolName, $params);
                        $resultJson = is_array($toolResult)
                            ? json_encode($toolResult, JSON_UNESCAPED_UNICODE)
                            : (string) $toolResult;

                        Log::info('GptService_chatWithTools_tool_result', [
                            'tool' => $toolName,
                            'result_preview' => mb_substr($resultJson, 0, 200),
                        ]);

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content' => $resultJson,
                        ];
                        $executedToolCallsInLastIteration = true;
                    } catch (\Throwable $e) {
                        Log::error('GptService_chatWithTools_tool_error', [
                            'tool' => $toolName,
                            'error' => $e->getMessage(),
                        ]);

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                        ];
                        $executedToolCallsInLastIteration = true;
                    }
                }
            }

            // If the loop ended right after tool execution, give the model one final
            // chance to summarize the tool results for the user instead of returning
            // an empty string.
            if ($executedToolCallsInLastIteration) {
                Log::info('GptService_chatWithTools_final_answer_attempt', [
                    'messages_count' => count($messages),
                    'iterations' => $iteration,
                ]);

                $response = $adapter->chat($messages, $formattedTools);
                Log::info('GptService_chatWithTools_final_answer_response'.json_encode($response->toArray(), JSON_UNESCAPED_UNICODE));

                $result = $this->responseFormat($response);
                $this->historyService->save($resolvedModel, $userId, (string) $question, json_encode($messages, JSON_UNESCAPED_UNICODE), $response->toArray());
                $content = $result['content'] ?? $result['text'] ?? $content;
            }

            return [
                'content' => $content ?? '',
                'messages' => $messages,
                'iterations' => $iteration,
            ];

        } catch (\Throwable $e) {
            Log::error('GptService_chatWithTools_error', [
                'provider' => $activeProvider,
                'model' => $resolvedModel,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function responseFormat(CreateResponse $response)
    {

        $content = $response->choices[0]->message->content ?? '';
        $toolCalls = $response->choices[0]->message->toolCalls ?? [];

        Log::info('DeepSeek_chat_end', [
            'content_len' => strlen($content ?? ''),
            'has_tool_calls' => ! empty($toolCalls),
        ]);

        if (! empty($toolCalls)) {
            return [
                'text' => $content ?? '',
                'tool_calls' => $toolCalls,
            ];
        }

        // todo::确定没用到的地方就删除；所有相关调用的地方都筛查下，json应该统一处理
        if ($this->responseFormat === 'text') {
            return ['text' => $content ?? ''];
        }

        $direct = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($direct)) {
            return $direct;
        }

        if (preg_match('/```json\s*(.*?)\s*```/s', $content ?? '', $matches) && isset($matches[1])) {
            $jsonArr = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonArr)) {
                return $jsonArr;
            }
        }

        return ['text' => $content ?? ''];
    }
}
