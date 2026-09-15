<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use NgarakDev\Modularization\Console\Commands\MakeMigrationCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleAuthCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleEventCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleLivewireCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleManagerCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleTranslationCommand;
use NgarakDev\Modularization\Console\Commands\MigrateModuleCommand;
use NgarakDev\Modularization\Console\Commands\MigrateModulesCommand;
use NgarakDev\Modularization\Console\Commands\ModuleCacheCommand;
use NgarakDev\Modularization\Console\Commands\ModuleClearCommand;
use NgarakDev\Modularization\Console\Commands\ModuleExportCommand;
use NgarakDev\Modularization\Console\Commands\ModuleListCommand;
use NgarakDev\Modularization\Console\Commands\ModuleToggleCommand;
use NgarakDev\Modularization\Console\Commands\PublishStubsCommand;
use NgarakDev\Modularization\Facades\Modularization;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\Support\ModuleCache;
use NgarakDev\Modularization\Support\ModuleDiscovery;
use NgarakDev\Modularization\Support\ModuleStatusManager;

class ModularizationServiceProvider extends ServiceProvider
{
    /**
     * Register package services and bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/modularization.php',
            'modularization',
        );

        $this->app->singleton(ModuleDiscovery::class, function ($app) {
            return new ModuleDiscovery(
                files: $app[Filesystem::class],
                modulesPath: base_path($app['config']->get('modularization.modules_path', 'modules')),
                defaultNamespace: $app['config']->get('modularization.namespace', 'Modules'),
            );
        });

        $this->app->singleton(ModuleStatusManager::class, function ($app) {
            return new ModuleStatusManager($app[Filesystem::class]);
        });

        $this->app->singleton(ModuleCache::class, function ($app) {
            return new ModuleCache(
                files: $app[Filesystem::class],
                bootstrapPath: $app->bootstrapPath(),
            );
        });

        $this->app->singleton(ModularizationService::class, function ($app) {
            return new ModularizationService(
                discovery: $app[ModuleDiscovery::class],
                statusManager: $app[ModuleStatusManager::class],
                cache: $app[ModuleCache::class],
            );
        });

        // Register the 'modularization' alias for backwards compatibility with Facade and direct binding
        $this->app->alias(ModularizationService::class, 'modularization');

        $this->registerCommands();
    }

    /**
     * Bootstrap the package.
     */
    public function boot(): void
    {
        $this->publishAssets();
        $this->loadModules();
    }

