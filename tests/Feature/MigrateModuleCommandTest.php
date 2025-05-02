<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MigrateModuleCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MigrateModuleCommandTest extends TestCase
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

        // Create test module with migrations directory
        $this->createTestModule();
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
    public function it_shows_error_when_module_does_not_exist()
    {
        $nonExistentModule = 'NonExistentModule';

        $this->artisan('module:migrate', ['name' => $nonExistentModule])
            ->expectsOutput("Module [{$nonExistentModule}] does not exist.")
            ->assertExitCode(1);
    }

    /** @test */
    public function it_shows_warning_when_no_migrations_found()
    {
        // Remove the migrations directory
        $migrationsPath = $this->modulesPath . '/' . $this->testModuleName . '/Database/Migrations';
        $this->files->deleteDirectory($migrationsPath);

        $this->artisan('module:migrate', ['name' => $this->testModuleName])
            ->expectsOutput("No migrations found for module [{$this->testModuleName}].")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_migrations_when_migrations_exist()
    {
        // Mocking the Artisan::call() result since we can't actually run migrations in these tests
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Migration output');

        $this->artisan('module:migrate', ['name' => $this->testModuleName])
            ->expectsOutput("Running migrations for module [{$this->testModuleName}]...")
            ->expectsOutput("Migration output")
            ->expectsOutput("Running migrations for module [{$this->testModuleName}] completed successfully.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_migrations_fresh_when_fresh_option_is_used()
    {
        // Mocking the Artisan::call() result
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Fresh migration output');

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--fresh' => true])
            ->expectsOutput("Refreshing database and re-running all migrations for module [{$this->testModuleName}]...")
            ->expectsOutput("Fresh migration output")
            ->expectsOutput("Refreshing database and re-running all migrations for module [{$this->testModuleName}] completed successfully.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_rolls_back_migrations_when_rollback_option_is_used()
    {
        // Mocking the Artisan::call() result
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:rollback', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Rollback output');

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--rollback' => true])
            ->expectsOutput("Rolling back migrations for module [{$this->testModuleName}]...")
            ->expectsOutput("Rollback output")
            ->expectsOutput("Rolling back migrations for module [{$this->testModuleName}] completed successfully.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_migration_status_when_status_option_is_used()
    {
        // Mocking the Artisan::call() result
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:status', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Status output');

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--status' => true])
            ->expectsOutput("Showing migration status for module [{$this->testModuleName}]...")
            ->expectsOutput("Status output")
            ->expectsOutput("Showing migration status for module [{$this->testModuleName}] completed successfully.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_resets_migrations_when_reset_option_is_used()
    {
        // Mocking the Artisan::call() result
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:reset', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Reset output');

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--reset' => true])
            ->expectsOutput("Resetting all migrations for module [{$this->testModuleName}]...")
            ->expectsOutput("Reset output")
            ->expectsOutput("Resetting all migrations for module [{$this->testModuleName}] completed successfully.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_refreshes_migrations_when_refresh_option_is_used()
    {
        // Mocking the Artisan::call() result
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:refresh', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Refresh output');

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--refresh' => true])
            ->expectsOutput("Refreshing all migrations for module [{$this->testModuleName}]...")
            ->expectsOutput("Refresh output")
            ->expectsOutput("Refreshing all migrations for module [{$this->testModuleName}] completed successfully.")
            ->assertExitCode(0);
    }

    /**
     * Helper method to create a test module with migrations
     */
    protected function createTestModule()
    {
        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        // Create module directories
        $directories = [
            '',
            'Database/Migrations',
            'Providers',
        ];

        foreach ($directories as $directory) {
            $path = $modulePath . ($directory ? '/' . $directory : '');
            $this->files->makeDirectory($path, 0755, true);
        }

        // Create a dummy migration file
        $migrationPath = $modulePath . '/Database/Migrations/2023_01_01_000000_create_test_table.php';
        $migrationContent = <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('test_table');
    }
};
EOT;

        $this->files->put($migrationPath, $migrationContent);
    }
}
