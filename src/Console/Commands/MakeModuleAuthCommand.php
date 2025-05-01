<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeModuleAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-auth
                            {name? : The name of the authentication module (defaults to "Auth")}
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a standalone authentication module with Blade templates';

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
        $moduleName = $this->argument('name') ?: 'Auth';
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

        // Create authentication files
        $this->createAuthControllers($moduleName, $modulePath, $force);
        $this->createAuthViews($moduleName, $modulePath, $force);
        $this->createAuthRoutes($moduleName, $modulePath, $force);
        $this->createAuthMiddleware($moduleName, $modulePath, $force);
        $this->updateServiceProvider($moduleName, $modulePath);

        $this->info("Authentication module [{$moduleName}] created successfully");
        $this->info("You can now access your authentication system at: /auth/login");

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
            'Http/Controllers/Auth',
            'Http/Middleware',
            'Providers',
            'Resources/views',
            'Resources/views/layouts',
            'Resources/views/auth',
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

        $providerContent = $this->getStub('module-provider', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $moduleName,
            '{{moduleNameLower}}' => strtolower($moduleName),
        ]);

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
    'description' => '{$moduleName} Module',
    'enabled' => true,
    'routes' => [
        'prefix' => '{$moduleNameLower}',
        'middleware' => ['web'],
    ],
    'menu' => [
        'title' => '{$moduleName}',
        'icon' => 'fa fa-lock',
    ],
];
EOT;

        $this->files->put($configPath, $content);
        $this->line("Created: Config/config.php");
    }

    /**
     * Create authentication controllers.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $force
     * @return void
     */
    protected function createAuthControllers($moduleName, $modulePath, $force)
    {
        $controllersPath = $modulePath . '/Http/Controllers/Auth';

        if (!$this->files->isDirectory($controllersPath)) {
            $this->files->makeDirectory($controllersPath, 0755, true);
        }

        // Create Login Controller
        $this->createController($moduleName, $controllersPath, 'LoginController', $force);

        // Create Register Controller
        $this->createController($moduleName, $controllersPath, 'RegisterController', $force);

        // Create Forgot Password Controller
        $this->createController($moduleName, $controllersPath, 'ForgotPasswordController', $force);

        // Create Reset Password Controller
        $this->createController($moduleName, $controllersPath, 'ResetPasswordController', $force);

        // Create Verify Email Controller
        $this->createController($moduleName, $controllersPath, 'VerifyEmailController', $force);

        $this->info('Authentication controllers created successfully.');
    }

    /**
     * Create authentication controller.
     *
     * @param string $moduleName
     * @param string $path
     * @param string $controller
     * @param bool $force
     * @return void
     */
    protected function createController($moduleName, $path, $controller, $force)
    {
        $controllerPath = $path . '/' . $controller . '.php';

        if (!$this->files->exists($controllerPath) || $force) {
            $namespace = config('modularization.namespace', 'Modules');

            $content = $this->getStub('auth-controllers/' . Str::kebab($controller), [
                '{{namespace}}' => $namespace,
                '{{moduleName}}' => $moduleName,
                '{{moduleNameLower}}' => strtolower($moduleName),
            ]);

            $this->files->put($controllerPath, $content);
            $this->line("Created: {$controller}");
        } else {
            $this->warn("Skipped: {$controller} (already exists)");
        }
    }

    /**
     * Create authentication views.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $force
     * @return void
     */
    protected function createAuthViews($moduleName, $modulePath, $force)
    {
        $viewsPath = $modulePath . '/Resources/views/auth';

        if (!$this->files->isDirectory($viewsPath)) {
            $this->files->makeDirectory($viewsPath, 0755, true);
        }

        // Create login view
        $this->createView($moduleName, $viewsPath, 'login', $force);

        // Create register view
        $this->createView($moduleName, $viewsPath, 'register', $force);

        // Create forgot-password view
        $this->createView($moduleName, $viewsPath, 'forgot-password', $force);

        // Create reset-password view
        $this->createView($moduleName, $viewsPath, 'reset-password', $force);

        // Create verify-email view
        $this->createView($moduleName, $viewsPath, 'verify-email', $force);

        // Create auth layout
        $layoutsPath = $modulePath . '/Resources/views/layouts';
        if (!$this->files->isDirectory($layoutsPath)) {
            $this->files->makeDirectory($layoutsPath, 0755, true);
        }

        $this->createView($moduleName, $layoutsPath, 'auth-layout', $force);

        // Create dashboard view
        $dashboardPath = $modulePath . '/Resources/views/dashboard';
        if (!$this->files->isDirectory($dashboardPath)) {
            $this->files->makeDirectory($dashboardPath, 0755, true);
        }

        $this->createDashboardView($moduleName, $dashboardPath, $force);

        $this->info('Authentication views created successfully.');
    }

    /**
     * Create authentication view.
     *
     * @param string $moduleName
     * @param string $path
     * @param string $view
     * @param bool $force
     * @return void
     */
    protected function createView($moduleName, $path, $view, $force)
    {
        $viewPath = $path . '/' . $view . '.blade.php';

        if (!$this->files->exists($viewPath) || $force) {
            $content = $this->getStub('auth-views/' . $view, [
                '{{moduleName}}' => $moduleName,
                '{{moduleNameLower}}' => strtolower($moduleName),
            ]);

            $this->files->put($viewPath, $content);
            $this->line("Created view: {$view}");
        } else {
            $this->warn("Skipped view: {$view} (already exists)");
        }
    }

    /**
     * Create a dashboard view.
     *
     * @param string $moduleName
     * @param string $path
     * @param bool $force
     * @return void
     */
    protected function createDashboardView($moduleName, $path, $force)
    {
        $viewPath = $path . '/index.blade.php';
        $moduleNameLower = strtolower($moduleName);

        if (!$this->files->exists($viewPath) || $force) {
            $content = <<<EOT
@extends('{$moduleNameLower}::layouts.auth-layout')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h1 class="text-2xl font-bold mb-4">Welcome to Your Dashboard</h1>
                <p>You're logged in as <strong>{{ Auth::user()->name }}</strong>!</p>
                
                <div class="mt-6">
                    <form method="POST" action="{{ route('{$moduleNameLower}.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;

            $this->files->put($viewPath, $content);
            $this->line("Created: dashboard/index.blade.php");
        } else {
            $this->warn("Skipped: dashboard/index.blade.php (already exists)");
        }
    }

    /**
     * Create authentication routes.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $force
     * @return void
     */
    protected function createAuthRoutes($moduleName, $modulePath, $force)
    {
        $routesPath = $modulePath . '/Routes/auth.php';

        if (!$this->files->exists($routesPath) || $force) {
            $namespace = config('modularization.namespace', 'Modules');

            $content = $this->getStub('auth-routes', [
                '{{namespace}}' => $namespace,
                '{{moduleName}}' => $moduleName,
                '{{moduleNameLower}}' => strtolower($moduleName),
            ]);

            $this->files->put($routesPath, $content);
            $this->info('Authentication routes created successfully.');
        } else {
            $this->warn('Skipped auth routes (already exists)');
        }

        // Create dashboard route
        $dashboardRoutesPath = $modulePath . '/Routes/web.php';

        if (!$this->files->exists($dashboardRoutesPath) || $force) {
            $namespace = config('modularization.namespace', 'Modules');
            $moduleNameLower = strtolower($moduleName);

            $content = <<<EOT
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', '{$moduleNameLower}.auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('{$moduleNameLower}::dashboard.index');
    })->name('{$moduleNameLower}.dashboard');
});
EOT;

            $this->files->put($dashboardRoutesPath, $content);
            $this->info('Dashboard route created successfully.');
        } else {
            $this->warn('Skipped dashboard route (already exists)');
        }
    }

    /**
     * Create authentication middleware.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $force
     * @return void
     */
    protected function createAuthMiddleware($moduleName, $modulePath, $force)
    {
        $middlewarePath = $modulePath . '/Http/Middleware';

        if (!$this->files->isDirectory($middlewarePath)) {
            $this->files->makeDirectory($middlewarePath, 0755, true);
        }

        // Create Authenticate middleware
        $this->createMiddleware($moduleName, $middlewarePath, 'Authenticate', $force);

        // Create RedirectIfAuthenticated middleware
        $this->createMiddleware($moduleName, $middlewarePath, 'RedirectIfAuthenticated', $force);

        $this->info('Authentication middleware created successfully.');
    }

    /**
     * Create middleware.
     *
     * @param string $moduleName
     * @param string $path
     * @param string $middleware
     * @param bool $force
     * @return void
     */
    protected function createMiddleware($moduleName, $path, $middleware, $force)
    {
        $middlewarePath = $path . '/' . $middleware . '.php';

        if (!$this->files->exists($middlewarePath) || $force) {
            $namespace = config('modularization.namespace', 'Modules');

            $content = $this->getStub('auth-middleware/' . Str::kebab($middleware), [
                '{{namespace}}' => $namespace,
                '{{moduleName}}' => $moduleName,
                '{{moduleNameLower}}' => strtolower($moduleName),
            ]);

            $this->files->put($middlewarePath, $content);
            $this->line("Created middleware: {$middleware}");
        } else {
            $this->warn("Skipped middleware: {$middleware} (already exists)");
        }
    }

    /**
     * Update service provider to register auth routes and middleware.
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

            // Check if middleware already registered
            if (strpos($content, 'middlewareAliases') === false) {
                $bootMethod = <<<'EOT'
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path('{{moduleName}}', 'Database/Migrations'));
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/auth.php');

        // Register middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('{{moduleNameLower}}.auth', \{{namespace}}\{{moduleName}}\Http\Middleware\Authenticate::class);
        $router->aliasMiddleware('{{moduleNameLower}}.guest', \{{namespace}}\{{moduleName}}\Http\Middleware\RedirectIfAuthenticated::class);
    }
EOT;

                $bootMethod = str_replace(
                    ['{{moduleName}}', '{{moduleNameLower}}', '{{namespace}}'],
                    [$moduleName, strtolower($moduleName), config('modularization.namespace', 'Modules')],
                    $bootMethod
                );

                // Replace boot method in service provider
                $pattern = '/public function boot\(\).*?\{.*?\}/s';
                $content = preg_replace($pattern, rtrim($bootMethod), $content);

                $this->files->put($providerPath, $content);
                $this->info('Service provider updated successfully.');
            } else {
                $this->warn('Service provider already has middleware registered.');
            }
        } else {
            $this->error('Service provider not found.');
        }
    }

    /**
     * Get stub content with replacements.
     *
     * @param string $name
     * @param array $replacements
     * @return string
     */
    protected function getStub($name, $replacements = [])
    {
        // Check for custom stub in application
        $stubPath = base_path('stubs/vendor/modularization/auth/' . $name . '.stub');

        if ($this->files->exists($stubPath)) {
            $content = $this->files->get($stubPath);
        } else {
            // Fall back to package stubs
            $packageStubsPath = __DIR__ . '/../../../stubs/auth/' . $name . '.stub';

            if ($this->files->exists($packageStubsPath)) {
                $content = $this->files->get($packageStubsPath);
            } else {
                // If no stub file exists, use stub content from this class
                $content = $this->getDefaultStubContent($name);
            }
        }

        // Replace placeholders
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    /**
     * Get default stub content for authentication files.
     *
     * @param string $name
     * @return string
     */
    protected function getDefaultStubContent($name)
    {
        // Placeholder - we'll implement these stubs in a separate method or file
        return '<?php // Stub for ' . $name;
    }
}
