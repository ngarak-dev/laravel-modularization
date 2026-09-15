<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use Orchestra\Testbench\TestCase;

class MigrateModuleCommandTest extends TestCase
{
    protected Filesystem $files;

    protected string $testModuleName = 'TestModule';

    protected string $modulesPath;

    protected function getPackageProviders($app): array
    {
        return [ModularizationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('modularization.modules_path', 'modules');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = base_path('modules');
        $this->files->ensureDirectoryExists($this->modulesPath, 0755);
        $this->createTestModule();
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath.'/'.$this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath.'/'.$this->testModuleName);
        }

        parent::tearDown();
    }

    public function test_it_shows_error_when_module_does_not_exist(): void
    {
        $this->artisan('module:migrate', ['name' => 'NonExistentModule'])
            ->expectsOutput('Module [NonExistentModule] does not exist.')
            ->assertExitCode(1);
    }

    public function test_it_shows_warning_when_no_migrations_found(): void
    {
        $this->files->deleteDirectory($this->modulesPath.'/'.$this->testModuleName.'/Database/Migrations');

        $this->artisan('module:migrate', ['name' => $this->testModuleName])
            ->expectsOutput("No migrations found for module [{$this->testModuleName}].")
            ->assertExitCode(0);
    }

    public function test_it_runs_module_migrations(): void
    {
        $this->artisan('module:migrate', ['name' => $this->testModuleName])
            ->expectsOutputToContain("Running migrations for module [{$this->testModuleName}]")
            ->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('test_table'));
    }

    protected function createTestModule(): void
    {
        $modulePath = $this->modulesPath.'/'.$this->testModuleName;
        $this->files->ensureDirectoryExists($modulePath.'/Database/Migrations', 0755);

        $this->files->put($modulePath.'/Database/Migrations/2023_01_01_000000_create_test_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_table');
    }
};
PHP);
    }
}
