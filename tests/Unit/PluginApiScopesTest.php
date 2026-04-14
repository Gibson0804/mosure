<?php

namespace Tests\Unit;

use App\Services\PluginService;
use PHPUnit\Framework\Attributes\Test;
use Plugins\PluginInterface;
use ReflectionMethod;
use Tests\TestCase;

class PluginApiScopesTest extends TestCase
{
    #[Test]
    public function it_uses_safe_readonly_scopes_when_plugin_does_not_declare_api_scopes(): void
    {
        $service = app(PluginService::class);
        $plugin = $this->makePlugin([]);

        $scopes = $this->resolvePluginApiScopes($service, $plugin);

        $this->assertSame([
            'content.read',
            'page.read',
            'media.read',
        ], $scopes);
    }

    #[Test]
    public function it_uses_declared_plugin_api_scopes_and_filters_invalid_values(): void
    {
        $service = app(PluginService::class);
        $plugin = $this->makePlugin([
            'api_scopes' => ['content.read', 'page.write', 'invalid.scope', 'page.write'],
        ]);

        $scopes = $this->resolvePluginApiScopes($service, $plugin);

        $this->assertSame([
            'content.read',
            'page.write',
        ], $scopes);
    }

    private function resolvePluginApiScopes(PluginService $service, PluginInterface $plugin): array
    {
        $method = new ReflectionMethod($service, 'resolvePluginApiScopes');
        $method->setAccessible(true);

        return $method->invoke($service, $plugin);
    }

    private function makePlugin(array $config): PluginInterface
    {
        return new class($config) implements PluginInterface
        {
            public function __construct(private array $config) {}

            public function getId(): string
            {
                return 'test_plugin_v1';
            }

            public function getName(): string
            {
                return 'Test Plugin';
            }

            public function getVersion(): string
            {
                return 'v1';
            }

            public function getConfig(): array
            {
                return $this->config;
            }

            public function install(string $projectPrefix): bool
            {
                return true;
            }

            public function uninstall(string $projectPrefix): bool
            {
                return true;
            }

            public function enable(string $projectPrefix): bool
            {
                return true;
            }

            public function disable(string $projectPrefix): bool
            {
                return true;
            }

            public function upgrade(string $fromVersion): bool
            {
                return true;
            }

            public function registerRoutes(): void {}

            public function registerListeners(): void {}

            public function onBeforeInstall(string $projectPrefix): void {}

            public function onAfterInstall(string $projectPrefix): void {}

            public function onBeforeUninstall(string $projectPrefix): void {}

            public function onAfterUninstall(string $projectPrefix): void {}
        };
    }
}
