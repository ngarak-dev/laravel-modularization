<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MakeModuleManagerCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MakeModuleManagerCommandTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'ModuleManagerTest';
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
        $this->app->singleton('command.module.make-manager', function ($app) {
            return new MakeModuleManagerCommand($app['files']);
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
    public function it_can_generate_module_manager_scaffolding()
    {
        // Execute the command
        $this->artisan('module:make-manager', [
            'name' => $this->testModuleName
        ])
            ->expectsOutput("Module Manager [{$this->testModuleName}] created successfully")
            ->assertExitCode(0);

        // Check that controllers were created
        $controllersPath = $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers';
        $this->assertTrue($this->files->isDirectory($controllersPath));
        $this->assertTrue($this->files->exists($controllersPath . '/ModuleManagerController.php'));

        // Check that views were created
        $viewsPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/views';
        $this->assertTrue($this->files->isDirectory($viewsPath));

        // Check for dashboard view
        $dashboardPath = $viewsPath . '/dashboard';
        $this->assertTrue($this->files->isDirectory($dashboardPath));
        $this->assertTrue($this->files->exists($dashboardPath . '/index.blade.php'));

        // Check for layout view
        $layoutsPath = $viewsPath . '/layouts';
        $this->assertTrue($this->files->isDirectory($layoutsPath));
        $this->assertTrue($this->files->exists($layoutsPath . '/master.blade.php'));

        // Check that routes were created
        $routesPath = $this->modulesPath . '/' . $this->testModuleName . '/Routes/web.php';
        $this->assertTrue($this->files->exists($routesPath));

        // Check web route contains expected routes
        $routeContent = $this->files->get($routesPath);
        $this->assertStringContainsString("Route::get('/', [ModuleManagerController::class, 'index'])", $routeContent);
        $this->assertStringContainsString("Route::post('/toggle', [ModuleManagerController::class, 'toggleModule'])", $routeContent);

        // Check that config was created
        $configPath = $this->modulesPath . '/' . $this->testModuleName . '/Config/config.php';
        $this->assertTrue($this->files->exists($configPath));

        // Check config contains expected structure
        $configContent = $this->files->get($configPath);
        $this->assertStringContainsString("'name' =>", $configContent);
        $this->assertStringContainsString("'description' =>", $configContent);
        $this->assertStringContainsString("'enabled' => true", $configContent);
        $this->assertStringContainsString("'routes' =>", $configContent);
        $this->assertStringContainsString("'menu' =>", $configContent);
    }

    /** @test */
    public function it_uses_default_name_when_no_name_provided()
    {
        // Execute the command without a name parameter
        $this->artisan('module:make-manager')
            ->expectsOutput("Module Manager [ModuleManager] created successfully")
            ->assertExitCode(0);

        // Check that the default ModuleManager module was created
        $this->assertTrue($this->files->isDirectory($this->modulesPath . '/ModuleManager'));
        $this->assertTrue($this->files->exists($this->modulesPath . '/ModuleManager/Providers/ModuleManagerServiceProvider.php'));

        // Clean up default module
        $this->files->deleteDirectory($this->modulesPath . '/ModuleManager');
    }

    /** @test */
    public function it_asks_for_confirmation_when_module_already_exists()
    {
        // Create the module first
        $this->files->makeDirectory($this->modulesPath . '/ExistingModule', 0755, true);

        // Execute the command with existing module and answer "no" to confirmation
        $this->artisan('module:make-manager', ['name' => 'ExistingModule'])
            ->expectsQuestion("Module [ExistingModule] already exists. Do you want to continue?", false)
            ->expectsOutput("Operation cancelled.")
            ->assertExitCode(1);

        // Clean up
        $this->files->deleteDirectory($this->modulesPath . '/ExistingModule');
    }

    /** @test */
    public function it_creates_config_file_with_proper_structure()
    {
        // Execute the command
        $this->artisan('module:make-manager', [
            'name' => $this->testModuleName
        ])->run();

        // Check config file
        $configPath = $this->modulesPath . '/' . $this->testModuleName . '/Config/config.php';
        $this->assertTrue($this->files->exists($configPath));

        // Include the config file to check its structure
        $config = include $configPath;

        // Verify config structure
        $this->assertIsArray($config);
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('description', $config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('routes', $config);
        $this->assertArrayHasKey('menu', $config);

        // Check nested structure
        $this->assertIsArray($config['routes']);
        $this->assertArrayHasKey('prefix', $config['routes']);
        $this->assertArrayHasKey('middleware', $config['routes']);

        $this->assertIsArray($config['menu']);
        $this->assertArrayHasKey('title', $config['menu']);
        $this->assertArrayHasKey('icon', $config['menu']);
    }
}
