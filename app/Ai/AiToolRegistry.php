<?php

namespace App\Ai;

use App\Ai\Attributes\AiTool;
use App\Repository\MoldRepository;

class AiToolRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $path = self::getJsonPath();
        if (is_file($path)) {
            $content = file_get_contents($path);
            $decoded = json_decode($content ?: '[]', true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $registry = self::buildRegistry();
        self::writeRegistry($registry);

        return $registry;
    }

    public static function refresh(): array
    {
        $registry = self::buildRegistry();
        self::writeRegistry($registry);

        return $registry;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return mixed
     */
    public static function invoke(string $name, array $params = [])
    {
        $all = self::all();
        if (! isset($all[$name])) {
            throw new \InvalidArgumentException('Unknown AI tool: '.$name);
        }

        self::validateToolAgainstMoldType($name, $params);

        $tool = $all[$name];
        $serviceClass = $tool['service'];
        $method = $tool['method'];

        $service = app($serviceClass);

        $reflection = new \ReflectionMethod($serviceClass, $method);
        $args = [];
        foreach ($reflection->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            if (array_key_exists($paramName, $params)) {
                $args[] = $params[$paramName];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } else {
                throw new \InvalidArgumentException('Missing required param: '.$paramName.' for tool '.$name);
            }
        }

        return $reflection->invokeArgs($service, $args);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function validateToolAgainstMoldType(string $toolName, array $params): void
    {
        $moldType = self::resolveMoldTypeForTool($toolName, $params);
        if ($moldType === null) {
            return;
        }

        if ($moldType === 'single' && in_array($toolName, ['content_create', 'content_update'], true)) {
            throw new \InvalidArgumentException('当前模型为 single 类型，请使用 subject_detail 或 subject_update，不可使用 '.$toolName);
        }

        if ($moldType === 'list' && in_array($toolName, ['subject_detail', 'subject_update'], true)) {
            throw new \InvalidArgumentException('当前模型为 list 类型，请使用 content_list/content_detail/content_create/content_update，不可使用 '.$toolName);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function resolveMoldTypeForTool(string $toolName, array $params): ?string
    {
        if (isset($params['moldId']) && is_numeric($params['moldId'])) {
            $moldInfo = app(MoldRepository::class)->getMoldInfo((int) $params['moldId']);

            return is_array($moldInfo) ? (string) ($moldInfo['mold_type'] ?? '') : (string) ($moldInfo->mold_type ?? '');
        }

        if (isset($params['tableName']) && is_string($params['tableName'])) {
            $moldInfo = app(MoldRepository::class)->getMoldInfoByTableName($params['tableName']);
            if ($moldInfo === null) {
                return null;
            }

            return is_array($moldInfo) ? (string) ($moldInfo['mold_type'] ?? '') : (string) ($moldInfo->mold_type ?? '');
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function buildRegistry(): array
    {
        $tools = [];

        $servicesPath = base_path('app/Services');
        if (! is_dir($servicesPath)) {
            return $tools;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($servicesPath)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($servicesPath) + 1);
            $class = 'App\\Services\\'.str_replace(['/', '\\'], '\\', substr($relativePath, 0, -4));

            if (! class_exists($class)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($class);
            foreach ($reflectionClass->getMethods() as $method) {
                foreach ($method->getAttributes(AiTool::class) as $attribute) {
                    /** @var AiTool $instance */
                    $instance = $attribute->newInstance();
                    $name = $instance->name;

                    if (isset($tools[$name])) {
                        continue;
                    }

                    $tools[$name] = [
                        'name' => $name,
                        'description' => $instance->description,
                        'params' => $instance->params,
                        'service' => $class,
                        'method' => $method->getName(),
                    ];
                }
            }
        }

        return $tools;
    }

    /**
     * @param  array<string, array<string, mixed>>  $registry
     */
    private static function writeRegistry(array $registry): void
    {
        $path = self::getJsonPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function getJsonPath(): string
    {
        return base_path('app/Ai/ai_tools.json');
    }

    public static function formatToolsToChatTool(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $name => $tool) {
            $func = [
                'name' => $name,
                'description' => $tool['description'] ?? '',
            ];

            if (! empty($tool['params'])) {
                $properties = new \stdClass;
                $required = [];
                foreach ($tool['params'] as $pName => $param) {
                    $paramType = $param['type'] ?? 'string';

                    if (strpos($paramType, '|') !== false) {
                        $types = explode('|', $paramType);
                        $anyOf = [];
                        foreach ($types as $t) {
                            $t = trim($t);
                            if (in_array($t, ['string', 'integer', 'number', 'boolean', 'array', 'object'])) {
                                $anyOf[] = (object) ['type' => $t];
                            }
                        }
                        if (count($anyOf) === 1) {
                            $paramSchema = $anyOf[0];
                        } else {
                            $paramSchema = (object) [
                                'anyOf' => $anyOf,
                            ];
                        }
                    } else {
                        $paramSchema = (object) [
                            'type' => $paramType,
                        ];
                    }

                    $paramSchema->description = $param['desc'] ?? $param['description'] ?? '';
                    $properties->{$pName} = $paramSchema;

                    if (($param['required'] ?? false)) {
                        $required[] = $pName;
                    }
                }
                $func['parameters'] = [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ];
            } else {
                $func['parameters'] = [
                    'type' => 'object',
                    'properties' => new \stdClass,
                    'required' => [],
                ];
            }

            $formatted[] = [
                'type' => 'function',
                'function' => $func,
            ];
        }

        return $formatted;
    }
}
