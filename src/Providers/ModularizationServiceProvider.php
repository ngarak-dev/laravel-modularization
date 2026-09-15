<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use NgarakDev\Modularization\Console\Commands\MakeModuleAuthCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleEventCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleLivewireCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleManagerCommand;
use NgarakDev\Modularization\Console\Commands\MakeMigrationCommand;
use NgarakDev\Modularization\Console\Commands\MigrateModuleCommand;
use NgarakDev\Modularization\Console\Commands\MigrateModulesCommand;
use NgarakDev\Modularization\Console\Commands\ModuleCacheCommand;
use NgarakDev\Modularization\Console\Commands\ModuleClearCommand;
use NgarakDev\Modularization\Console\Commands\ModuleExportCommand;
use NgarakDev\Modularization\Console\Commands\ModuleListCommand;
use NgarakDev\Modularization\Console\Commands\ModuleToggleCommand;
use NgarakDev\Modularization\Console\Commands\PublishStubsCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleTranslationCommand;
use NgarakDev\Modularization\Contracts\ModuleCacheInterface;
use NgarakDev\Modularization\Contracts\ModuleDiscoveryInterface;
use NgarakDev\Modularization\Contracts\ModuleLoaderInterface;
use NgarakDev\Modularization\Contracts\ModuleRepositoryInterface;
use NgarakDev\Modularization\Contracts\ModuleStatusManagerInterface;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Services\DependencyResolver;
use NgarakDev\Modularization\Services\ModuleCache;
use NgarakDev\Modularization\Services\ModuleDiscovery;
use NgarakDev\Modularization\Services\ModuleLoader;
use NgarakDev\Modularization\Services\ModuleRepository;
use NgarakDev\Modularization\Services\ModuleStatusManager;
use NgarakDev\Modularization\Support\ModulePathResolver;

class ModularizationServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/modularization.php',
            'modularization'
        );

        $this->registerCoreServices();
        $this->registerCommands();
        $this->registerLegacyBindings();
    }

    /**
     * Bootstrap the service provider.
     */
    public function boot(): void
    {
        $this->bootModules();
        $this->registerPublishes();
    }

    /**
     * Register core services with the container.
     */
    private function registerCoreServices(): void
    {
        $this->app->singleton(ModulePathResolver::class, function (Application $app) {
            return new ModulePathResolver($app);
        });

        $this->app->singleton(ModuleDiscoveryInterface::class, function (Application $app) {
            return new ModuleDiscovery(
                $app,
                $app['files'],
                $app->make(ModulePathResolver::class),
            );
        });

        $this->app->singleton(ModuleRepositoryInterface::class, function (Application $app) {
            return new ModuleRepository(
                $app->make(ModuleDiscoveryInterface::class),
            );
        });

        $this->app->singleton(ModuleLoaderInterface::class, function (Application $app) {
            return new ModuleLoader(
                $app,
                $app['files'],
            );
        });

        $this->app->singleton(ModuleCacheInterface::class, function (Application $app) {
            return new ModuleCache(
                $app,
                $app['files'],
            );
        });

        $this->app->singleton(ModuleStatusManagerInterface::class, function (Application $app) {
            return new ModuleStatusManager(
                $app->make(ModuleRepositoryInterface::class),
                $app->make(ModulePathResolver::class),
                $app['files'],
            );
        });

        $this->app->singleton(DependencyResolver::class, function (Application $app) {
            return new DependencyResolver(
                $app->make(ModuleRepositoryInterface::class),
            );
        });

        $this->app->singleton(ModuleManager::class, function (Application $app) {
            return new ModuleManager(
                $app->make(ModuleRepositoryInterface::class),
                $app->make(ModuleLoaderInterface::class),
                $app->make(ModuleStatusManagerInterface::class),
                $app->make(ModuleCacheInterface::class),
                $app->make(DependencyResolver::class),
            );
        });
    }

    /**
     * Register legacy bindings for backwards compatibility.
     */
    private function registerLegacyBindings(): void
    {
        $this->app->singleton('modularization', function (Application $app) {
            return new ModularizationService(
                $app->make(ModuleManager::class),
            );
        });

        $this->app->alias('modularization', ModularizationService::class);
        $this->app->alias(ModuleManager::class, 'modules');
    }

    /**
     * Register console commands.
     */
    private function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->app->singleton('command.module.make', fn ($app) => new MakeModuleCommand($app['files']));
        $this->app->singleton('command.make.module', fn ($app) => $app['command.module.make']);
        $this->app->singleton('command.module.make-livewire', fn () => new MakeModuleLivewireCommand());
        $this->app->singleton('command.module.publish-stubs', fn ($app) => new PublishStubsCommand($app['files']));
        $this->app->singleton('command.module.toggle', fn ($app) => new ModuleToggleCommand($app['files']));
        $this->app->singleton('command.module.make-event', fn ($app) => new MakeModuleEventCommand($app['files']));
        $this->app->singleton('command.module.make-translation', fn ($app) => new MakeModuleTranslationCommand($app['files']));
        $this->app->singleton('command.module.export', fn ($app) => new ModuleExportCommand($app['files']));
        $this->app->singleton('command.module.make-auth', fn ($app) => new MakeModuleAuthCommand($app['files']));
        $this->app->singleton('command.module.make-manager', fn ($app) => new MakeModuleManagerCommand($app['files']));
        $this->app->singleton('command.module.migrate', fn () => new MigrateModuleCommand());
        $this->app->singleton('command.module.migrate-all', fn () => new MigrateModulesCommand());
        $this->app->singleton('command.module.make-migration', fn () => new MakeMigrationCommand());
        $this->app->singleton('command.module.list', fn ($app) => new ModuleListCommand($app->make(ModuleManager::class)));
        $this->app->singleton('command.module.cache', fn ($app) => new ModuleCacheCommand($app->make(ModuleManager::class)));
        $this->app->singleton('command.module.clear', fn ($app) => new ModuleClearCommand($app->make(ModuleManager::class)));

        $this->commands([
            'command.module.make',
            'command.make.module',
            'command.module.make-livewire',
            'command.module.publish-stubs',
            'command.module.toggle',
            'command.module.make-event',
            'command.module.make-translation',
            'command.module.export',
            'command.module.make-auth',
            'command.module.make-manager',
            'command.module.migrate',
            'command.module.migrate-all',
            'command.module.make-migration',
            'command.module.list',
            'command.module.cache',
            'command.module.clear',
        ]);
    }

    /**
     * Boot all enabled modules.
     */
    private function bootModules(): void
    {
        $manager = $this->app->make(ModuleManager::class);
        $manager->boot();
    }

    /**
     * Register publishable assets.
     */
    private function registerPublishes(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../../config/modularization.php' => config_path('modularization.php'),
        ], 'modularization-config');

        $this->publishes([
            __DIR__ . '/../../stubs' => base_path('stubs/vendor/modularization'),
        ], 'modularization-stubs');
    }
}
