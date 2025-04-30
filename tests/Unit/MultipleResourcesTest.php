<?php

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MultipleResourcesTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'MultiResource';
    protected $modulesPath;

    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
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

        // Register the command
        $this->app->singleton('command.module.make', function ($app) {
            return new MakeModuleCommand($app['files']);
        });
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
    public function it_can_create_module_with_multiple_resources()
    {
        $resources = 'Product,Category,Order';

        // Execute the command with multiple resources
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resources
        ])->assertExitCode(0);

        // Check for module directory
        $modulePath = $this->modulesPath . '/' . $this->testModuleName;
        $this->assertTrue($this->files->isDirectory($modulePath));

        // Check for each resource's files
        foreach (['Product', 'Category', 'Order'] as $resource) {
            // Models
            $this->assertTrue($this->files->isFile($modulePath . '/Models/' . $resource . '.php'));

            // Controllers
            $this->assertTrue($this->files->isFile($modulePath . '/Http/Controllers/' . $resource . 'Controller.php'));

            // Repositories
            $this->assertTrue($this->files->isFile($modulePath . '/Repositories/' . $resource . 'Repository.php'));
            $this->assertTrue($this->files->isFile($modulePath . '/Repositories/Interfaces/' . $resource . 'RepositoryInterface.php'));

            // Services
            $this->assertTrue($this->files->isFile($modulePath . '/Services/' . $resource . 'Service.php'));
            $this->assertTrue($this->files->isFile($modulePath . '/Services/Interfaces/' . $resource . 'ServiceInterface.php'));
        }
    }

    /** @test */
    public function it_can_create_resources_with_views()
    {
        $resources = 'Product,Category';

        // Execute the command with resources and views
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resources,
            '--with-views' => true
        ])->assertExitCode(0);

        // Check for module directory
        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        // Check for resource-specific view directories
        foreach (['product', 'category'] as $resourceLower) {
            $viewsPath = $modulePath . '/Resources/views/' . $resourceLower;
            $this->assertTrue($this->files->isDirectory($viewsPath));

            // Check for standard view files
            $this->assertTrue($this->files->isFile($viewsPath . '/index.blade.php'));
            $this->assertTrue($this->files->isFile($viewsPath . '/create.blade.php'));
            $this->assertTrue($this->files->isFile($viewsPath . '/edit.blade.php'));
            $this->assertTrue($this->files->isFile($viewsPath . '/show.blade.php'));
        }
    }

    /** @test */
    public function it_can_create_resources_with_api_controllers()
    {
        $resources = 'Product,Order';

        // Execute the command with resources and API flag
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resources,
            '--api' => true
        ])->assertExitCode(0);

        // Check for module directory
        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        // Check for API controllers for each resource
        foreach (['Product', 'Order'] as $resource) {
            $apiControllerPath = $modulePath . '/Http/Controllers/API/' . $resource . 'Controller.php';
            $this->assertTrue($this->files->isFile($apiControllerPath));

            // Check content of API controller
            $content = $this->files->get($apiControllerPath);
            $this->assertStringContainsString('public function index()', $content);
            $this->assertStringContainsString('public function store(', $content);
            $this->assertStringContainsString('public function show(', $content);
            $this->assertStringContainsString('public function update(', $content);
            $this->assertStringContainsString('public function destroy(', $content);
        }

        // Check API routes
        $apiRoutesPath = $modulePath . '/Routes/api.php';
        $this->assertTrue($this->files->isFile($apiRoutesPath));
    }

    /** @test */
    public function it_can_create_resources_with_crud_operations()
    {
        $resources = 'Product,Customer';

        // Execute the command with resources and CRUD flag
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resources,
            '--with-crud' => true
        ])->assertExitCode(0);

        // Check for module directory
        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        // Check for CRUD methods in controllers
        foreach (['Product', 'Customer'] as $resource) {
            $controllerPath = $modulePath . '/Http/Controllers/' . $resource . 'Controller.php';
            $this->assertTrue($this->files->isFile($controllerPath));

            // Check CRUD methods
            $content = $this->files->get($controllerPath);
            $this->assertStringContainsString('public function index()', $content);
            $this->assertStringContainsString('public function create()', $content);
            $this->assertStringContainsString('public function store(', $content);
            $this->assertStringContainsString('public function show(', $content);
            $this->assertStringContainsString('public function edit(', $content);
            $this->assertStringContainsString('public function update(', $content);
            $this->assertStringContainsString('public function destroy(', $content);
        }
    }
}
