<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;

/**
 * Strongly structured access to the package configuration.
 */
final class ModuleConfiguration
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function modulesPathRelative(): string
    {
        $path = (string) $this->config->get('modularization.modules_path', 'modules');
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_contains($path, '..')) {
            throw new InvalidArgumentException(
                'Configuration [modularization.modules_path] must be a non-empty path relative to the application base path, without `..` segments.'
            );
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return trim($path, '/');
    }

    public function modulesPath(): string
    {
        $path = $this->modulesPathRelative();

        return $this->isAbsolutePath($path) ? $path : base_path($path);
    }

    public function namespace(): string
    {
        $namespace = trim((string) $this->config->get('modularization.namespace', 'Modules'), '\\');

        if ($namespace === '' || preg_match('/^[A-Z][A-Za-z0-9_\\\\]*$/', $namespace) !== 1) {
            throw new InvalidArgumentException(
                'Configuration [modularization.namespace] must be a valid PHP namespace such as Modules.'
            );
        }

        return $namespace;
    }

    /**
     * @return array<int, string>
     */
    public function directories(): array
    {
        $directories = $this->config->get('modularization.directories', []);

        if (! is_array($directories) || $directories === []) {
            return $this->defaultDirectories();
        }

        return array_values(array_filter(array_map('strval', $directories)));
    }

    public function autoRegisterRoutes(): bool
    {
        return (bool) $this->config->get(
            'modularization.auto_register_controllers',
            $this->config->get('modularization.auto_register_providers', true)
        );
    }

    public function autoRegisterLivewire(): bool
    {
        return (bool) $this->config->get('modularization.auto_register_livewire', true);
    }

    public function scaffoldRepositories(): bool
    {
        return (bool) $this->config->get('modularization.scaffold.repositories', $this->config->get('modularization.enforce_repository_pattern', true));
    }

    public function scaffoldServices(): bool
    {
        return (bool) $this->config->get('modularization.scaffold.services', true);
    }

    public function cacheEnabled(): bool
    {
        $value = $this->config->get('modularization.cache.enabled');

        if ($value === null) {
            return app()->environment('production');
        }

        return (bool) $value;
    }

    public function cachePath(): string
    {
        $relative = (string) $this->config->get('modularization.cache.path', 'bootstrap/cache/modules.php');

        if ($relative === '' || str_contains($relative, '..')) {
            throw new InvalidArgumentException(
                'Configuration [modularization.cache.path] must be a path relative to the application base path, without `..` segments.'
            );
        }

        return $this->isAbsolutePath($relative) ? $relative : base_path($relative);
    }

    public function failOnMissingDependencies(): bool
    {
        return (bool) $this->config->get('modularization.dependencies.fail_on_missing', false);
    }

    public function warnOnMissingDependencies(): bool
    {
        return (bool) $this->config->get('modularization.dependencies.warn_on_missing', true);
    }

    public function failOnRouteCollision(): bool
    {
        return (bool) $this->config->get('modularization.routes.fail_on_collision', false);
    }

    public function skipDisabledOnBoot(): bool
    {
        return (bool) $this->config->get('modularization.skip_disabled', true);
    }

    public function updateComposer(): bool
    {
        return (bool) $this->config->get('modularization.update_composer', true);
    }

    public function dumpAutoload(): bool
    {
        return (bool) $this->config->get('modularization.dump_autoload', true);
    }

    public function registryPath(): string
    {
        $relative = (string) $this->config->get('modularization.registry.path', 'bootstrap/cache/modules-registry.json');

        if ($relative === '' || str_contains($relative, '..')) {
            throw new InvalidArgumentException(
                'Configuration [modularization.registry.path] must be a path relative to the application base path, without `..` segments.'
            );
        }

        return $this->isAbsolutePath($relative) ? $relative : base_path($relative);
    }

    public function viteEnabled(): bool
    {
        return (bool) $this->config->get('modularization.assets.vite', true);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @return array<int, string>
     */
    public function defaultDirectories(): array
    {
        return [
            'Http/Controllers',
            'Http/Controllers/API',
            'Http/Middleware',
            'Http/Requests',
            'Models',
            'Repositories',
            'Repositories/Interfaces',
            'Services',
            'Services/Interfaces',
            'Providers',
            'Database/Migrations',
            'Database/Seeders',
            'Database/Factories',
            'Routes',
            'Config',
            'Resources/views',
            'Resources/lang',
            'Resources/assets/js',
            'Resources/assets/css',
            'Livewire',
            'Events',
            'Listeners',
            'Jobs',
            'Policies',
            'Console',
            'Tests/Unit',
            'Tests/Feature',
        ];
    }
}
