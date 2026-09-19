<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Generators;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\ModuleCache;
use NgarakDev\Modularization\ModuleConfiguration;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\Support\ModuleName;

final class ManagerModuleGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
        private readonly ModuleCache $cache,
    ) {}

    public function exists(string $name): bool
    {
        return $this->files->isDirectory($this->paths->path(ModuleName::parse($name)->studly()));
    }

    /**
     * @return array<int, string>
     */
    public function generate(string $name, bool $force = false): array
    {
        $module = ModuleName::parse($name);
        $modulePath = $this->paths->path($module->studly());
        $created = [];

        if (! $this->files->isDirectory($modulePath)) {
            $created = array_merge($created, $this->createBaseModuleStructure($module, $modulePath));
        } else {
            $configDir = $this->paths->join($modulePath, 'Config');
            $this->files->ensureDirectoryExists($configDir, 0755);

            if (! $this->files->exists($configDir.'/config.php')) {
                $created[] = $this->createConfigFile($module, $modulePath);
            }
        }

        $created[] = $this->writeIfAllowed(
            $this->paths->join($modulePath, 'Http/Controllers/ModuleManagerController.php'),
            $this->controllerStub($module),
            $force
        );
        $created[] = $this->writeIfAllowed(
            $this->paths->join($modulePath, 'Resources/views/layouts/master.blade.php'),
            $this->layoutStub(),
            $force
        );
        $created[] = $this->writeIfAllowed(
            $this->paths->join($modulePath, 'Resources/views/dashboard/index.blade.php'),
            $this->dashboardStub($module),
            $force
        );
        $created[] = $this->writeIfAllowed(
            $this->paths->join($modulePath, 'Routes/web.php'),
            $this->routesStub($module),
            $force
        );

        $this->updateServiceProvider($module, $modulePath);
        $this->writeIfAllowed(
            $this->paths->join($modulePath, 'Providers/RouteServiceProvider.php'),
            $this->routeServiceProviderStub($module),
            false
        );
        $this->cache->forget();

        return array_values(array_filter($created));
    }

    /**
     * @return array<int, string>
     */
    private function createBaseModuleStructure(ModuleName $module, string $modulePath): array
    {
        foreach ([
            'Http/Controllers',
            'Providers',
            'Resources/views',
            'Resources/views/layouts',
            'Resources/views/dashboard',
            'Routes',
            'Config',
        ] as $directory) {
            $this->files->ensureDirectoryExists($this->paths->join($modulePath, $directory), 0755);
        }

        $providerPath = $this->paths->join($modulePath, 'Providers/'.$module->studly().'ServiceProvider.php');
        $this->files->put($providerPath, $this->providerStub($module));

        return [$providerPath, $this->createConfigFile($module, $modulePath)];
    }

    private function createConfigFile(ModuleName $module, string $modulePath): string
    {
        $configPath = $this->paths->join($modulePath, 'Config/config.php');
        $moduleName = $module->studly();
        $moduleNameLower = $module->lower();
        $content = <<<PHP
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
PHP;
        $this->files->put($configPath, $content);

        return $configPath;
    }

    private function updateServiceProvider(ModuleName $module, string $modulePath): void
    {
        $providerPath = $this->paths->join($modulePath, 'Providers/'.$module->studly().'ServiceProvider.php');

        if (! $this->files->exists($providerPath)) {
            return;
        }

        $content = $this->files->get($providerPath);

        if (str_contains($content, "loadRoutesFrom(__DIR__ . '/../Routes/web.php')")) {
            return;
        }

        $updated = preg_replace(
            '/public function boot\(\).*?\{/s',
            "public function boot()\n    {\n        \$this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');",
            $content
        );

        if (is_string($updated)) {
            $this->files->put($providerPath, $updated);
        }
    }

    private function writeIfAllowed(string $path, string $content, bool $force): string
    {
        if ($this->files->exists($path) && ! $force) {
            return '';
        }

        $this->files->ensureDirectoryExists(dirname($path), 0755);
        $this->files->put($path, $content);

        return $path;
    }

    private function providerStub(ModuleName $module): string
    {
        $namespace = $this->configuration->namespace();
        $moduleName = $module->studly();

        return <<<PHP
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
PHP;
    }

    private function controllerStub(ModuleName $module): string
    {
        $namespace = $this->configuration->namespace();
        $moduleName = $module->studly();
        $moduleNameLower = $module->lower();

        return <<<PHP
<?php

namespace {$namespace}\\{$moduleName}\\Http\\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

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
     * @return array<string, mixed>
     */
    protected function getModules(): array
    {
        \$modulesPath = base_path(config('modularization.modules_path', 'modules'));
        \$modules = [];

        if (! File::isDirectory(\$modulesPath)) {
            return \$modules;
        }

        foreach (File::directories(\$modulesPath) as \$directory) {
            \$name = basename(\$directory);
            \$isDisabled = File::exists(\$directory . '/.disabled');
            \$configFile = \$directory . '/Config/config.php';

            \$config = [
                'name' => \$name,
                'description' => '',
                'enabled' => ! \$isDisabled,
                'routes' => [],
                'menu' => [
                    'title' => \$name,
                    'icon' => 'fa fa-cube',
                ],
            ];

            if (File::exists(\$configFile)) {
                \$moduleConfig = include \$configFile;
                if (is_array(\$moduleConfig)) {
                    \$config = array_merge(\$config, \$moduleConfig);
                }
            }

            \$modules[\$name] = \$config;
        }

        return \$modules;
    }

    protected function updateModuleConfig(string \$moduleName, bool \$enabled): void
    {
        \$modulesPath = base_path(config('modularization.modules_path', 'modules'));
        \$configFile = \$modulesPath . '/' . \$moduleName . '/Config/config.php';

        if (File::exists(\$configFile)) {
            \$config = include \$configFile;
            if (! is_array(\$config)) {
                \$config = [];
            }
            \$config['enabled'] = \$enabled;

            \$content = "<?php\\n\\nreturn " . var_export(\$config, true) . ";";
            File::put(\$configFile, \$content);
        }
    }
}
PHP;
    }

    private function layoutStub(): string
    {
        return <<<'EOT'
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
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    Module Manager
                </h1>
            </div>
        </header>

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
    }

    private function dashboardStub(ModuleName $module): string
    {
        $moduleNameLower = $module->lower();

        return <<<EOT
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
    }

    private function routesStub(ModuleName $module): string
    {
        $namespace = $this->configuration->namespace();
        $moduleName = $module->studly();
        $moduleNameLower = $module->lower();

        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$namespace}\\{$moduleName}\\Http\\Controllers\\ModuleManagerController;

Route::middleware('web')->group(function() {
    Route::get('/', [ModuleManagerController::class, 'index'])->name('{$moduleNameLower}.dashboard');
    Route::post('/toggle', [ModuleManagerController::class, 'toggleModule'])->name('{$moduleNameLower}.toggle');
});
PHP;
    }

    private function routeServiceProviderStub(ModuleName $module): string
    {
        $namespace = $this->configuration->namespace();
        $moduleName = $module->studly();
        $moduleNameLower = $module->lower();

        return <<<PHP
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
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->prefix('{$moduleNameLower}')
            ->group(module_path('{$moduleName}', 'Routes/web.php'));
    }
}
PHP;
    }
}
