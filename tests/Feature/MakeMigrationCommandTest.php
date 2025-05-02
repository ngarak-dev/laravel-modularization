<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Mockery;
use NgarakDev\Modularization\Console\Commands\MakeMigrationCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakeMigrationCommandTest extends TestCase
{
    protected $testModuleName = 'TestModule';
    protected $modulesPath;
    protected $files;
    protected $command;

    protected function getPackageProviders($app)
    {
        return ['NgarakDev\Modularization\Providers\ModularizationServiceProvider'];
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->modulesPath = base_path('modules');
        $this->files = app('files');
        $this->command = new MakeMigrationCommand();
        $this->command->setLaravel($this->app);

        // Create a test module
        $this->createTestModule();
    }

    public function tearDown(): void
    {
        // Cleanup the test module
        if (File::isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            File::deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_fails_when_module_does_not_exist()
    {
        $this->artisan('module:make-migration', [
            'name' => 'create_test_table',
            'module' => 'NonExistentModule'
        ])
            ->expectsOutput("Module [NonExistentModule] does not exist.")
            ->assertExitCode(1);
    }

    /** @test */
    public function it_creates_migration_directory_if_it_does_not_exist()
    {
        // Remove migrations directory if it exists
        $migrationsPath = $this->modulesPath . '/' . $this->testModuleName . '/Database/Migrations';
        if (File::isDirectory($migrationsPath)) {
            File::deleteDirectory($migrationsPath);
        }

        // We're not testing the actual Laravel migration creation, just the directory creation
        $this->app->shouldReceive('call')
            ->with('make:migration', \Mockery::any())
            ->andReturn(0);

        $this->artisan('module:make-migration', [
            'name' => 'create_test_table',
            'module' => $this->testModuleName
        ]);

        $this->assertTrue(File::isDirectory($migrationsPath), 'Migrations directory was not created.');
    }

    /** @test */
    public function it_creates_migration_with_default_path()
    {
        // Mock the Laravel make:migration command
        $this->app->expects($this->once())
            ->method('call')
            ->with('make:migration', $this->callback(function ($arg) {
                $expectedPath = str_replace(base_path() . '/', '', $this->modulesPath . '/' . $this->testModuleName . '/Database/Migrations');
                return $arg['name'] === 'create_test_table' && $arg['--path'] === $expectedPath;
            }))
            ->willReturn(0);

        $this->artisan('module:make-migration', [
            'name' => 'create_test_table',
            'module' => $this->testModuleName
        ])
            ->expectsOutput("Creating migration for module [{$this->testModuleName}]...")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_creates_migration_with_custom_path()
    {
        $customPath = 'Custom/Path';
        $fullCustomPath = $this->modulesPath . '/' . $this->testModuleName . '/' . $customPath;

        // Mock the Laravel make:migration command
        $this->app->expects($this->once())
            ->method('call')
            ->with('make:migration', $this->callback(function ($arg) use ($fullCustomPath) {
                $expectedPath = str_replace(base_path() . '/', '', $fullCustomPath);
                return $arg['name'] === 'create_test_table' && $arg['--path'] === $expectedPath;
            }))
            ->willReturn(0);

        $this->artisan('module:make-migration', [
            'name' => 'create_test_table',
            'module' => $this->testModuleName,
            '--path' => $customPath
        ])
            ->expectsOutput("Creating migration for module [{$this->testModuleName}]...")
            ->assertExitCode(0);

        $this->assertTrue(File::isDirectory($fullCustomPath), 'Custom migrations path was not created.');
    }

    /** @test */
    public function it_creates_migration_with_create_option()
    {
        // Mock the Laravel make:migration command
        $this->app->expects($this->once())
            ->method('call')
            ->with('make:migration', $this->callback(function ($arg) {
                return isset($arg['--create']) && $arg['--create'] === 'test_table';
            }))
            ->willReturn(0);

        $this->artisan('module:make-migration', [
            'name' => 'create_test_table',
            'module' => $this->testModuleName,
            '--create' => 'test_table'
        ])
            ->expectsOutput("Creating migration for module [{$this->testModuleName}]...")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_creates_migration_with_table_option()
    {
        // Mock the Laravel make:migration command
        $this->app->expects($this->once())
            ->method('call')
            ->with('make:migration', $this->callback(function ($arg) {
                return isset($arg['--table']) && $arg['--table'] === 'test_table';
            }))
            ->willReturn(0);

        $this->artisan('module:make-migration', [
            'name' => 'update_test_table',
            'module' => $this->testModuleName,
            '--table' => 'test_table'
        ])
            ->expectsOutput("Creating migration for module [{$this->testModuleName}]...")
            ->assertExitCode(0);
    }

    /**
     * Helper method to create a test module
     */
    protected function createTestModule()
    {
        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        // Create module directories
        $directories = [
            '',
            'Database',
            'Providers',
        ];

        foreach ($directories as $directory) {
            $path = $modulePath . ($directory ? '/' . $directory : '');
            $this->files->makeDirectory($path, 0755, true, true);
        }
    }
}
