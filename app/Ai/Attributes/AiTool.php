<?php

namespace App\Ai\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class AiTool
{
    public string $name;

    public string $description;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $params;

    public function __construct(string $name, string $description = '', array $params = [])
    {
        $this->name = $name;
        $this->description = $description;
        $this->params = $params;
    }
}
