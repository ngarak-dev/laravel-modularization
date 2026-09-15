<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Providers;

use Illuminate\Support\ServiceProvider;
use NgarakDev\Modularization\Console\Commands\MakeMigrationCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleAuthCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleClassCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleConsoleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleControllerCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleEventCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleFactoryCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleJobCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleListenerCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleLivewireCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleManagerCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleModelCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleNotificationCommand;
use NgarakDev\Modularization\Console\Commands\MakeModulePolicyCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleRepositoryCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleRequestCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleResourceCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleSeederCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleServiceCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleTestCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleTranslationCommand;
use NgarakDev\Modularization\Console\Commands\MigrateModuleCommand;
use NgarakDev\Modularization\Console\Commands\MigrateModulesCommand;
use NgarakDev\Modularization\Console\Commands\ModuleCacheCommand;
use NgarakDev\Modularization\Console\Commands\ModuleClearCommand;
use NgarakDev\Modularization\Console\Commands\ModuleDiscoverCommand;
use NgarakDev\Modularization\Console\Commands\ModuleExportCommand;
use NgarakDev\Modularization\Console\Commands\ModuleListCommand;
use NgarakDev\Modularization\Console\Commands\ModuleToggleCommand;
use NgarakDev\Modularization\Console\Commands\PublishStubsCommand;
use NgarakDev\Modularization\DependencyResolver;
use NgarakDev\Modularization\Generators\ClassGenerator;
use NgarakDev\Modularization\Generators\ModuleGenerator;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\ModuleCache;
use NgarakDev\Modularization\ModuleConfiguration;
use NgarakDev\Modularization\ModuleDiscovery;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\ModuleManifest;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\ModuleRegistrar;
use NgarakDev\Modularization\ModuleStatusManager;
use NgarakDev\Modularization\StubLocator;

class ModularizationServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    private array $commands = [
        MakeModuleCommand::class,
        MakeModuleLivewireCommand::class,
        PublishStubsCommand::class,
        ModuleToggleCommand::class,
        MakeModuleEventCommand::class,
        MakeModuleTranslationCommand::class,
        ModuleExportCommand::class,
        MakeModuleAuthCommand::class,
        MakeModuleManagerCommand::class,
        MigrateModuleCommand::class,
        MigrateModulesCommand::class,
        MakeMigrationCommand::class,
        ModuleListCommand::class,
        ModuleCacheCommand::class,
        ModuleClearCommand::class,
        ModuleDiscoverCommand::class,
        MakeModuleClassCommand::class,
        MakeModuleControllerCommand::class,
        MakeModuleModelCommand::class,
        MakeModuleRepositoryCommand::class,
        MakeModuleServiceCommand::class,
        MakeModuleRequestCommand::class,
        MakeModuleResourceCommand::class,
        MakeModuleSeederCommand::class,
        MakeModuleFactoryCommand::class,
        MakeModulePolicyCommand::class,
        MakeModuleJobCommand::class,
        MakeModuleNotificationCommand::class,
        MakeModuleConsoleCommand::class,
        MakeModuleTestCommand::class,
        MakeModuleListenerCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/modularization.php', 'modularization');

        $this->app->singleton(ModuleConfiguration::class);
        $this->app->singleton(ModulePathResolver::class);
        $this->app->singleton(ModuleManifest::class);
        $this->app->singleton(ModuleCache::class);
        $this->app->singleton(ModuleDiscovery::class);
        $this->app->singleton(DependencyResolver::class);
        $this->app->singleton(ModuleStatusManager::class);
        $this->app->singleton(ModuleRegistrar::class);
        $this->app->singleton(StubLocator::class);
        $this->app->singleton(ModuleGenerator::class);
        $this->app->singleton(ClassGenerator::class);
        $this->app->singleton(ModuleManager::class);

        $this->app->singleton('modularization', function ($app) {
            return new ModularizationService($app['files'], $app->make(ModuleManager::class));
        });

        $this->app->alias('modularization', ModularizationService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/modularization.php' => config_path('modularization.php'),
        ], 'modularization-config');

        $this->publishes([
            __DIR__.'/../../stubs' => base_path('stubs/vendor/modularization'),
        ], 'modularization-stubs');

        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);

            if (method_exists($this, 'optimizes')) {
                $this->optimizes('module:cache', 'module:clear');
            }
        }

        $this->app->make(ModuleManager::class)->boot();
    }
}
