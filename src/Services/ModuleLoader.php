<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Contracts\ModuleLoaderInterface;
use ReflectionClass;

/**
 * Loads module components into Laravel.
 */
final class ModuleLoader implements ModuleLoaderInterface
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {
    }

    public function load(ModuleInterface $module): void
    {
        $this->loadServiceProvider($module);
        $this->loadRoutes($module);
        $this->loadViews($module);
        $this->loadTranslations($module);
        $this->loadMigrations($module);
        $this->loadConfig($module);
        $this->loadLivewireComponents($module);
    }

    public function loadServiceProvider(ModuleInterface $module): void
    {
        $providerClass = $module->getProviderClass();

        if ($providerClass !== null && class_exists($providerClass)) {
            $this->app->register($providerClass);
        }
    }

    public function loadRoutes(ModuleInterface $module): void
    {
        $routesPath = $module->path('Routes');
        $moduleNameLower = strtolower($module->getName());
        $namespace = $module->getNamespace();

        if (!$this->files->isDirectory($routesPath)) {
            return;
        }

        $webRoutesPath = $routesPath . '/web.php';
        if ($this->files->exists($webRoutesPath)) {
            Route::middleware('web')
                ->namespace($namespace . '\\Http\\Controllers')
                ->group($webRoutesPath);
        }

        $apiRoutesPath = $routesPath . '/api.php';
        if ($this->files->exists($apiRoutesPath)) {
            Route::prefix('api')
                ->middleware('api')
                ->name('api.')
                ->namespace($namespace . '\\Http\\Controllers')
                ->group($apiRoutesPath);
        }

        $livewireRoutesPath = $routesPath . '/livewire.php';
        if ($this->files->exists($livewireRoutesPath)) {
            Route::middleware('web')
                ->group($livewireRoutesPath);
        }

        $authRoutesPath = $routesPath . '/auth.php';
        if ($this->files->exists($authRoutesPath)) {
            Route::middleware('web')
                ->group($authRoutesPath);
        }
    }

    public function loadViews(ModuleInterface $module): void
    {
        $viewsPath = $module->path('Resources/views');
        $moduleNameLower = strtolower($module->getName());

        if ($this->files->isDirectory($viewsPath)) {
            $this->app['view']->addNamespace($moduleNameLower, $viewsPath);
        }
    }

    public function loadTranslations(ModuleInterface $module): void
    {
        $translationsPath = $module->path('Resources/lang');
        $moduleNameLower = strtolower($module->getName());

        if ($this->files->isDirectory($translationsPath)) {
            $this->app['translator']->addNamespace($moduleNameLower, $translationsPath);
        }
    }

    public function loadMigrations(ModuleInterface $module): void
    {
        $migrationsPath = $module->path('Database/Migrations');

        if ($this->files->isDirectory($migrationsPath)) {
            $this->app->afterResolving('migrator', function ($migrator) use ($migrationsPath) {
                $migrator->path($migrationsPath);
            });
        }
    }

    public function loadConfig(ModuleInterface $module): void
    {
        $configPath = $module->path('Config');
        $moduleName = $module->getName();

        if (!$this->files->isDirectory($configPath)) {
            return;
        }

        $configFiles = $this->files->files($configPath);

        foreach ($configFiles as $configFile) {
            if (pathinfo($configFile, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $configName = pathinfo($configFile, PATHINFO_FILENAME);
            $key = "modules.{$moduleName}.{$configName}";

            $existingConfig = $this->app['config']->get($key, []);
            $moduleConfig = require $configFile;

            if (is_array($moduleConfig)) {
                $this->app['config']->set($key, array_merge($moduleConfig, $existingConfig));
            }
        }
    }

    public function loadLivewireComponents(ModuleInterface $module): void
    {
        if (!class_exists(Livewire::class)) {
            return;
        }

        $livewirePath = $module->path('Livewire');

        if (!$this->files->isDirectory($livewirePath)) {
            return;
        }

        $namespace = $module->getNamespace();
        $fullNamespace = $namespace . '\\Livewire';
        $prefix = strtolower($module->getName());

        $this->registerLivewireComponentsInDirectory($livewirePath, $fullNamespace, $prefix);

        $directories = $this->files->directories($livewirePath);
        foreach ($directories as $directory) {
            $directoryName = basename($directory);
            $subNamespace = $fullNamespace . '\\' . $directoryName;
            $subPrefix = $prefix . '.' . $this->kebabCase($directoryName);

            $this->registerLivewireComponentsInDirectory($directory, $subNamespace, $subPrefix);
        }
    }

    /**
     * Register Livewire components in a directory.
     */
    private function registerLivewireComponentsInDirectory(
        string $directory,
        string $namespace,
        string $prefix
    ): void {
        $files = $this->files->glob($directory . '/*.php');

        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $componentClass = $namespace . '\\' . $fileName;

            if (!class_exists($componentClass)) {
                continue;
            }

            $reflection = new ReflectionClass($componentClass);
            if (!$reflection->isSubclassOf(\Livewire\Component::class)) {
                continue;
            }

            $alias = $prefix . '.' . $this->kebabCase($fileName);
            Livewire::component($alias, $componentClass);
        }
    }

    /**
     * Convert a string to kebab-case.
     */
    private function kebabCase(string $string): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }
}
