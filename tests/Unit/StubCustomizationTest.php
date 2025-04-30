<?php

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\PublishStubsCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class StubCustomizationTest extends TestCase
{
    protected $files;
    protected $stubsPath;

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
        $this->stubsPath = base_path('stubs/vendor/modularization');

        // Clean up any existing stubs
        if ($this->files->isDirectory($this->stubsPath)) {
            $this->files->deleteDirectory($this->stubsPath);
        }
    }

    protected function tearDown(): void
    {
        // Clean up stubs
        if ($this->files->isDirectory($this->stubsPath)) {
            $this->files->deleteDirectory($this->stubsPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_publish_stubs()
    {
        // Register the command
        $this->app->singleton('command.module.publish-stubs', function ($app) {
            return new PublishStubsCommand($app['files']);
        });

        // Execute the command
        $this->artisan('module:publish-stubs')
            ->expectsOutput('Stubs published successfully!')
            ->assertExitCode(0);

        // Check that the stubs directory was created
        $this->assertTrue($this->files->isDirectory($this->stubsPath));

        // Check that stubs were published
        $stubFiles = $this->files->files($this->stubsPath);
        $this->assertNotEmpty($stubFiles);
    }

    /** @test */
    public function it_can_use_custom_stubs_when_creating_modules()
    {
        // First publish the stubs
        $this->app->singleton('command.module.publish-stubs', function ($app) {
            return new PublishStubsCommand($app['files']);
        });

        $this->artisan('module:publish-stubs')->assertExitCode(0);

        // Modify a stub with custom content
        $modelStubPath = $this->stubsPath . '/model.stub';
        if ($this->files->exists($modelStubPath)) {
            $customContent = $this->files->get($modelStubPath);
            $customContent .= "\n    // Custom model implementation";
            $this->files->put($modelStubPath, $customContent);
        } else {
            // Create a custom model stub
            $this->files->put($modelStubPath, '<?php

namespace {{namespace}}\{{moduleName}}\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {{moduleName}} extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Add your fields here
    ];

    // Custom model implementation
}');
        }

        // Register the make:module command
        $this->app->singleton('command.module.make', function ($app) {
            return new \NgarakDev\Modularization\Console\Commands\MakeModuleCommand($app['files']);
        });

        // Create a module
        $testModuleName = 'StubTest';
        $modulesPath = base_path('modules');

        // Make sure we have a clean test environment
        if ($this->files->isDirectory($modulesPath . '/' . $testModuleName)) {
            $this->files->deleteDirectory($modulesPath . '/' . $testModuleName);
        }

        // Create modules directory if it doesn't exist
        if (!$this->files->isDirectory($modulesPath)) {
            $this->files->makeDirectory($modulesPath, 0755, true);
        }

        // Create a module with custom stubs
        $this->artisan('make:module', ['name' => $testModuleName])
            ->assertExitCode(0);

        // Check if the model contains our custom code
        $modelPath = $modulesPath . '/' . $testModuleName . '/Models/' . $testModuleName . '.php';
        $this->assertTrue($this->files->exists($modelPath));
        $modelContent = $this->files->get($modelPath);
        $this->assertStringContainsString('// Custom model implementation', $modelContent);

        // Clean up
        if ($this->files->isDirectory($modulesPath . '/' . $testModuleName)) {
            $this->files->deleteDirectory($modulesPath . '/' . $testModuleName);
        }
    }
}
