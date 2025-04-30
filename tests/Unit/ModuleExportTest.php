<?php

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\ModuleExportCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModuleExportTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'ExportTest';
    protected $modulesPath;
    protected $exportPath;

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
        $this->exportPath = base_path('build/' . strtolower($this->testModuleName));

        // Make sure we have a clean test environment
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        if ($this->files->isDirectory($this->exportPath)) {
            $this->files->deleteDirectory($this->exportPath);
        }

        // Ensure modules directory exists
        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        // Register the commands
        $this->app->singleton('command.module.make', function ($app) {
            return new MakeModuleCommand($app['files']);
        });

        $this->app->singleton('command.module.export', function ($app) {
            return new ModuleExportCommand($app['files']);
        });

        // Create a test module first
        $this->artisan('make:module', [
            'name' => $this->testModuleName,
            '--api' => true,
            '--with-views' => true
        ])->run();
    }

    protected function tearDown(): void
    {
        // Clean up the test module
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        // Clean up export directory
        if ($this->files->isDirectory($this->exportPath)) {
            $this->files->deleteDirectory($this->exportPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_export_a_module()
    {
        $vendorName = 'NgarakDev';

        // Execute the command
        $this->artisan('module:export', [
            'module' => $this->testModuleName,
            '--vendor' => $vendorName,
            '--author' => 'Ngara K',
            '--email' => 'ngarakiringo@gmail.com'
        ])
            ->expectsOutput("Module [{$this->testModuleName}] exported successfully to: {$this->exportPath}")
            ->assertExitCode(0);

        // Check that the export directory was created
        $this->assertTrue($this->files->isDirectory($this->exportPath));

        // Check for core package files
        $this->assertTrue($this->files->exists($this->exportPath . '/composer.json'));
        $this->assertTrue($this->files->exists($this->exportPath . '/LICENSE'));
        $this->assertTrue($this->files->exists($this->exportPath . '/README.md'));

        // Check for the new service provider
        $this->assertTrue($this->files->exists($this->exportPath . '/src/' . $this->testModuleName . 'ServiceProvider.php'));

        // Check for src directory with module contents
        $this->assertTrue($this->files->isDirectory($this->exportPath . '/src'));
        $this->assertTrue($this->files->isDirectory($this->exportPath . '/src/Models'));
        $this->assertTrue($this->files->isDirectory($this->exportPath . '/src/Http/Controllers'));
        $this->assertTrue($this->files->isDirectory($this->exportPath . '/src/Repositories'));
        $this->assertTrue($this->files->isDirectory($this->exportPath . '/src/Services'));
    }

    /** @test */
    public function it_creates_correct_composer_json()
    {
        $vendorName = 'NgarakDev';
        $packageName = strtolower($vendorName) . '/' . strtolower($this->testModuleName);

        // Execute the command
        $this->artisan('module:export', [
            'module' => $this->testModuleName,
            '--vendor' => $vendorName
        ])->assertExitCode(0);

        // Check composer.json contents
        $composerJsonPath = $this->exportPath . '/composer.json';
        $this->assertTrue($this->files->exists($composerJsonPath));

        $composerJson = json_decode($this->files->get($composerJsonPath), true);

        $this->assertEquals($packageName, $composerJson['name']);
        $this->assertArrayHasKey('autoload', $composerJson);
        $this->assertArrayHasKey('psr-4', $composerJson['autoload']);
        $this->assertArrayHasKey($vendorName . '\\' . $this->testModuleName . '\\', $composerJson['autoload']['psr-4']);
        $this->assertEquals('src/', $composerJson['autoload']['psr-4'][$vendorName . '\\' . $this->testModuleName . '\\']);

        // Check for service provider registration
        $this->assertArrayHasKey('extra', $composerJson);
        $this->assertArrayHasKey('laravel', $composerJson['extra']);
        $this->assertArrayHasKey('providers', $composerJson['extra']['laravel']);
        $this->assertContains($vendorName . '\\' . $this->testModuleName . '\\' . $this->testModuleName . 'ServiceProvider', $composerJson['extra']['laravel']['providers']);
    }

    /** @test */
    public function it_rewrites_namespaces_in_exported_files()
    {
        $vendorName = 'NgarakDev';
        $oldNamespace = config('modularization.namespace', 'Modules') . '\\' . $this->testModuleName;
        $newNamespace = $vendorName . '\\' . $this->testModuleName;

        // Execute the command
        $this->artisan('module:export', [
            'module' => $this->testModuleName,
            '--vendor' => $vendorName
        ])->assertExitCode(0);

        // Check a few key files for namespace changes
        $modelPath = $this->exportPath . '/src/Models/' . $this->testModuleName . '.php';
        $this->assertTrue($this->files->exists($modelPath));

        $modelContent = $this->files->get($modelPath);
        $this->assertStringContainsString("namespace {$newNamespace}\\Models;", $modelContent);
        $this->assertStringNotContainsString("namespace {$oldNamespace}\\Models;", $modelContent);

        // Check controller
        $controllerPath = $this->exportPath . '/src/Http/Controllers/' . $this->testModuleName . 'Controller.php';
        $this->assertTrue($this->files->exists($controllerPath));

        $controllerContent = $this->files->get($controllerPath);
        $this->assertStringContainsString("namespace {$newNamespace}\\Http\\Controllers;", $controllerContent);
        $this->assertStringNotContainsString("namespace {$oldNamespace}\\Http\\Controllers;", $controllerContent);
    }

    /** @test */
    public function it_creates_proper_service_provider()
    {
        $vendorName = 'NgarakDev';

        // Execute the command
        $this->artisan('module:export', [
            'module' => $this->testModuleName,
            '--vendor' => $vendorName
        ])->assertExitCode(0);

        // Check service provider
        $providerPath = $this->exportPath . '/src/' . $this->testModuleName . 'ServiceProvider.php';
        $this->assertTrue($this->files->exists($providerPath));

        $providerContent = $this->files->get($providerPath);
        $this->assertStringContainsString("namespace {$vendorName}\\{$this->testModuleName};", $providerContent);
        $this->assertStringContainsString("class {$this->testModuleName}ServiceProvider extends ServiceProvider", $providerContent);

        // Check for auto-registration of routes
        $this->assertStringContainsString('$this->loadRoutesFrom(__DIR__ . \'/Routes/web.php\');', $providerContent);
        $this->assertStringContainsString('$this->loadRoutesFrom(__DIR__ . \'/Routes/api.php\');', $providerContent);

        // Check for views, translations, migrations loading
        $this->assertStringContainsString('$this->loadViewsFrom(', $providerContent);
        $this->assertStringContainsString('$this->loadTranslationsFrom(', $providerContent);
        $this->assertStringContainsString('$this->loadMigrationsFrom(', $providerContent);

        // Check for publish statements
        $this->assertStringContainsString('$this->publishes([', $providerContent);
    }
}
