<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use App\Services\GptService;
use Illuminate\Support\Facades\Log;

/**
 * 云函数代码生成任务处理器
 */
class FunctionCodeSuggestTaskProcessor implements TaskProcessorInterface
{
    private $taskRepository;

    private $gptService;

    public function __construct(
        SysTaskRepository $taskRepository,
        GptService $gptService
    ) {
        $this->taskRepository = $taskRepository;
        $this->gptService = $gptService;
    }

    public function process(SysTask $task): void
    {
        $payload = $task->payload ?? [];
        $question = (string) ($payload['suggest'] ?? '');
        $functionType = (string) ($payload['function_type'] ?? 'endpoint');
        $models = (array) ($payload['models'] ?? []);

        if ($question === '') {
            $this->taskRepository->markFailed($task, '缺少必要参数：suggest');

            return;
        }

        $finalPrompt = Prompts::getFunctionCodePrompt($question, $functionType, $models);
        $userId = $task->requested_by ?? null;

        $result = $this->gptService->chat('', [
            ['role' => 'user', 'content' => $finalPrompt],
        ], $userId, $question);

        // 如果结果是数组（GPT 返回了 JSON），提取 code 字段；否则直接使用字符串
        $code = '';
        if (is_array($result)) {
            $code = $result['code'] ?? ($result['content'] ?? json_encode($result, JSON_UNESCAPED_UNICODE));
        } elseif (is_string($result)) {
            $code = $result;
        }

        // 清理可能包裹的 markdown 代码块
        $code = $this->stripCodeBlock($code);

        // 确保以 <?php 开头
        if (! str_starts_with(trim($code), '<?php')) {
            $code = "<?php\n\n" . ltrim($code, "<?php \t\n\r");
        }

        $this->taskRepository->markSuccess($task, ['code' => $code]);
    }

    /**
     * 去除 markdown 代码块标记
     */
    private function stripCodeBlock(string $text): string
    {
        $text = trim($text);

        // 匹配 ```php ... ``` 或 ``` ... ```
        if (preg_match('/^```(?:php)?\s*(.*?)\s*```$/s', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
    }
}
