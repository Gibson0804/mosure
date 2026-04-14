<?php

namespace App\Mcp\Prompts;

use App\Adapter\OpenPrompts;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Laravel\Mcp\Server\Prompts\Arguments;
use Laravel\Mcp\Server\Prompts\PromptResult;

class ModelGeneratePrompt extends Prompt
{
    public function name(): string
    {
        return 'model-generate';
    }

    public function description(): string
    {
        return '生成Mosure模型的JSON结构描述，用于创建新的内容模型';
    }

    public function arguments(): Arguments
    {
        return (new Arguments)
            ->add(new Argument(
                name: 'model_name',
                description: '模型名称（中文名）',
                required: true,
            ))
            ->add(new Argument(
                name: 'description',
                description: '模型描述（可选）',
                required: false,
            ));
    }

    public function handle(array $arguments): PromptResult
    {
        $modelName = $arguments['model_name'] ?? '';
        $description = $arguments['description'] ?? '';

        $prompt = OpenPrompts::getModelGeneratePrompt($modelName, $description);

        return new PromptResult($prompt, '模型生成提示词');
    }
}
