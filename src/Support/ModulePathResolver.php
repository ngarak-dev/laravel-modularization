<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves paths for modules.
 */
final class ModulePathResolver
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    /**
     * Get the base path where modules are stored.
     */
    public function getBasePath(): string
    {
        $configPath = $this->app['config']->get('modularization.modules_path', 'modules');

        if (str_starts_with($configPath, '/')) {
            return $configPath;
        }

        return $this->app->basePath($configPath);
    }

    /**
     * Get the path to a specific module.
     */
    public function getModulePath(string $moduleName): string
    {
        return $this->getBasePath() . DIRECTORY_SEPARATOR . $moduleName;
    }

    /**
     * Get the path to a specific location within a module.
     */
    public function path(string $moduleName, string $subPath = ''): string
    {
        $modulePath = $this->getModulePath($moduleName);

        if ($subPath === '') {
            return $modulePath;
        }

        return $modulePath . DIRECTORY_SEPARATOR . ltrim($subPath, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the default namespace for modules.
     */
    public function getNamespace(): string
    {
        return $this->app['config']->get('modularization.namespace', 'Modules');
    }

    /**
     * Get the full namespace for a specific module.
     */
    public function getModuleNamespace(string $moduleName): string
    {
        return $this->getNamespace() . '\\' . $moduleName;
    }

    /**
     * Get the path to the module's routes directory.
     */
    public function getRoutesPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Routes');
    }

    /**
     * Get the path to the module's views directory.
     */
    public function getViewsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Resources/views');
    }

    /**
     * Get the path to the module's translations directory.
     */
    public function getTranslationsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Resources/lang');
    }

    /**
     * Get the path to the module's migrations directory.
     */
    public function getMigrationsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Database/Migrations');
    }

    /**
     * Get the path to the module's config directory.
     */
    public function getConfigPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Config');
    }

    /**
     * Get the path to the module's Livewire components directory.
     */
    public function getLivewirePath(string $moduleName): string
    {
        return $this->path($moduleName, 'Livewire');
    }

    /**
     * Get the path to the module's assets directory.
     */
    public function getAssetsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Resources/assets');
    }

    /**
     * Check if a module path exists.
     */
    public function exists(string $moduleName): bool
    {
        return is_dir($this->getModulePath($moduleName));
    }
}
