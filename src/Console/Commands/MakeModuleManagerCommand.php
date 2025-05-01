<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeModuleManagerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-manager
                            {name? : The name of the manager module (defaults to "ModuleManager")}
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a module manager dashboard to enable/disable modules';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $moduleName = $this->argument('name') ?: 'ModuleManager';
        $force = $this->option('force');

        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $moduleName;

        // Create module directory if it doesn't exist
        if (!$this->files->isDirectory($modulePath)) {
            $this->createBaseModuleStructure($moduleName, $modulePath);
        } else if (!$force) {
            if (!$this->confirm("Module [{$moduleName}] already exists. Do you want to continue?")) {
                $this->info("Operation cancelled.");
                return 1;
            }

            // Ensure Config directory exists in existing module
            $configDir = $modulePath . '/Config';
            if (!$this->files->isDirectory($configDir)) {
                $this->files->makeDirectory($configDir, 0755, true);
                $this->createConfigFile($moduleName, $modulePath);
            } else if (!$this->files->exists($configDir . '/config.php') || $force) {
                $this->createConfigFile($moduleName, $modulePath);
            }
        }

        // Create module manager files
        $this->createModuleManagerController($moduleName, $modulePath, $force);
        $this->createModuleManagerViews($moduleName, $modulePath, $force);
        $this->createModuleManagerRoutes($moduleName, $modulePath, $force);
        $this->updateServiceProvider($moduleName, $modulePath);

        $this->info("Module Manager [{$moduleName}] created successfully");
        $this->info("You can now access your module manager at: /{$moduleName}");

        return 0;
    }

    /**
     * Create the base module structure.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function createBaseModuleStructure($moduleName, $modulePath)
    {
        $this->info("Creating module [{$moduleName}]...");

        // Create base directories
        $directories = [
            '',
            'Http/Controllers',
            'Providers',
            'Resources/views',
            'Resources/views/layouts',
            'Resources/views/dashboard',
            'Routes',
            'Config',
        ];

        foreach ($directories as $directory) {
            $path = $modulePath . ($directory ? '/' . $directory : '');
            $this->files->makeDirectory($path, 0755, true);
        }

        // Create service provider
        $namespace = config('modularization.namespace', 'Modules');
        $providerPath = $modulePath . '/Providers/' . $moduleName . 'ServiceProvider.php';

        $providerContent = <<<EOT
<?php

namespace {$namespace}\\{$moduleName}\\Providers;

use Illuminate\Support\ServiceProvider;

class {$moduleName}ServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        \$this->registerTranslations();
        \$this->registerConfig();
        \$this->registerViews();
        \$this->loadMigrationsFrom(module_path('{$moduleName}', 'Database/Migrations'));
        \$this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        \$this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        \$this->publishes([
            module_path('{$moduleName}', 'Config/config.php') => config_path('{$moduleName}.php'),
        ], 'config');
        \$this->mergeConfigFrom(
            module_path('{$moduleName}', 'Config/config.php'), strtolower('{$moduleName}')
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    protected function registerViews()
    {
        \$viewPath = resource_path('views/modules/' . strtolower('{$moduleName}'));

        \$sourcePath = module_path('{$moduleName}', 'Resources/views');

        \$this->publishes([
            \$sourcePath => \$viewPath
        ], 'views');

        \$this->loadViewsFrom(array_merge(array_map(function (\$path) {
            return \$path . '/modules/' . strtolower('{$moduleName}');
        }, \$this->app['config']->get('view.paths')), [\$sourcePath]), strtolower('{$moduleName}'));
    }

    /**
     * Register translations.
     *
     * @return void
     */
    protected function registerTranslations()
    {
        \$langPath = resource_path('lang/modules/' . strtolower('{$moduleName}'));

        if (is_dir(\$langPath)) {
            \$this->loadTranslationsFrom(\$langPath, strtolower('{$moduleName}'));
        } else {
            \$this->loadTranslationsFrom(module_path('{$moduleName}', 'Resources/lang'), strtolower('{$moduleName}'));
        }
    }
}
EOT;

        $this->files->put($providerPath, $providerContent);
        $this->line("Created: {$moduleName}ServiceProvider.php");

        // Create config file
        $this->createConfigFile($moduleName, $modulePath);
    }

    /**
     * Create config file for the module.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function createConfigFile($moduleName, $modulePath)
    {
        $configPath = $modulePath . '/Config/config.php';
        $moduleNameLower = strtolower($moduleName);

        $content = <<<EOT
<?php

return [
    'name' => '{$moduleName}',
    'description' => 'Module Manager for enabling and disabling modules',
    'enabled' => true,
    'routes' => [
        'prefix' => '{$moduleNameLower}',
        'middleware' => ['web'],
    ],
    'menu' => [
        'title' => '{$moduleName}',
        'icon' => 'fa fa-cubes',
    ],
];
EOT;

        $this->files->put($configPath, $content);
        $this->line("Created: Config/config.php");
    }

    /**
     * Create module manager controller.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $force
     * @return void
     */
    protected function createModuleManagerController($moduleName, $modulePath, $force)
    {
        $controllerPath = $modulePath . '/Http/Controllers/ModuleManagerController.php';
        $namespace = config('modularization.namespace', 'Modules');
        $moduleNameLower = strtolower($moduleName);

        if (!$this->files->exists($controllerPath) || $force) {
            $content = <<<EOT
<?php

namespace {$namespace}\\{$moduleName}\\Http\\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class ModuleManagerController extends Controller
{
    /**
     * Display the module manager dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        \$modules = \$this->getModules();
        
        return view('{$moduleNameLower}::dashboard.index', compact('modules'));
    }
    
    /**
     * Toggle module status.
     *
     * @param Request \$request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleModule(Request \$request)
    {
        \$moduleName = \$request->input('module');
        \$action = \$request->input('action');
        
        \$modulesPath = base_path(config('modularization.modules_path', 'modules'));
        \$modulePath = \$modulesPath . '/' . \$moduleName;
        
        if (\$action === 'enable') {
            if (File::exists(\$modulePath . '/.disabled')) {
                File::delete(\$modulePath . '/.disabled');
            }
            
            \$this->updateModuleConfig(\$moduleName, true);
        } else {
            File::put(\$modulePath . '/.disabled', '');
            \$this->updateModuleConfig(\$moduleName, false);
        }
        
        return redirect()->route('{$moduleNameLower}.dashboard')->with('success', "Module {\$moduleName} has been " . (\$action === 'enable' ? 'enabled' : 'disabled'));
    }
    
    /**
     * Get all modules with their status.
     *
     * @return array
     */
    protected function getModules()
    {
        \$modulesPath = base_path(config('modularization.modules_path', 'modules'));
        \$modules = [];
        
        if (!File::isDirectory(\$modulesPath)) {
            return \$modules;
        }
        
        \$directories = File::directories(\$modulesPath);
        
        foreach (\$directories as \$directory) {
            \$name = basename(\$directory);
            \$isDisabled = File::exists(\$directory . '/.disabled');
            \$configFile = \$directory . '/Config/config.php';
            
            \$config = [
                'name' => \$name,
                'description' => '',
                'enabled' => !\$isDisabled,
                'routes' => [],
                'menu' => [
                    'title' => \$name,
                    'icon' => 'fa fa-cube',
                ],
            ];
            
            if (File::exists(\$configFile)) {
                \$moduleConfig = include \$configFile;
                \$config = array_merge(\$config, \$moduleConfig);
            }
            
            \$modules[\$name] = \$config;
        }
        
        return \$modules;
    }
    
    /**
     * Update module config file.
     *
     * @param string \$moduleName
     * @param bool \$enabled
     * @return void
     */
    protected function updateModuleConfig(\$moduleName, \$enabled)
    {
        \$modulesPath = base_path(config('modularization.modules_path', 'modules'));
        \$configFile = \$modulesPath . '/' . \$moduleName . '/Config/config.php';
        
        if (File::exists(\$configFile)) {
            \$config = include \$configFile;
            \$config['enabled'] = \$enabled;
            
            \$content = "<?php\n\nreturn " . var_export(\$config, true) . ";";
            File::put(\$configFile, \$content);
        }
    }
}
EOT;

            $this->files->put($controllerPath, $content);
            $this->line("Created: ModuleManagerController.php");
        } else {
            $this->warn("Skipped: ModuleManagerController.php (already exists)");
        }
    }

    /**
     * Create module manager views.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $force
     * @return void
     */
    protected function createModuleManagerViews($moduleName, $modulePath, $force)
    {
        // Create layout view
        $layoutPath = $modulePath . '/Resources/views/layouts/master.blade.php';
        $moduleNameLower = strtolower($moduleName);

        if (!$this->files->exists($layoutPath) || $force) {
            $content = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    Module Manager
                </h1>
            </div>
        </header>
        
        <!-- Main content -->
        <main class="flex-1">
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif
                
                @yield('content')
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white shadow-inner mt-auto">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-gray-500 text-sm">
                    Laravel Modularization Package &copy; {{ date('Y') }}
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
EOT;

            $this->files->put($layoutPath, $content);
            $this->line("Created: layouts/master.blade.php");
        } else {
            $this->warn("Skipped: layouts/master.blade.php (already exists)");
        }

        // Create dashboard view
        $dashboardPath = $modulePath . '/Resources/views/dashboard/index.blade.php';

        if (!$this->files->exists($dashboardPath) || $force) {
            $content = <<<EOT
@extends('{$moduleNameLower}::layouts.master')

@section('content')
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 bg-white border-b border-gray-200">
        <h2 class="text-2xl font-bold mb-6">Manage Modules</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach(\$modules as \$name => \$module)
                <div class="bg-white rounded-lg overflow-hidden shadow hover:shadow-lg transition-shadow duration-300">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                @if(isset(\$module['menu']['icon']))
                                    <i class="{{ \$module['menu']['icon'] }} text-indigo-600 text-2xl mr-3"></i>
                                @else
                                    <i class="fa fa-cube text-indigo-600 text-2xl mr-3"></i>
                                @endif
                                <h3 class="text-xl font-semibold">{{ \$module['name'] }}</h3>
                            </div>
                            
                            <div>
                                @if(\$module['enabled'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Enabled
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Disabled
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6">
                            {{ \$module['description'] ?? 'No description available' }}
                        </p>
                        
                        <div class="flex justify-end">
                            <form method="POST" action="{{ route('{$moduleNameLower}.toggle') }}">
                                @csrf
                                <input type="hidden" name="module" value="{{ \$name }}">
                                <input type="hidden" name="action" value="{{ \$module['enabled'] ? 'disable' : 'enable' }}">
                                
                                @if(\$module['enabled'])
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Disable
                                    </button>
                                @else
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        Enable
                                    </button>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
EOT;

            $this->files->put($dashboardPath, $content);
            $this->line("Created: dashboard/index.blade.php");
        } else {
            $this->warn("Skipped: dashboard/index.blade.php (already exists)");
        }
    }

    /**
     * Create module manager routes.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $force
     * @return void
     */
    protected function createModuleManagerRoutes($moduleName, $modulePath, $force)
    {
        $routesPath = $modulePath . '/Routes/web.php';
        $namespace = config('modularization.namespace', 'Modules');
        $moduleNameLower = strtolower($moduleName);

        if (!$this->files->exists($routesPath) || $force) {
            $content = <<<EOT
<?php

use Illuminate\Support\Facades\Route;
use {$namespace}\\{$moduleName}\\Http\\Controllers\\ModuleManagerController;

Route::middleware('web')->group(function() {
    Route::get('/', [ModuleManagerController::class, 'index'])->name('{$moduleNameLower}.dashboard');
    Route::post('/toggle', [ModuleManagerController::class, 'toggleModule'])->name('{$moduleNameLower}.toggle');
});
EOT;

            $this->files->put($routesPath, $content);
            $this->info('Module manager routes created successfully.');
        } else {
            $this->warn('Skipped routes (already exists)');
        }
    }

    /**
     * Update service provider to register routes.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function updateServiceProvider($moduleName, $modulePath)
    {
        $providerPath = $modulePath . '/Providers/' . $moduleName . 'ServiceProvider.php';

        if ($this->files->exists($providerPath)) {
            $content = $this->files->get($providerPath);

            // Ensure routes are loaded
            if (strpos($content, 'loadRoutesFrom(__DIR__ . \'/../Routes/web.php\')') === false) {
                $pattern = '/public function boot\(\).*?\{/s';
                $replacement = "public function boot()\n    {\n        \$this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');";
                $content = preg_replace($pattern, $replacement, $content);
                $this->files->put($providerPath, $content);
                $this->info('Service provider updated successfully.');
            } else {
                $this->warn('Service provider already has routes registered.');
            }
        } else {
            $this->error('Service provider not found.');
        }

        // Create RouteServiceProvider
        $this->createRouteServiceProvider($moduleName, $modulePath);
    }

    /**
     * Create RouteServiceProvider for the module.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function createRouteServiceProvider($moduleName, $modulePath)
    {
        $providerPath = $modulePath . '/Providers/RouteServiceProvider.php';
        $namespace = config('modularization.namespace', 'Modules');
        $moduleNameLower = strtolower($moduleName);

        if (!$this->files->exists($providerPath)) {
            $content = <<<EOT
<?php

namespace {$namespace}\\{$moduleName}\\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected \$moduleNamespace = '{$namespace}\\{$moduleName}\\Http\\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        \$this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->prefix('{$moduleNameLower}')
            ->group(module_path('{$moduleName}', 'Routes/web.php'));
    }
}
EOT;

            $this->files->put($providerPath, $content);
            $this->line("Created: RouteServiceProvider.php");
        } else {
            $this->warn("Skipped: RouteServiceProvider.php (already exists)");
        }
    }
}
