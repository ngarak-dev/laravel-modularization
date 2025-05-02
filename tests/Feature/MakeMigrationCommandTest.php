<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Mockery;

class MakeMigrationCommandTest extends TestCase
{
    protected $testModuleName = 'TestModule';
    protected $modulesPath;
    protected $files;

    protected function getPackageProviders($app)
    {
        return ['NgarakDev\Modularization\Providers\ModularizationServiceProvider'];
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->modulesPath = base_path('modules');
        $this->files = app('files');

        // Create a test module
        $this->createTestModule();
    }

    public function tearDown(): void
    {
        // Cleanup the test module
        if (File::isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            File::deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        Mockery::close();
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

        // Set up mocking for the Artisan call inside the command
        $this->instance(
            'command.make:migration',
            Mockery::mock('Illuminate\Foundation\Console\MigrateMakeCommand')
                ->shouldReceive('handle')
                ->andReturn(0)
                ->getMock()
        );

        $this->artisan('module:make-migration', [
            'name' => 'create_test_table',
            'module' => $this->testModuleName
        ]);

        $this->assertTrue(File::isDirectory($migrationsPath), 'Migrations directory was not created.');
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
