<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NgarakDev\Modularization\ModuleCache;
use NgarakDev\Modularization\ModuleConfiguration;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\StubLocator;
use NgarakDev\Modularization\Support\ModuleName;

final class AuthModuleGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
        private readonly StubLocator $stubs,
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

        $created = array_merge(
            $created,
            $this->createAuthControllers($module, $modulePath, $force),
            $this->createAuthViews($module, $modulePath, $force),
            $this->createAuthRoutes($module, $modulePath, $force),
            $this->createAuthMiddleware($module, $modulePath, $force),
        );

        $this->updateServiceProvider($module, $modulePath);
        $this->cache->forget();

        return array_values(array_filter($created));
    }

    /**
     * @return array<int, string>
     */
    private function createBaseModuleStructure(ModuleName $module, string $modulePath): array
    {
        $directories = [
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
            $this->files->ensureDirectoryExists($this->paths->join($modulePath, $directory), 0755);
        }

        $namespace = $this->configuration->namespace();
        $providerPath = $this->paths->join($modulePath, 'Providers/'.$module->studly().'ServiceProvider.php');
        $providerContent = $this->stub('auth/module-provider', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $module->studly(),
            '{{moduleNameLower}}' => $module->lower(),
        ]);

        $this->files->put($providerPath, $providerContent);

        return [
            $providerPath,
            $this->createConfigFile($module, $modulePath),
        ];
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
PHP;

        $this->files->put($configPath, $content);

        return $configPath;
    }

    /**
     * @return array<int, string>
     */
    private function createAuthControllers(ModuleName $module, string $modulePath, bool $force): array
    {
        $controllersPath = $this->paths->join($modulePath, 'Http/Controllers/Auth');
        $this->files->ensureDirectoryExists($controllersPath, 0755);

        $created = [];

        foreach ([
            'LoginController',
            'RegisterController',
            'ForgotPasswordController',
            'ResetPasswordController',
            'VerifyEmailController',
        ] as $controller) {
            $created[] = $this->writeIfAllowed(
                $controllersPath.'/'.$controller.'.php',
                $this->stub('auth/auth-controllers/'.Str::kebab($controller), [
                    '{{namespace}}' => $this->configuration->namespace(),
                    '{{moduleName}}' => $module->studly(),
                    '{{moduleNameLower}}' => $module->lower(),
                ]),
                $force
            );
        }

        return array_values(array_filter($created));
    }

    /**
     * @return array<int, string>
     */
    private function createAuthViews(ModuleName $module, string $modulePath, bool $force): array
    {
        $viewsPath = $this->paths->join($modulePath, 'Resources/views/auth');
        $this->files->ensureDirectoryExists($viewsPath, 0755);

        $created = [];

        foreach (['login', 'register', 'forgot-password', 'reset-password', 'verify-email'] as $view) {
            $created[] = $this->writeIfAllowed(
                $viewsPath.'/'.$view.'.blade.php',
                $this->stub('auth/auth-views/'.$view, [
                    '{{moduleName}}' => $module->studly(),
                    '{{moduleNameLower}}' => $module->lower(),
                ]),
                $force
            );
        }

        $layoutsPath = $this->paths->join($modulePath, 'Resources/views/layouts');
        $this->files->ensureDirectoryExists($layoutsPath, 0755);
        $created[] = $this->writeIfAllowed(
            $layoutsPath.'/auth-layout.blade.php',
            $this->stub('auth/auth-views/auth-layout', [
                '{{moduleName}}' => $module->studly(),
                '{{moduleNameLower}}' => $module->lower(),
            ]),
            $force
        );

        $dashboardPath = $this->paths->join($modulePath, 'Resources/views/dashboard');
        $this->files->ensureDirectoryExists($dashboardPath, 0755);
        $created[] = $this->writeIfAllowed($dashboardPath.'/index.blade.php', $this->dashboardView($module), $force);

        return array_values(array_filter($created));
    }

    /**
     * @return array<int, string>
     */
    private function createAuthRoutes(ModuleName $module, string $modulePath, bool $force): array
    {
        $created = [];
        $routesPath = $this->paths->join($modulePath, 'Routes/auth.php');
        $created[] = $this->writeIfAllowed(
            $routesPath,
            $this->stub('auth/auth-routes', [
                '{{namespace}}' => $this->configuration->namespace(),
                '{{moduleName}}' => $module->studly(),
                '{{moduleNameLower}}' => $module->lower(),
            ]),
            $force
        );

        $dashboardRoutesPath = $this->paths->join($modulePath, 'Routes/web.php');
        $moduleNameLower = $module->lower();
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', '{$moduleNameLower}.auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('{$moduleNameLower}::dashboard.index');
    })->name('{$moduleNameLower}.dashboard');
});
PHP;

        $created[] = $this->writeIfAllowed($dashboardRoutesPath, $content, $force);

        return array_values(array_filter($created));
    }

    /**
     * @return array<int, string>
     */
    private function createAuthMiddleware(ModuleName $module, string $modulePath, bool $force): array
    {
        $middlewarePath = $this->paths->join($modulePath, 'Http/Middleware');
        $this->files->ensureDirectoryExists($middlewarePath, 0755);
        $created = [];

        foreach (['Authenticate', 'RedirectIfAuthenticated'] as $middleware) {
            $created[] = $this->writeIfAllowed(
                $middlewarePath.'/'.$middleware.'.php',
                $this->stub('auth/auth-middleware/'.Str::kebab($middleware), [
                    '{{namespace}}' => $this->configuration->namespace(),
                    '{{moduleName}}' => $module->studly(),
                    '{{moduleNameLower}}' => $module->lower(),
                ]),
                $force
            );
        }

        return array_values(array_filter($created));
    }

    private function updateServiceProvider(ModuleName $module, string $modulePath): void
    {
        $providerPath = $this->paths->join($modulePath, 'Providers/'.$module->studly().'ServiceProvider.php');

        if (! $this->files->exists($providerPath)) {
            return;
        }

        $content = $this->files->get($providerPath);

        if (str_contains($content, 'middlewareAliases')) {
            return;
        }

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
            [$module->studly(), $module->lower(), $this->configuration->namespace()],
            $bootMethod,
        );

        $updated = preg_replace('/public function boot\(\).*?\{.*?\}/s', rtrim($bootMethod), $content);

        if (is_string($updated)) {
            $this->files->put($providerPath, $updated);
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function stub(string $name, array $replacements = []): string
    {
        $content = $this->stubs->render($name, $replacements);

        if ($content !== '') {
            return $content;
        }

        return '<?php // Stub for '.$name;
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

    private function dashboardView(ModuleName $module): string
    {
        $moduleNameLower = $module->lower();

        return <<<EOT
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
    }
}
