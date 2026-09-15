<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MigrateModuleCommandTest extends TestCase
{
    protected Filesystem $files;
    protected string $testModuleName = 'TestMigrateModule';
    protected string $modulesPath;

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

        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        $this->createTestModule();
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_shows_error_when_module_does_not_exist()
    {
        $this->artisan('module:migrate', ['name' => 'NonExistentModule'])
            ->expectsOutput('Module [NonExistentModule] does not exist.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_shows_warning_when_no_migrations_found()
    {
        $migrationsPath = $this->modulesPath . '/' . $this->testModuleName . '/Database/Migrations';
        $this->files->deleteDirectory($migrationsPath);

        $this->artisan('module:migrate', ['name' => $this->testModuleName])
            ->expectsOutput("No migrations found for module [{$this->testModuleName}].")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_migrations_when_migrations_exist()
    {
        $this->artisan('module:migrate', ['name' => $this->testModuleName])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_migrations_fresh_when_fresh_option_is_used()
    {
        // First run to create tables
        $this->artisan('module:migrate', ['name' => $this->testModuleName])->assertExitCode(0);

        // Fresh run
        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--fresh' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_rolls_back_migrations_when_rollback_option_is_used()
    {
        $this->artisan('module:migrate', ['name' => $this->testModuleName])->assertExitCode(0);

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--rollback' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_migration_status_when_status_option_is_used()
    {
        $this->artisan('module:migrate', ['name' => $this->testModuleName])
            ->assertExitCode(0);

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--status' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_resets_migrations_when_reset_option_is_used()
    {
        $this->artisan('module:migrate', ['name' => $this->testModuleName])->assertExitCode(0);

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--reset' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_refreshes_migrations_when_refresh_option_is_used()
    {
        $this->artisan('module:migrate', ['name' => $this->testModuleName])->assertExitCode(0);

        $this->artisan('module:migrate', ['name' => $this->testModuleName, '--refresh' => true])
            ->assertExitCode(0);
    }

    protected function createTestModule(): void
    {
        $modulePath = $this->modulesPath . '/' . $this->testModuleName;

        foreach (['', 'Database/Migrations', 'Providers'] as $directory) {
            $path = $modulePath . ($directory ? '/' . $directory : '');
            $this->files->makeDirectory($path, 0755, true);
        }

        $migrationPath = $modulePath . '/Database/Migrations/2023_01_01_000000_create_migrate_test_table.php';
        $this->files->put($migrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migrate_test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migrate_test_table');
    }
};
PHP);
    }
}
