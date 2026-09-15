<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;

/**
 * Registers a discovered module with Laravel (providers, routes, views, ...).
 */
final class ModuleRegistrar
{
    public function __construct(
        private readonly Application $app,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
        private readonly DependencyResolver $dependencies,
    ) {}

    /**
     * @param  array<string, Module>  $modules
     */
    public function registerAll(array $modules): void
    {
        $enabled = array_filter(
            $modules,
            function (Module $module): bool {
                if (! $module->valid) {
                    return false;
                }

                return $module->enabled || ! $this->configuration->skipDisabledOnBoot();
            }
        );

        $ordered = $this->dependencies->sort(
            $enabled,
            $this->configuration->failOnMissingDependencies()
        );

        foreach ($ordered as $module) {
            $missing = $this->dependencies->missingDependencies($module, $modules);

            if ($missing !== []) {
                if ($this->configuration->failOnMissingDependencies()) {
                    throw ModuleDependencyException::missing($module->name, $missing);
                }

                continue;
            }

            $this->register($module);
        }
    }

    public function register(Module $module): void
    {
        $this->registerProvider($module);
        $this->registerConfig($module);

        if ($this->configuration->autoRegisterRoutes()) {
            $this->registerRoutes($module);
        }

        $this->registerViews($module);
        $this->registerTranslations($module);
        $this->registerMigrations($module);

        if ($this->configuration->autoRegisterLivewire()) {
            $this->registerLivewire($module);
        }
    }

    private function registerProvider(Module $module): void
    {
        if ($module->provider === null || ! class_exists($module->provider)) {
            return;
        }

        if ($this->app->getProvider($module->provider) !== null) {
            return;
        }

        $this->app->register($module->provider);
    }

    private function registerRoutes(Module $module): void
    {
        $namespace = $module->namespace.'\\Http\\Controllers';

        if (! empty($module->routes['web'])) {
            Route::middleware('web')
                ->namespace($namespace)
                ->group($this->paths->join($module->path, $module->routes['web']));
        }

        if (! empty($module->routes['api'])) {
            Route::prefix('api')
                ->middleware('api')
                ->name('api.')
                ->namespace($namespace)
                ->group($this->paths->join($module->path, $module->routes['api']));
        }

        if (! empty($module->routes['livewire'])) {
            Route::middleware('web')
                ->group($this->paths->join($module->path, $module->routes['livewire']));
        }
    }

    private function registerViews(Module $module): void
    {
        if ($module->views === null) {
            return;
        }

        $viewsPath = $this->paths->join($module->path, $module->views);
        $this->app['view']->addNamespace($module->viewNamespace(), $viewsPath);
    }

    private function registerTranslations(Module $module): void
    {
        if ($module->translations === null) {
            return;
        }

        $path = $this->paths->join($module->path, $module->translations);
        $this->app['translator']->addNamespace($module->viewNamespace(), $path);
    }

    private function registerMigrations(Module $module): void
    {
        if ($module->migrations === null) {
            return;
        }

        $this->app->afterResolving('migrator', function ($migrator) use ($module): void {
            $migrator->path($this->paths->join($module->path, $module->migrations));
        });

        if ($this->app->resolved('migrator')) {
            $this->app['migrator']->path($this->paths->join($module->path, $module->migrations));
        }
    }

    private function registerConfig(Module $module): void
    {
        foreach ($module->configFiles as $relative) {
            $full = $this->paths->join($module->path, $relative);
            $name = pathinfo($relative, PATHINFO_FILENAME);
            $this->app['config']->set(
                "modules.{$module->name}.{$name}",
                array_merge(
                    $this->app['config']->get("modules.{$module->name}.{$name}", []),
                    require $full
                )
            );
        }
    }

    private function registerLivewire(Module $module): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        foreach ($module->livewire as $component) {
            if (! class_exists($component['class'])) {
                continue;
            }

            Livewire::component($component['alias'], $component['class']);
        }
    }
}
