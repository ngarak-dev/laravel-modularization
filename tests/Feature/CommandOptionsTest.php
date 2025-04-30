<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class CommandOptionsTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'OptionsTest';
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
    public function it_can_combine_api_and_resource_options()
    {
        $resourceName = 'Product';

        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resourceName,
            '--api' => true
        ])->assertExitCode(0);

        // Verify that resource-specific API controller was created
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/API/' . $resourceName . 'Controller.php'
        ));

        // Verify API routes contain the resource
        $apiRoutesPath = $this->modulesPath . '/' . $this->testModuleName . '/Routes/api.php';
        $routesContent = $this->files->get($apiRoutesPath);

        $this->assertStringContainsString($resourceName . 'Controller', $routesContent);
    }

    /** @test */
    public function it_can_combine_resource_and_crud_options()
    {
        $resourceName = 'Product';

        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resourceName,
            '--with-crud' => true
        ])->assertExitCode(0);

        // Verify CRUD operations in resource-specific controller
        $controllerPath = $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $resourceName . 'Controller.php';
        $this->assertTrue($this->files->isFile($controllerPath));

        $controllerContent = $this->files->get($controllerPath);
        $this->assertStringContainsString('public function index()', $controllerContent);
        $this->assertStringContainsString('public function create()', $controllerContent);
        $this->assertStringContainsString('public function store(', $controllerContent);
        $this->assertStringContainsString('public function show(', $controllerContent);
        $this->assertStringContainsString('public function edit(', $controllerContent);
        $this->assertStringContainsString('public function update(', $controllerContent);
        $this->assertStringContainsString('public function destroy(', $controllerContent);
    }

    /** @test */
    public function it_can_combine_resource_and_views_options()
    {
        $resourceName = 'Product';

        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resourceName,
            '--with-views' => true
        ])->assertExitCode(0);

        // Verify resource-specific views were created
        $resourceViews = $this->modulesPath . '/' . $this->testModuleName . '/Resources/views/' . strtolower($resourceName);

        $this->assertTrue($this->files->isDirectory($resourceViews));
        $this->assertTrue($this->files->isFile($resourceViews . '/index.blade.php'));
        $this->assertTrue($this->files->isFile($resourceViews . '/show.blade.php'));
        $this->assertTrue($this->files->isFile($resourceViews . '/create.blade.php'));
        $this->assertTrue($this->files->isFile($resourceViews . '/edit.blade.php'));
    }

    /** @test */
    public function it_can_combine_resource_and_livewire_options()
    {
        $resourceName = 'Product';

        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--resource' => $resourceName,
            '--with-livewire' => true
        ])->assertExitCode(0);

        // Verify resource-specific Livewire components were created
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $resourceName . 'Table.php'
        ));
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $resourceName . 'Form.php'
        ));

        // Verify Livewire views were created
        $kebabResource = strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($resourceName)));
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/resources/views/livewire/' . $kebabResource . '-table.blade.php'
        ));
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/resources/views/livewire/' . $kebabResource . '-form.blade.php'
        ));
    }

    /** @test */
    public function it_can_combine_livewire_only_and_resource_options()
    {
        $resourceName = 'Product';

        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--resource' => $resourceName,
            '--with-livewire-only' => true
        ])->assertExitCode(0);

        // Verify resource-specific Livewire components were created
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $resourceName . 'Table.php'
        ));

        // Verify controller was NOT created (livewire-only)
        $this->assertFalse($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $resourceName . 'Controller.php'
        ));
    }

    /** @test */
    public function it_handles_complex_option_combinations()
    {
        $resourceName = 'Product';

        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--resource' => $resourceName,
            '--api' => true,
            '--with-crud' => true,
            '--with-views' => true
        ])->assertExitCode(0);

        // Verify all expected files exist
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $resourceName . 'Controller.php'
        ));
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/API/' . $resourceName . 'Controller.php'
        ));
        $this->assertTrue($this->files->isDirectory(
            $this->modulesPath . '/' . $this->testModuleName . '/resources/views/' . strtolower($resourceName)
        ));
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Services/' . $resourceName . 'Service.php'
        ));
    }

    /** @test */
    public function livewire_only_option_overrides_standard_view_generation()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
            '--with-livewire-only' => true
        ])->assertExitCode(0);

        // Verify that controllers were NOT created (due to livewire-only)
        $this->assertFalse($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $this->testModuleName . 'Controller.php'
        ));

        // Verify that Livewire components were created
        $this->assertTrue($this->files->isFile(
            $this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $this->testModuleName . 'Table.php'
        ));

        // Verify that regular blade views weren't created
        $this->assertFalse($this->files->isDirectory(
            $this->modulesPath . '/' . $this->testModuleName . '/resources/views/' . strtolower($this->testModuleName)
        ));
    }
}
