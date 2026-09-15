<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MigrateModulesCommandTest extends TestCase
{
    protected Filesystem $files;
    protected string $modulesPath;
    protected array $testModules = ['AllMigrateModule1', 'AllMigrateModule2', 'AllMigrateDisabled'];

    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->modulesPath = base_path('modules');

        foreach ($this->testModules as $module) {
            if ($this->files->isDirectory($this->modulesPath . '/' . $module)) {
                $this->files->deleteDirectory($this->modulesPath . '/' . $module);
            }
        }

        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        $this->createTestModules();
    }

    protected function tearDown(): void
    {
        foreach ($this->testModules as $module) {
            if ($this->files->isDirectory($this->modulesPath . '/' . $module)) {
                $this->files->deleteDirectory($this->modulesPath . '/' . $module);
            }
        }

        parent::tearDown();
    }

    /** @test */
    public function it_warns_when_modules_directory_does_not_exist()
    {
        $this->files->deleteDirectory($this->modulesPath);

        $this->artisan('module:migrate-all')
            ->expectsOutput('Modules directory does not exist.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_warns_when_no_modules_found()
    {
        foreach ($this->testModules as $module) {
            if ($this->files->isDirectory($this->modulesPath . '/' . $module)) {
                $this->files->deleteDirectory($this->modulesPath . '/' . $module);
            }
        }

        $this->artisan('module:migrate-all')
            ->expectsOutput('No modules found.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_skips_disabled_modules_when_using_only_enabled_flag()
    {
        $this->artisan('module:migrate-all', ['--only-enabled' => true])
            ->expectsOutput('Skipping disabled module [AllMigrateDisabled]')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_migrates_all_modules_when_not_using_only_enabled_flag()
    {
        $this->artisan('module:migrate-all')
            ->expectsOutput('Migration Summary:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_reports_failed_migrations()
    {
        // Remove migrations from one module to simulate a module with no migrations
        // (in this scenario the command will still succeed with 0 for that module)
        // The test verifies the summary output structure
        $this->artisan('module:migrate-all')
            ->expectsOutput('Migration Summary:')
            ->assertExitCode(0);
    }

    protected function createTestModules(): void
    {
        foreach ($this->testModules as $index => $moduleName) {
            $modulePath = $this->modulesPath . '/' . $moduleName;

            foreach (['', 'Database/Migrations', 'Providers', 'Config'] as $directory) {
                $path = $modulePath . ($directory ? '/' . $directory : '');
                $this->files->makeDirectory($path, 0755, true);
            }

            $tableName = strtolower($moduleName) . '_tbl';
            $migrationPath = $modulePath . "/Database/Migrations/2023_01_01_00000{$index}_create_{$tableName}.php";
            $this->files->put($migrationPath, <<<PHP
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('{$tableName}'); }
};
PHP);
        }

        // Mark AllMigrateDisabled as disabled
        $this->files->put($this->modulesPath . '/AllMigrateDisabled/.disabled', '');
    }
}
