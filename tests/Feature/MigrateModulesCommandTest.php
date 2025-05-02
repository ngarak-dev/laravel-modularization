<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MigrateModulesCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MigrateModulesCommandTest extends TestCase
{
    protected $files;
    protected $modulesPath;
    protected $testModules = ['TestModule1', 'TestModule2', 'DisabledModule'];

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

        // Clean up any existing test modules
        foreach ($this->testModules as $module) {
            if ($this->files->isDirectory($this->modulesPath . '/' . $module)) {
                $this->files->deleteDirectory($this->modulesPath . '/' . $module);
            }
        }

        // Ensure modules directory exists
        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        // Create test modules
        $this->createTestModules();
    }

    protected function tearDown(): void
    {
        // Clean up test modules
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
        // Delete modules directory
        $this->files->deleteDirectory($this->modulesPath);

        $this->artisan('module:migrate-all')
            ->expectsOutput("Modules directory does not exist.")
            ->assertExitCode(1);
    }

    /** @test */
    public function it_warns_when_no_modules_found()
    {
        // Remove all modules but keep directory
        foreach ($this->testModules as $module) {
            if ($this->files->isDirectory($this->modulesPath . '/' . $module)) {
                $this->files->deleteDirectory($this->modulesPath . '/' . $module);
            }
        }

        $this->artisan('module:migrate-all')
            ->expectsOutput("No modules found.")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_skips_disabled_modules_when_using_only_enabled_flag()
    {
        // Mocking Artisan::call for the two enabled modules
        Artisan::shouldReceive('call')
            ->twice()
            ->with('module:migrate', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Migration output');

        $this->artisan('module:migrate-all', ['--only-enabled' => true])
            ->expectsOutput("Skipping disabled module [DisabledModule]")
            ->expectsOutput("Migration Summary:")
            ->expectsOutput("- 2 modules migrated successfully")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_migrates_all_modules_when_not_using_only_enabled_flag()
    {
        // Mocking Artisan::call for all three modules
        Artisan::shouldReceive('call')
            ->times(3)
            ->with('module:migrate', \Mockery::type('array'))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn('Migration output');

        $this->artisan('module:migrate-all')
            ->expectsOutput("Migration Summary:")
            ->expectsOutput("- 3 modules migrated successfully")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_reports_failed_migrations()
    {
        // Mock two successful migrations and one failure
        Artisan::shouldReceive('call')
            ->twice()
            ->with('module:migrate', \Mockery::contains(['name' => 'TestModule1']))
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('module:migrate', \Mockery::contains(['name' => 'TestModule2']))
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('module:migrate', \Mockery::contains(['name' => 'DisabledModule']))
            ->andReturn(1);

        Artisan::shouldReceive('output')
            ->andReturn('Migration output');

        $this->artisan('module:migrate-all')
            ->expectsOutput("Migration Summary:")
            ->expectsOutput("- 2 modules migrated successfully")
            ->expectsOutput("- 1 modules failed: DisabledModule")
            ->assertExitCode(1);
    }

    /**
     * Helper method to create test modules with migrations
     */
    protected function createTestModules()
    {
        foreach ($this->testModules as $index => $moduleName) {
            $modulePath = $this->modulesPath . '/' . $moduleName;

            // Create module directories
            $directories = [
                '',
                'Database/Migrations',
                'Providers',
                'Config',
            ];

            foreach ($directories as $directory) {
                $path = $modulePath . ($directory ? '/' . $directory : '');
                $this->files->makeDirectory($path, 0755, true);
            }

            // Create a dummy migration file
            $migrationPath = $modulePath . '/Database/Migrations/2023_01_01_00000' . $index . '_create_' . strtolower($moduleName) . '_table.php';
            $migrationContent = <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('$moduleName', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('$moduleName');
    }
};
EOT;
            $this->files->put($migrationPath, $migrationContent);

            // Create config file for each module
            $configPath = $modulePath . '/Config/config.php';
            $configContent = <<<EOT
<?php

return [
    'name' => '{$moduleName}',
    'description' => 'Test module {$moduleName}',
    'enabled' => true,
    'routes' => [
        'prefix' => '${moduleName}',
        'middleware' => ['web'],
    ],
    'menu' => [
        'title' => '{$moduleName}',
        'icon' => 'fa fa-cube',
    ],
];
EOT;
            $this->files->put($configPath, $configContent);
        }

        // Mark the DisabledModule as disabled
        $this->files->put($this->modulesPath . '/DisabledModule/.disabled', '');

        // Update config for DisabledModule
        $disabledConfigPath = $this->modulesPath . '/DisabledModule/Config/config.php';
        $disabledConfig = include $disabledConfigPath;
        $disabledConfig['enabled'] = false;
        $this->files->put($disabledConfigPath, "<?php\n\nreturn " . var_export($disabledConfig, true) . ";");
    }
}
