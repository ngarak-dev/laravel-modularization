<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Finder\Finder;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModuleIntegrationTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'TestModule';
    protected $testModuleNameLower = 'testmodule';
    protected $modulesPath;

    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Set up environment configuration
        $app['config']->set('modularization.namespace', 'Modules');
        $app['config']->set('modularization.modules_path', 'modules');
        $app['config']->set('modularization.auto_discover', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->modulesPath = base_path('modules');

        // Make sure we have a clean test environment
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        // Ensure modules directory exists
        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        // Register the make:module command
        $this->app->singleton('command.module.make', function ($app) {
            return new \NgarakDev\Modularization\Console\Commands\MakeModuleCommand($app['files']);
        });

        $this->app->make('command.module.make');
    }

    protected function tearDown(): void
    {
        // Clean up the test module
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_create_and_register_a_module()
    {
        // Create a test module
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true
        ])->assertExitCode(0);

        // Load the service provider for the test module
        $provider = "Modules\\{$this->testModuleName}\\Providers\\{$this->testModuleName}ServiceProvider";

        // Manually register the module's service provider for testing
        $this->app->register($provider);

        // Check that repository and service bindings work
        $repositoryInterface = "Modules\\{$this->testModuleName}\\Repositories\\Interfaces\\{$this->testModuleName}RepositoryInterface";
        $repository = "Modules\\{$this->testModuleName}\\Repositories\\{$this->testModuleName}Repository";

        $this->assertTrue($this->app->bound($repositoryInterface));
        $this->assertInstanceOf($repository, $this->app->make($repositoryInterface));

        $serviceInterface = "Modules\\{$this->testModuleName}\\Services\\Interfaces\\{$this->testModuleName}ServiceInterface";
        $service = "Modules\\{$this->testModuleName}\\Services\\{$this->testModuleName}Service";

        $this->assertTrue($this->app->bound($serviceInterface));
        $this->assertInstanceOf($service, $this->app->make($serviceInterface));
    }

    /** @test */
    public function it_can_load_module_views()
    {
        // Create a test module with views
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true
        ])->assertExitCode(0);

        // Register the module's service provider
        $provider = "Modules\\{$this->testModuleName}\\Providers\\{$this->testModuleName}ServiceProvider";
        $this->app->register($provider);

        // Create a service provider that loads views
        $viewsServiceProvider = new class($this->app) extends \Illuminate\Support\ServiceProvider {
            public function boot()
            {
                $this->loadViewsFrom(base_path('modules/TestModule/resources/views'), 'testmodule');
            }
        };

        // Register the views service provider
        $this->app->register(get_class($viewsServiceProvider));

        // Check if the view exists
        $this->assertTrue(View::exists($this->testModuleNameLower . '::' . $this->testModuleNameLower . '.index'));
    }

    /** @test */
    public function it_can_load_module_routes()
    {
        // Create a test module
        $this->artisan('module:make', [
            'name' => $this->testModuleName
        ])->assertExitCode(0);

        // Register the module's service provider
        $provider = "Modules\\{$this->testModuleName}\\Providers\\{$this->testModuleName}ServiceProvider";
        $this->app->register($provider);

        // Create a service provider that loads routes
        $routesServiceProvider = new class($this->app) extends \Illuminate\Support\ServiceProvider {
            public function boot()
            {
                $this->loadRoutesFrom(base_path('modules/TestModule/routes/web.php'));
            }
        };

        // Register the routes service provider
        $this->app->register(get_class($routesServiceProvider));

        // Refresh routes
        $this->refreshApplication();

        // Check if the route exists
        $this->assertTrue(Route::has($this->testModuleNameLower . '.index'));
    }

    /** @test */
    public function it_can_use_repository_pattern()
    {
        // Create a test module
        $this->artisan('module:make', [
            'name' => $this->testModuleName
        ])->assertExitCode(0);

        // Register the service provider
        $provider = "Modules\\{$this->testModuleName}\\Providers\\{$this->testModuleName}ServiceProvider";
        $this->app->register($provider);

        // Get repository interface
        $repositoryInterface = "Modules\\{$this->testModuleName}\\Repositories\\Interfaces\\{$this->testModuleName}RepositoryInterface";

        // Mock the model that would be used by the repository
        $modelMock = $this->createMock("Modules\\{$this->testModuleName}\\Models\\{$this->testModuleName}");

        // Replace real model with mock in the container
        $this->app->instance("Modules\\{$this->testModuleName}\\Models\\{$this->testModuleName}", $modelMock);

        // Get repository instance from container
        $repository = $this->app->make($repositoryInterface);

        // Basic assertion to check if we got the repository
        $this->assertNotNull($repository);
    }

    /** @test */
    public function it_can_use_service_layer()
    {
        // Create a test module
        $this->artisan('module:make', [
            'name' => $this->testModuleName
        ])->assertExitCode(0);

        // Register the service provider
        $provider = "Modules\\{$this->testModuleName}\\Providers\\{$this->testModuleName}ServiceProvider";
        $this->app->register($provider);

        // Get service and repository interfaces
        $serviceInterface = "Modules\\{$this->testModuleName}\\Services\\Interfaces\\{$this->testModuleName}ServiceInterface";
        $repositoryInterface = "Modules\\{$this->testModuleName}\\Repositories\\Interfaces\\{$this->testModuleName}RepositoryInterface";

        // Create mock repository
        $repositoryMock = $this->createMock($repositoryInterface);

        // Replace real repository with mock in the container
        $this->app->instance($repositoryInterface, $repositoryMock);

        // Get service instance from container
        $service = $this->app->make($serviceInterface);

        // Basic assertion to check if we got the service
        $this->assertNotNull($service);
    }

    /** @test */
    public function it_creates_module_structure_compatible_with_laravel_conventions()
    {
        // Create a test module
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
            '--with-livewire' => true,
            '--api' => true
        ])->assertExitCode(0);

        // Check if standard Laravel directories and files exist
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/Http'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/Http/Requests'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/Models'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/database/migrations'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/resources/views'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/routes'));

        // Check that controllers follow Laravel conventions
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $this->testModuleName . 'Controller.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/API/' . $this->testModuleName . 'Controller.php'));

        // Check that livewire components follow conventions
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName . '/Livewire'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $this->testModuleName . 'Table.php'));
    }
}
