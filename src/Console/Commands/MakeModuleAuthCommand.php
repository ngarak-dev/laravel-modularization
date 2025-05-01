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
                            {module : The name of the module}
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create authentication scaffolding for a module using Blade templates';

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
        $moduleName = $this->argument('module');
        $force = $this->option('force');

        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $moduleName;

        // Check if module exists
        if (!$this->files->isDirectory($modulePath)) {
            $this->error("Module [{$moduleName}] does not exist!");
            return 1;
        }

        // Create authentication files
        $this->createAuthControllers($moduleName, $modulePath, $force);
        $this->createAuthViews($moduleName, $modulePath, $force);
        $this->createAuthRoutes($moduleName, $modulePath, $force);
        $this->createAuthMiddleware($moduleName, $modulePath, $force);
        $this->updateServiceProvider($moduleName, $modulePath);

        $this->info("Authentication scaffolding created successfully for module [{$moduleName}]");

        return 0;
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

            // Add auth route registration if not already there
            if (!Str::contains($content, 'loadRoutesFrom(__DIR__ . \'/../Routes/auth.php\')')) {
                $content = Str::replaceFirst(
                    'public function boot(): void',
                    "public function boot(): void\n    {\n        // Load auth routes\n        \$this->loadRoutesFrom(__DIR__ . '/../Routes/auth.php');",
                    $content
                );
            }

            // Add middleware registration if not already there
            if (!Str::contains($content, 'routeMiddleware')) {
                $namespace = config('modularization.namespace', 'Modules');
                $middlewareCode = "\n        // Register auth middleware\n        \$router = \$this->app['router'];\n        \$router->aliasMiddleware('module.auth', \\{$namespace}\\{$moduleName}\Http\Middleware\Authenticate::class);\n        \$router->aliasMiddleware('module.guest', \\{$namespace}\\{$moduleName}\Http\Middleware\RedirectIfAuthenticated::class);";

                $content = Str::replaceFirst(
                    "{\n        // ",
                    "{" . $middlewareCode . "\n        // ",
                    $content
                );
            }

            $this->files->put($providerPath, $content);
            $this->info('Service provider updated successfully.');
        } else {
            $this->warn('Service provider not found. Middleware must be registered manually.');
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
