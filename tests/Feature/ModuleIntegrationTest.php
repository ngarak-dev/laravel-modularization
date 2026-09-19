<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
        $app['config']->set('modularization.namespace', 'Modules');
        $app['config']->set('modularization.modules_path', 'modules');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = base_path('modules');

        // Make sure we have a clean test environment
        if ($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath.'/'.$this->testModuleName);
        }

        // Ensure modules directory exists
        if (! $this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        // Register the make:module command
        $this->app->singleton('command.module.make', function ($app) {
            return new MakeModuleCommand($app['files']);
        });

        $this->app->make('command.module.make');
    }

    protected function tearDown(): void
    {
        // Clean up the test module
        if ($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath.'/'.$this->testModuleName);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_and_register_a_module()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
        ])->assertExitCode(0);

        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        // Verify key structural files were created
        $this->assertTrue($this->files->isDirectory($modulePath));
        $this->assertTrue($this->files->isFile($modulePath . '/Providers/' . $this->testModuleName . 'ServiceProvider.php'), 'ServiceProvider.php missing');
        $this->assertTrue($this->files->isFile($modulePath . '/Routes/web.php'), 'web.php missing');
        $this->assertTrue($this->files->isFile($modulePath . '/Routes/api.php'), 'api.php missing');

        // Verify ModularizationService can discover the newly created module
        /** @var ModularizationService $service */
        $service = $this->app->make(ModularizationService::class);
        $service->refresh();

        $module = $service->find($this->testModuleName);
        $this->assertInstanceOf(Module::class, $module);
        $this->assertTrue($module->isEnabled());
    }

    #[Test]
    public function it_can_load_module_views()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
        ])->assertExitCode(0);

        $viewsPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/views';
        $this->assertTrue($this->files->isDirectory($viewsPath));

        // Create a service provider that loads views
        $viewsServiceProvider = new class($this->app) extends ServiceProvider
        {
            public function boot()
            {
                $this->loadViewsFrom(base_path('modules/TestModule/Resources/views'), 'testmodule');
            }
        };

        // Register the views service provider
        $this->app->register(get_class($viewsServiceProvider));

        // Check if the view exists
        $this->assertTrue(View::exists($this->testModuleNameLower.'::'.$this->testModuleNameLower.'.index'));
    }

    #[Test]
    public function it_can_load_module_routes()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
        ])->assertExitCode(0);

        $routesFile = $this->modulesPath . '/' . $this->testModuleName . '/Routes/web.php';
        $this->assertTrue($this->files->isFile($routesFile), 'web.php not found');

        // Create a service provider that loads routes
        $routesServiceProvider = new class($this->app) extends ServiceProvider
        {
            public function boot()
            {
                $this->loadRoutesFrom(base_path('modules/TestModule/Routes/web.php'));
            }
        };

        // Register the routes service provider
        $this->app->register(get_class($routesServiceProvider));

        // Refresh routes
        $this->refreshApplication();

        // Check if the route exists
        $this->assertTrue(Route::has($this->testModuleNameLower.'.index'));
    }

    #[Test]
    public function it_can_use_repository_pattern()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
        ])->assertExitCode(0);

        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        $this->assertTrue($this->files->isFile($modulePath . '/Repositories/' . $this->testModuleName . 'Repository.php'));
        $this->assertTrue($this->files->isFile($modulePath . '/Repositories/Interfaces/' . $this->testModuleName . 'RepositoryInterface.php'));

        $interfaceContent = $this->files->get($modulePath . '/Repositories/Interfaces/' . $this->testModuleName . 'RepositoryInterface.php');
        $this->assertStringContainsString('interface ' . $this->testModuleName . 'RepositoryInterface', $interfaceContent);

        $repoContent = $this->files->get($modulePath . '/Repositories/' . $this->testModuleName . 'Repository.php');
        $this->assertStringContainsString('class ' . $this->testModuleName . 'Repository', $repoContent);
        $this->assertStringContainsString('implements ' . $this->testModuleName . 'RepositoryInterface', $repoContent);
    }

    #[Test]
    public function it_can_use_service_layer()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
        ])->assertExitCode(0);

        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        $this->assertTrue($this->files->isFile($modulePath . '/Services/' . $this->testModuleName . 'Service.php'));
        $this->assertTrue($this->files->isFile($modulePath . '/Services/Interfaces/' . $this->testModuleName . 'ServiceInterface.php'));

        $serviceContent = $this->files->get($modulePath . '/Services/' . $this->testModuleName . 'Service.php');
        $this->assertStringContainsString('class ' . $this->testModuleName . 'Service', $serviceContent);
        $this->assertStringContainsString('implements ' . $this->testModuleName . 'ServiceInterface', $serviceContent);
        $this->assertStringContainsString($this->testModuleName . 'RepositoryInterface', $serviceContent);
    }

    #[Test]
    public function it_creates_module_structure_compatible_with_laravel_conventions()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
            '--with-livewire' => true,
            '--api' => true,
        ])->assertExitCode(0);

        // Check if standard Laravel directories and files exist
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Http'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Http/Controllers'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Http/Requests'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Models'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Database/Migrations'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Resources/views'));
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Routes'));

        // Check that controllers follow Laravel conventions
        $this->assertTrue($this->files->isFile($this->modulesPath.'/'.$this->testModuleName.'/Http/Controllers/'.$this->testModuleName.'Controller.php'));
        $this->assertTrue($this->files->isFile($this->modulesPath.'/'.$this->testModuleName.'/Http/Controllers/API/'.$this->testModuleName.'Controller.php'));

        // Check that livewire components follow conventions
        $this->assertTrue($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName.'/Livewire'));
        $this->assertTrue($this->files->isFile($this->modulesPath.'/'.$this->testModuleName.'/Livewire/'.$this->testModuleName.'Table.php'));
    }
}