    /**
     * Register all Artisan commands.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeModuleCommand::class,
            MakeModuleLivewireCommand::class,
            MakeModuleEventCommand::class,
            MakeModuleTranslationCommand::class,
            ModuleExportCommand::class,
            ModuleToggleCommand::class,
            PublishStubsCommand::class,
            MigrateModuleCommand::class,
            MigrateModulesCommand::class,
            MakeMigrationCommand::class,
            MakeModuleAuthCommand::class,
            MakeModuleManagerCommand::class,
            ModuleListCommand::class,
            ModuleCacheCommand::class,
            ModuleClearCommand::class,
        ]);
    }

    /**
     * Publish package assets.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../../config/modularization.php' => config_path('modularization.php'),
        ], 'modularization-config');

        $this->publishes([
            __DIR__.'/../../stubs' => base_path('stubs/vendor/modularization'),
        ], 'modularization-stubs');
    }

    /**
     * Discover and boot all enabled modules.
     *
     * Note: Boot-time discovery uses ModuleDiscovery directly so that the
     * ModularizationService (which is lazily initialized on first access) does
     * not pre-warm with a stale module list. This matters in tests where
     * modules are created after the service provider has been booted.
     */
    protected function loadModules(): void
    {
        /** @var ModuleDiscovery $discovery */
        $discovery = $this->app->make(ModuleDiscovery::class);

        $modules = $discovery->discover();

        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $this->bootModule($module);
            }
        }
    }

    /**
     * Boot a single module: register its provider, routes, views, translations, migrations, etc.
     */
    protected function bootModule(Module $module): void
    {
        $this->registerModuleServiceProvider($module);
        $this->registerRoutes($module);
        $this->registerViews($module);
        $this->registerTranslations($module);
        $this->registerMigrations($module);
        $this->registerAssets($module);
        $this->registerLivewireComponents($module);
        $this->registerConfig($module);
    }

    /**
     * Register the module's service provider if it exists and the class is loadable.
     */
    protected function registerModuleServiceProvider(Module $module): void
    {
        $providerPath = $module->providerPath();

        if (! File::exists($providerPath)) {
            return;
        }

        $providerClass = $module->providerClass();

        if (class_exists($providerClass)) {
            $this->app->register($providerClass);
        }
    }

    /**
     * Register web, API, and Livewire route files for the module.
     */
    protected function registerRoutes(Module $module): void
    {
        $modulePath = $module->path;

        $webRoutes = $modulePath.'/Routes/web.php';
        if (File::exists($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }

        $apiRoutes = $modulePath.'/Routes/api.php';
        if (File::exists($apiRoutes)) {
            Route::prefix('api')
                ->middleware('api')
                ->name('api.')
                ->group($apiRoutes);
        }

        $livewireRoutes = $modulePath.'/Routes/livewire.php';
        if (File::exists($livewireRoutes)) {
            Route::middleware('web')->group($livewireRoutes);
        }
    }

    /**
     * Register and publish the module's views.
     */
    protected function registerViews(Module $module): void
    {
        $viewsPath = $module->path.'/Resources/views';

        if (! File::isDirectory($viewsPath)) {
            return;
        }

        $alias = strtolower($module->name);
        $this->loadViewsFrom($viewsPath, $alias);

        $this->publishes([
            $viewsPath => resource_path("views/vendor/{$alias}"),
        ], ["{$alias}-views", 'laravel-assets']);
    }

    /**
     * Register and publish the module's translations.
     */
    protected function registerTranslations(Module $module): void
    {
        $langPath = $module->path.'/Resources/lang';

        if (! File::isDirectory($langPath)) {
            return;
        }

        $alias = strtolower($module->name);
        $this->loadTranslationsFrom($langPath, $alias);

        $this->publishes([
            $langPath => lang_path("vendor/{$alias}"),
        ], ["{$alias}-translations", 'laravel-assets']);
    }

    /**
     * Register the module's database migrations.
     */
    protected function registerMigrations(Module $module): void
    {
        $migrationsPath = $module->path.'/Database/Migrations';

        if (File::isDirectory($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Publish the module's public assets.
     */
    protected function registerAssets(Module $module): void
    {
        $assetsPath = $module->path.'/Resources/assets';

        if (! File::isDirectory($assetsPath)) {
            return;
        }

        $alias = strtolower($module->name);
        $this->publishes([
            $assetsPath => public_path("modules/{$alias}"),
        ], ["{$alias}-assets", 'laravel-assets']);
    }

    /**
     * Merge and publish the module's configuration files.
     */
    protected function registerConfig(Module $module): void
    {
        $configPath = $module->path.'/Config';

        if (! File::isDirectory($configPath)) {
            return;
        }

        $alias = strtolower($module->name);

        foreach (File::files($configPath) as $configFile) {
            $configName = pathinfo((string) $configFile, PATHINFO_FILENAME);
            $this->mergeConfigFrom((string) $configFile, "modules.{$module->name}.{$configName}");
        }

        $this->publishes([
            $configPath => config_path("modules/{$alias}"),
        ], ["{$alias}-config", 'laravel-assets']);
    }

    /**
     * Auto-register Livewire components for the module.
     *
     * Gracefully skipped when Livewire is not installed.
     */
    protected function registerLivewireComponents(Module $module): void
    {
        if (! $this->app['config']->get('modularization.auto_register_livewire', true)) {
            return;
        }

        if (! class_exists(Livewire::class)) {
            return;
        }

        $livewirePath = $module->path.'/Livewire';

        if (! File::isDirectory($livewirePath)) {
            return;
        }

        $prefix = strtolower($module->name);
        $namespace = $module->namespace.'\\Livewire';

        $this->registerLivewireDirectory($livewirePath, $namespace, $prefix);
    }

    /**
     * Register Livewire components from a directory (recursive, one level deep).
     */
    private function registerLivewireDirectory(string $directory, string $namespace, string $prefix): void
    {
        foreach (File::glob("{$directory}/*.php") as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $componentClass = "{$namespace}\\{$className}";
            $alias = "{$prefix}.".Str::kebab($className);

            $this->registerLivewireComponent($componentClass, $alias);
        }

        foreach (File::directories($directory) as $subDir) {
            $subDirName = basename($subDir);
            $subNamespace = "{$namespace}\\{$subDirName}";
            $subPrefix = "{$prefix}.".Str::kebab($subDirName);

            foreach (File::glob("{$subDir}/*.php") as $file) {
                $className = pathinfo($file, PATHINFO_FILENAME);
                $componentClass = "{$subNamespace}\\{$className}";
                $alias = "{$subPrefix}.".Str::kebab($className);

                $this->registerLivewireComponent($componentClass, $alias);
            }
        }
    }

    /**
     * Register a single Livewire component if it is a valid Livewire component class.
     */
    private function registerLivewireComponent(string $componentClass, string $alias): void
    {
        if (! class_exists($componentClass)) {
            return;
        }

        if (! is_subclass_of($componentClass, Component::class)) {
            return;
        }

        Livewire::component($alias, $componentClass);
    }
}
