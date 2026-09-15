<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use Orchestra\Testbench\TestCase;

class MigrateModulesCommandTest extends TestCase
{
    protected Filesystem $files;

    protected string $modulesPath;

    /**
     * @var array<int, string>
     */
    protected array $testModules = ['TestModule1', 'TestModule2', 'DisabledModule'];

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
        $this->createTestModules();
    }

    protected function tearDown(): void
    {
        foreach ($this->testModules as $module) {
            if ($this->files->isDirectory($this->modulesPath.'/'.$module)) {
                $this->files->deleteDirectory($this->modulesPath.'/'.$module);
            }
        }

        parent::tearDown();
    }

    public function test_it_warns_when_modules_directory_does_not_exist(): void
    {
        $this->files->deleteDirectory($this->modulesPath);

        $this->artisan('module:migrate-all')
            ->expectsOutput('Modules directory does not exist.')
            ->assertExitCode(1);
    }

    public function test_it_warns_when_no_modules_found(): void
    {
        foreach ($this->testModules as $module) {
            $this->files->deleteDirectory($this->modulesPath.'/'.$module);
        }

        $this->artisan('module:migrate-all')
            ->expectsOutput('No modules found.')
            ->assertExitCode(0);
    }

    public function test_it_skips_disabled_modules_when_using_only_enabled_flag(): void
    {
        $this->artisan('module:migrate-all', ['--only-enabled' => true])
            ->expectsOutputToContain('Skipping disabled module [DisabledModule]')
            ->expectsOutputToContain('Migration Summary:')
            ->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('TestModule1'));
        $this->assertTrue(Schema::hasTable('TestModule2'));
        $this->assertFalse(Schema::hasTable('DisabledModule'));
    }

    public function test_it_migrates_all_modules_when_not_using_only_enabled_flag(): void
    {
        $this->artisan('module:migrate-all')
            ->expectsOutputToContain('Migration Summary:')
            ->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('TestModule1'));
        $this->assertTrue(Schema::hasTable('TestModule2'));
        $this->assertTrue(Schema::hasTable('DisabledModule'));
    }

    protected function createTestModules(): void
    {
        foreach ($this->testModules as $index => $moduleName) {
            $modulePath = $this->modulesPath.'/'.$moduleName;
            $this->files->ensureDirectoryExists($modulePath.'/Database/Migrations', 0755);
            $this->files->ensureDirectoryExists($modulePath.'/Config', 0755);

            $this->files->put(
                $modulePath.'/Database/Migrations/2023_01_01_00000'.$index.'_create_'.strtolower($moduleName).'_table.php',
                <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$moduleName}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$moduleName}');
    }
};
PHP
            );

            $this->files->put($modulePath.'/Config/config.php', <<<PHP
<?php

return [
    'name' => '{$moduleName}',
    'enabled' => true,
];
PHP);
        }

        $this->files->put($this->modulesPath.'/DisabledModule/.disabled', '');
    }
}
