<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Contracts\ModuleLoaderInterface;
use NgarakDev\Modularization\Events\ModuleBroken;
use NgarakDev\Modularization\Events\ModuleRegistered;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;

/**
 * Registers a discovered module with Laravel (providers, routes, views, ...).
 */
final class ModuleRegistrar implements ModuleLoaderInterface
{
    public function __construct(
        private readonly Application $app,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
        private readonly DependencyResolver $dependencies,
        private readonly RouteCollisionDetector $collisions,
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

                if ($this->configuration->warnOnMissingDependencies() && function_exists('logger')) {
                    Log::warning("Module [{$module->name}] skipped because of missing dependencies: ".implode(', ', $missing));
                }

                continue;
            }

            try {
                $this->register($module);
                event(new ModuleRegistered($module));
            } catch (\Throwable $exception) {
                event(new ModuleBroken($module, $exception->getMessage()));

                throw $exception;
            }
        }

        if ($this->configuration->failOnRouteCollision()) {
            $this->collisions->assertNone($ordered);
        }
    }

    public function load(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->register($module);
        }
    }

    public function loadServiceProvider(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->registerProvider($module);
        }
    }

    public function loadRoutes(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->registerRoutes($module);
        }
    }

    public function loadViews(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->registerViews($module);
        }
    }

    public function loadTranslations(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->registerTranslations($module);
        }
    }

    public function loadMigrations(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->registerMigrations($module);
        }
    }

    public function loadConfig(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->registerConfig($module);
        }
    }

    public function loadLivewireComponents(ModuleInterface $module): void
    {
        if ($module instanceof Module) {
            $this->registerLivewire($module);
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

        $this->registerViteInputs($module);
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

    private function registerViteInputs(Module $module): void
    {
        if (! $this->configuration->viteEnabled() || $module->assets === null) {
            return;
        }

        $js = $this->paths->join($module->path, $module->assets.'/js/app.js');
        $css = $this->paths->join($module->path, $module->assets.'/css/app.css');
        $inputs = $this->app['config']->get('modularization.vite.inputs', []);

        if (is_file($js)) {
            $inputs[] = $js;
        }

        if (is_file($css)) {
            $inputs[] = $css;
        }

        $this->app['config']->set('modularization.vite.inputs', array_values(array_unique($inputs)));
    }
}
