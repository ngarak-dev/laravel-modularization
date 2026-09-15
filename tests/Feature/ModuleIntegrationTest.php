<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModuleIntegrationTest extends TestCase
{
    protected Filesystem $files;
    protected string $testModuleName = 'IntegrationTestModule';
    protected string $modulesPath;

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

        $this->files = new Filesystem();
        $this->modulesPath = base_path('modules');

        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_create_and_register_a_module()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
            '--api' => true,
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

    /** @test */
    public function it_can_load_module_views()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
        ])->assertExitCode(0);

        $viewsPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/views';
        $this->assertTrue($this->files->isDirectory($viewsPath));

        // Manually load views for testing (service provider already ran at boot without this module)
        $this->app['view']->addNamespace(strtolower($this->testModuleName), $viewsPath);

        // Check views exist on disk
        $this->assertTrue($this->files->isDirectory($viewsPath . '/' . strtolower($this->testModuleName)));
        $this->assertTrue($this->files->isFile($viewsPath . '/' . strtolower($this->testModuleName) . '/index.blade.php'));
    }

    /** @test */
    public function it_can_load_module_routes()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
        ])->assertExitCode(0);

        $routesFile = $this->modulesPath . '/' . $this->testModuleName . '/Routes/web.php';
        $this->assertTrue($this->files->isFile($routesFile), 'web.php not found');

        $apiRoutesFile = $this->modulesPath . '/' . $this->testModuleName . '/Routes/api.php';
        $this->assertFalse($this->files->isFile($apiRoutesFile), 'api.php should NOT exist without --api flag');

        // Verify the routes file references the correct controller
        $routesContent = $this->files->get($routesFile);
        $this->assertStringContainsString($this->testModuleName . 'Controller', $routesContent);
        $this->assertStringContainsString('Route::', $routesContent);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
    public function it_creates_module_structure_compatible_with_laravel_conventions()
    {
        $this->artisan('module:make', [
            'name' => $this->testModuleName,
            '--with-views' => true,
            '--with-livewire' => true,
            '--api' => true,
        ])->assertExitCode(0);

        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        // PascalCase directory structure as generated
        $this->assertTrue($this->files->isDirectory($modulePath . '/Http'));
        $this->assertTrue($this->files->isDirectory($modulePath . '/Http/Controllers'));
        $this->assertTrue($this->files->isDirectory($modulePath . '/Http/Requests'));
        $this->assertTrue($this->files->isDirectory($modulePath . '/Models'));
        $this->assertTrue($this->files->isDirectory($modulePath . '/Database/Migrations'));
        $this->assertTrue($this->files->isDirectory($modulePath . '/Resources/views'));
        $this->assertTrue($this->files->isDirectory($modulePath . '/Routes'));

        // Check that controllers exist
        $this->assertTrue($this->files->isFile($modulePath . '/Http/Controllers/' . $this->testModuleName . 'Controller.php'));
        $this->assertTrue($this->files->isFile($modulePath . '/Http/Controllers/API/' . $this->testModuleName . 'Controller.php'));

        // Livewire components
        $this->assertTrue($this->files->isDirectory($modulePath . '/Livewire'));
        $this->assertTrue($this->files->isFile($modulePath . '/Livewire/' . $this->testModuleName . 'Table.php'));
    }
}
