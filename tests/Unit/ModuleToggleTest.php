<?php

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\ModuleToggleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModuleToggleTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'TestToggleModule';
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

        // Ensure modules directory exists
        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        // Register the command
        $this->app->singleton('command.module.make', function ($app) {
            return new MakeModuleCommand($app['files']);
        });

        $this->app->singleton('command.module.toggle', function ($app) {
            return new ModuleToggleCommand($app['files']);
        });

        // Create a test module
        $this->artisan('make:module', ['name' => $this->testModuleName])->run();
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
    public function it_can_disable_a_module()
    {
        // Execute the toggle command with disable option
        $this->artisan('module:toggle', [
            'name' => $this->testModuleName,
            '--disable' => true
        ])
            ->expectsOutput("Module [{$this->testModuleName}] has been disabled.")
            ->assertExitCode(0);

        // Check that the disabled file was created
        $disabledPath = $this->modulesPath . '/' . $this->testModuleName . '/.disabled';
        $this->assertTrue($this->files->exists($disabledPath));

        // Check that the file contains proper JSON data
        $disabledContent = json_decode($this->files->get($disabledPath), true);
        $this->assertArrayHasKey('disabled_at', $disabledContent);
        $this->assertArrayHasKey('disabled_by', $disabledContent);
    }

    /** @test */
    public function it_can_enable_a_disabled_module()
    {
        // First, disable the module
        $disabledPath = $this->modulesPath . '/' . $this->testModuleName . '/.disabled';
        $this->files->put($disabledPath, json_encode([
            'disabled_at' => now()->toDateTimeString(),
            'disabled_by' => 'test'
        ]));

        // Now enable it
        $this->artisan('module:toggle', [
            'name' => $this->testModuleName
        ])
            ->expectsOutput("Module [{$this->testModuleName}] has been enabled.")
            ->assertExitCode(0);

        // Check that the disabled file was removed
        $this->assertFalse($this->files->exists($disabledPath));
    }

    /** @test */
    public function it_shows_proper_message_when_module_is_already_disabled()
    {
        // First, disable the module
        $disabledPath = $this->modulesPath . '/' . $this->testModuleName . '/.disabled';
        $this->files->put($disabledPath, json_encode([
            'disabled_at' => now()->toDateTimeString(),
            'disabled_by' => 'test'
        ]));

        // Try to disable it again
        $this->artisan('module:toggle', [
            'name' => $this->testModuleName,
            '--disable' => true
        ])
            ->expectsOutput("Module [{$this->testModuleName}] is already disabled.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_proper_message_when_module_is_already_enabled()
    {
        // Module is enabled by default

        // Try to enable it again
        $this->artisan('module:toggle', [
            'name' => $this->testModuleName
        ])
            ->expectsOutput("Module [{$this->testModuleName}] is already enabled.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_returns_error_when_module_does_not_exist()
    {
        $nonExistentModule = 'NonExistentModule';

        $this->artisan('module:toggle', [
            'name' => $nonExistentModule,
            '--disable' => true
        ])
            ->expectsOutput("Module [$nonExistentModule] does not exist!")
            ->assertExitCode(1);
    }
}
