<?php

namespace VendorName\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MakeModuleCommandTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'TestModule';
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
    public function it_can_create_a_basic_module()
    {
        // Execute the command
        $this->artisan('make:module', ['name' => $this->testModuleName])
            ->expectsOutput("Module [{$this->testModuleName}] created successfully.")
            ->assertExitCode(0);

        // Check that the module directory was created
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName));

        // Check for required files
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Models/' . $this->testModuleName . '.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Services/' . $this->testModuleName . 'Service.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Repositories/' . $this->testModuleName . 'Repository.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $this->testModuleName . 'Controller.php'));
    }

    /** @test */
    public function it_can_create_a_module_with_api_option()
    {
        // Execute the command with --api flag
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--api' => true
        ])->assertExitCode(0);

        // Check that API controller was created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/API/' . $this->testModuleName . 'Controller.php'));

        // Check that API routes file was created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Routes/api.php'));

        // Check the content of the API routes file
        $routesContent = $this->files->get($this->modulesPath . '/' . $this->testModuleName . '/Routes/api.php');
        $this->assertStringContainsString('apiResource', $routesContent);
    }

    /** @test */
    public function it_can_create_a_module_with_views_option()
    {
        // Execute the command with --with-views flag
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--with-views' => true
        ])->assertExitCode(0);

        // Check that view files were created
        $viewsPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/views/' . strtolower($this->testModuleName);
        $this->assertTrue($this->files->isDirectory($viewsPath));
        $this->assertTrue($this->files->isFile($viewsPath . '/index.blade.php'));
        $this->assertTrue($this->files->isFile($viewsPath . '/show.blade.php'));
        $this->assertTrue($this->files->isFile($viewsPath . '/create.blade.php'));
        $this->assertTrue($this->files->isFile($viewsPath . '/edit.blade.php'));
    }

    /** @test */
    public function it_can_create_a_module_with_livewire_option()
    {
        // Execute the command with --with-livewire flag
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--with-livewire' => true
        ])->assertExitCode(0);

        // Check that Livewire components were created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $this->testModuleName . 'Table.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $this->testModuleName . 'Form.php'));

        // Check that Livewire views were created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Resources/views/livewire/' . strtolower(str_replace('_', '-', $this->testModuleName)) . '-table.blade.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Resources/views/livewire/' . strtolower(str_replace('_', '-', $this->testModuleName)) . '-form.blade.php'));
    }

    /** @test */
    public function it_respects_the_force_option_when_module_exists()
    {
        // First create a module
        $this->artisan('make:module', ['name' => $this->testModuleName])
            ->assertExitCode(0);

        // Add a custom file to check if it gets removed with --force
        $this->files->put(
            $this->modulesPath . '/' . $this->testModuleName . '/custom_file.txt',
            'This is a custom file'
        );

        // Now try to create it again without --force, should ask for confirmation
        $this->artisan('make:module', ['name' => $this->testModuleName])
            ->expectsQuestion("Module [{$this->testModuleName}] already exists. Do you want to overwrite it?", false)
            ->expectsOutput('Module creation aborted!')
            ->assertExitCode(1);

        // Custom file should still exist
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/custom_file.txt'));

        // Now recreate with --force
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--force' => true
        ])->assertExitCode(0);

        // The module should be recreated and the custom file should be gone
        $this->assertFalse($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/custom_file.txt'));
    }

    /** @test */
    public function it_creates_repository_interface_and_implementation()
    {
        // Execute the command
        $this->artisan('make:module', ['name' => $this->testModuleName])
            ->assertExitCode(0);

        // Check that repository files were created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Repositories/Interfaces/' . $this->testModuleName . 'RepositoryInterface.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Repositories/' . $this->testModuleName . 'Repository.php'));

        // Check content of repository interface
        $interfaceContent = $this->files->get($this->modulesPath . '/' . $this->testModuleName . '/Repositories/Interfaces/' . $this->testModuleName . 'RepositoryInterface.php');
        $this->assertStringContainsString('interface ' . $this->testModuleName . 'RepositoryInterface', $interfaceContent);
        $this->assertStringContainsString('public function getAll();', $interfaceContent);
        $this->assertStringContainsString('public function findById($id);', $interfaceContent);
        $this->assertStringContainsString('public function create(array $data);', $interfaceContent);
        $this->assertStringContainsString('public function update($id, array $data);', $interfaceContent);
        $this->assertStringContainsString('public function delete($id);', $interfaceContent);

        // Check content of repository implementation
        $repoContent = $this->files->get($this->modulesPath . '/' . $this->testModuleName . '/Repositories/' . $this->testModuleName . 'Repository.php');
        $this->assertStringContainsString('class ' . $this->testModuleName . 'Repository implements ' . $this->testModuleName . 'RepositoryInterface', $repoContent);
    }

    /** @test */
    public function it_creates_service_interface_and_implementation()
    {
        // Execute the command
        $this->artisan('make:module', ['name' => $this->testModuleName])
            ->assertExitCode(0);

        // Check that service files were created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Services/Interfaces/' . $this->testModuleName . 'ServiceInterface.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Services/' . $this->testModuleName . 'Service.php'));

        // Check content of service interface
        $interfaceContent = $this->files->get($this->modulesPath . '/' . $this->testModuleName . '/Services/Interfaces/' . $this->testModuleName . 'ServiceInterface.php');
        $this->assertStringContainsString('interface ' . $this->testModuleName . 'ServiceInterface', $interfaceContent);

        // Check content of service implementation
        $serviceContent = $this->files->get($this->modulesPath . '/' . $this->testModuleName . '/Services/' . $this->testModuleName . 'Service.php');
        $this->assertStringContainsString('class ' . $this->testModuleName . 'Service implements ' . $this->testModuleName . 'ServiceInterface', $serviceContent);
        $this->assertStringContainsString($this->testModuleName . 'RepositoryInterface', $serviceContent);
    }

    /** @test */
    public function it_creates_resource_specific_files_with_resource_option()
    {
        $resourceName = 'Product';

        // Execute the command with --resource option
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--resource' => $resourceName
        ])->assertExitCode(0);

        // Check that resource-specific files were created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Models/' . $resourceName . '.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Repositories/' . $resourceName . 'Repository.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Repositories/Interfaces/' . $resourceName . 'RepositoryInterface.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Services/' . $resourceName . 'Service.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Services/Interfaces/' . $resourceName . 'ServiceInterface.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $resourceName . 'Controller.php'));
    }

    /** @test */
    public function it_creates_crud_operations_with_crud_option()
    {
        // Execute the command with --with-crud flag
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-crud' => true
        ])->assertExitCode(0);

        // Check controller content for CRUD methods
        $controllerPath = $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $this->testModuleName . 'Controller.php';
        $controllerContent = $this->files->get($controllerPath);

        // Verify CRUD methods
        $this->assertStringContainsString('public function index()', $controllerContent);
        $this->assertStringContainsString('public function create()', $controllerContent);
        $this->assertStringContainsString('public function store(', $controllerContent);
        $this->assertStringContainsString('public function show(', $controllerContent);
        $this->assertStringContainsString('public function edit(', $controllerContent);
        $this->assertStringContainsString('public function update(', $controllerContent);
        $this->assertStringContainsString('public function destroy(', $controllerContent);

        // Check service implementations for CRUD methods
        $servicePath = $this->modulesPath . '/' . $this->testModuleName . '/Services/' . $this->testModuleName . 'Service.php';
        $serviceContent = $this->files->get($servicePath);

        $this->assertStringContainsString('public function getAll()', $serviceContent);
        $this->assertStringContainsString('public function findById(', $serviceContent);
        $this->assertStringContainsString('public function create(', $serviceContent);
        $this->assertStringContainsString('public function update(', $serviceContent);
        $this->assertStringContainsString('public function delete(', $serviceContent);
    }

    /** @test */
    public function it_creates_only_livewire_components_with_livewire_only_option()
    {
        // Execute the command with --with-livewire-only flag
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-livewire-only' => true
        ])->assertExitCode(0);

        // Check that Livewire components were created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $this->testModuleName . 'Table.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/Livewire/' . $this->testModuleName . 'Form.php'));

        // Check that Livewire views were created
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/resources/views/livewire/' . strtolower(str_replace('_', '-', $this->testModuleName)) . '-table.blade.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath . '/' . $this->testModuleName . '/resources/views/livewire/' . strtolower(str_replace('_', '-', $this->testModuleName)) . '-form.blade.php'));

        // Verify that no regular controllers were created when using livewire-only
        $controllerPath = $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/' . $this->testModuleName . 'Controller.php';
        $this->assertFalse($this->files->isFile($controllerPath));
    }

    /** @test */
    public function it_uses_correct_command_name()
    {
        // Test that both command names work (for backward compatibility)
        $this->artisan('make:module', ['name' => $this->testModuleName])
            ->assertExitCode(0);

        // Clean up
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        // Try with the other command name
        $this->artisan('module:make', ['name' => $this->testModuleName])
            ->assertExitCode(0);

        // Check that the module was created
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName));
    }
}
