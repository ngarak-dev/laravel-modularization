<?php

namespace NgarakDev\Modularization\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use ReflectionClass;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleLivewireCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleEventCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleTranslationCommand;
use NgarakDev\Modularization\Console\Commands\ModuleExportCommand;
use NgarakDev\Modularization\Console\Commands\ModuleToggleCommand;
use NgarakDev\Modularization\Console\Commands\PublishStubsCommand;
use NgarakDev\Modularization\ModularizationService;

class ModularizationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // Load modules
        $this->loadModules();

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/modularization.php' => config_path('modularization.php'),
        ], 'modularization-config');

        // Publish stubs
        $this->publishes([
            __DIR__ . '/../../stubs' => base_path('stubs/vendor/modularization'),
        ], 'modularization-stubs');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                'command.module.make',
                'command.make.module',
                'command.module.make-livewire',
                'command.module.publish-stubs',
                'command.module.toggle',
                'command.module.make-event',
                'command.module.make-translation',
                'command.module.export',
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/modularization.php',
            'modularization'
        );

        // Register the ModularizationService
        $this->app->singleton('modularization', function ($app) {
            return new ModularizationService($app['files']);
        });

        // Register Facade alias
        $this->app->alias('modularization', ModularizationService::class);

        // Register commands
        $this->app->singleton('command.module.make', function ($app) {
            return new MakeModuleCommand($app['files']);
        });

        // Register command with alias
        $this->app->singleton('command.make.module', function ($app) {
            return $app['command.module.make'];
        });

        // Register Livewire command
        $this->app->singleton('command.module.make-livewire', function ($app) {
            return new MakeModuleLivewireCommand();
        });

        // Register publish-stubs command
        $this->app->singleton('command.module.publish-stubs', function ($app) {
            return new PublishStubsCommand($app['files']);
        });

        // Register module:toggle command
        $this->app->singleton('command.module.toggle', function ($app) {
            return new ModuleToggleCommand($app['files']);
        });

        // Register module:make-event command
        $this->app->singleton('command.module.make-event', function ($app) {
            return new MakeModuleEventCommand($app['files']);
        });

        // Register module:make-translation command
        $this->app->singleton('command.module.make-translation', function ($app) {
            return new MakeModuleTranslationCommand($app['files']);
        });

        // Register module:export command
        $this->app->singleton('command.module.export', function ($app) {
            return new ModuleExportCommand($app['files']);
        });
    }

    /**
     * Load all modules.
     */
    private function loadModules()
    {
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $module) {
            $moduleName = basename($module);

            // Skip disabled modules
            if (File::exists($module . '/.disabled')) {
                continue;
            }

            // Register Service Provider from module
            $this->registerModuleServiceProvider($module, $moduleName);

            // Register Routes
            $this->registerRoutes($module, $moduleName);

            // Register Views
            $this->registerViews($module, $moduleName);

            // Register Translations
            $this->registerTranslations($module, $moduleName);

            // Register Migrations
            $this->registerMigrations($module, $moduleName);

            // Register Assets
            $this->registerAssets($module, $moduleName);

            // Register Livewire Components
            $this->registerLivewireComponents($module, $moduleName);

            // Register Config
            $this->registerConfig($module, $moduleName);
        }
    }

    /**
     * Register module service provider.
     */
    protected function registerModuleServiceProvider($module, $moduleName): void
    {
        $namespace = config('modularization.namespace', 'Modules');
        $providerPath = "$module/Providers/{$moduleName}ServiceProvider.php";

        if (File::exists($providerPath)) {
            $providerClass = "{$namespace}\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Register routes for the module.
     */
    protected function registerRoutes($module, $moduleName): void
    {
        $moduleNameLower = strtolower($moduleName);
        $namespace = config('modularization.namespace', 'Modules');

        // Web Routes
        $webRoutesPath = "$module/Routes/web.php";
        if (File::exists($webRoutesPath)) {
            Route::middleware('web')
                ->name($moduleNameLower . '.')
                ->namespace("{$namespace}\\{$moduleName}\\Http\\Controllers")
                ->group($webRoutesPath);
        }

        // API Routes
        $apiRoutesPath = "$module/Routes/api.php";
        if (File::exists($apiRoutesPath)) {
            Route::prefix('api')
                ->middleware('api')
                ->name('api.' . $moduleNameLower . '.')
                ->namespace("{$namespace}\\{$moduleName}\\Http\\Controllers")
                ->group($apiRoutesPath);
        }

        // Livewire Routes
        $livewireRoutesPath = "$module/Routes/livewire.php";
        if (File::exists($livewireRoutesPath)) {
            Route::middleware('web')
                ->name($moduleNameLower . '.')
                ->group($livewireRoutesPath);
        }
    }

    /**
     * Register views for the module.
     */
    protected function registerViews($module, $moduleName): void
    {
        $viewsPath = "$module/Resources/views";
        if (File::isDirectory($viewsPath)) {
            $this->loadViewsFrom($viewsPath, strtolower($moduleName));

            // Allow publishing view files
            $this->publishes([
                $viewsPath => resource_path("views/vendor/" . strtolower($moduleName)),
            ], [strtolower($moduleName) . '-views', 'laravel-assets']);
        }
    }

    /**
     * Register translations for the module.
     */
    protected function registerTranslations($module, $moduleName): void
    {
        $translationsPath = "$module/Resources/lang";
        if (File::isDirectory($translationsPath)) {
            $this->loadTranslationsFrom($translationsPath, strtolower($moduleName));

            // Allow publishing language files
            $this->publishes([
                $translationsPath => lang_path("vendor/" . strtolower($moduleName)),
            ], [strtolower($moduleName) . '-translations', 'laravel-assets']);
        }
    }

    /**
     * Register migrations for the module.
     */
    protected function registerMigrations($module, $moduleName): void
    {
        $migrationsPath = "$module/Database/Migrations";
        if (File::isDirectory($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Register assets for the module.
     */
    protected function registerAssets($module, $moduleName): void
    {
        $assetsPath = "$module/Resources/assets";
        if (File::isDirectory($assetsPath)) {
            $this->publishes([
                $assetsPath => public_path("modules/" . strtolower($moduleName)),
            ], [strtolower($moduleName) . '-assets', 'laravel-assets']);
        }
    }

    /**
     * Register configuration for the module.
     */
    protected function registerConfig($module, $moduleName): void
    {
        $configPath = "$module/Config";
        if (File::isDirectory($configPath)) {
            $configFiles = File::files($configPath);
            foreach ($configFiles as $configFile) {
                $configName = pathinfo($configFile, PATHINFO_FILENAME);
                $this->mergeConfigFrom($configFile, "modules.{$moduleName}.{$configName}");
            }

            // Allow publishing config files
            $this->publishes([
                $configPath => config_path("modules/" . strtolower($moduleName)),
            ], [strtolower($moduleName) . '-config', 'laravel-assets']);
        }
    }

    /**
     * Register Livewire components for the module.
     */
    protected function registerLivewireComponents($module, $moduleName): void
    {
        $livewirePath = "$module/Livewire";
        if (!File::isDirectory($livewirePath)) {
            return;
        }

        $namespace = config('modularization.namespace', 'Modules');
        $fullNamespace = "{$namespace}\\{$moduleName}\\Livewire";
        $prefix = strtolower($moduleName);

        // Find all PHP files in the Livewire directory
        $files = File::glob("$livewirePath/*.php");

        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $componentClass = "{$fullNamespace}\\{$fileName}";

            // Make sure the class exists and is a Livewire component
            if (class_exists($componentClass)) {
                $reflection = new ReflectionClass($componentClass);
                if ($reflection->isSubclassOf(\Livewire\Component::class)) {
                    $alias = "{$prefix}.{$this->kebabCase($fileName)}";
                    Livewire::component($alias, $componentClass);
                }
            }
        }

        // Check for subdirectories
        $directories = File::directories($livewirePath);
        foreach ($directories as $directory) {
            $directoryName = basename($directory);
            $subFiles = File::glob("$directory/*.php");

            foreach ($subFiles as $file) {
                $fileName = pathinfo($file, PATHINFO_FILENAME);
                $componentClass = "{$fullNamespace}\\{$directoryName}\\{$fileName}";

                if (class_exists($componentClass)) {
                    $reflection = new ReflectionClass($componentClass);
                    if ($reflection->isSubclassOf(\Livewire\Component::class)) {
                        $alias = "{$prefix}.{$this->kebabCase($directoryName)}.{$this->kebabCase($fileName)}";
                        Livewire::component($alias, $componentClass);
                    }
                }
            }
        }
    }

    /**
     * Convert a string to kebab case.
     */
    protected function kebabCase($string): string
    {
        return strtolower(preg_replace(
            ['/([a-z\d])([A-Z])/', '/([^-])([A-Z][a-z])/'],
            ['$1-$2', '$1-$2'],
            $string
        ));
    }
}
