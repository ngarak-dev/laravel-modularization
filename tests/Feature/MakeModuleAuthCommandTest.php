<?php

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleAuthCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class MakeModuleAuthCommandTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'AuthTest';
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

        // Register the commands
        $this->app->singleton('command.module.make', function ($app) {
            return new MakeModuleCommand($app['files']);
        });

        $this->app->singleton('command.module.make-auth', function ($app) {
            return new MakeModuleAuthCommand($app['files']);
        });

        // Create a test module first
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
    public function it_can_generate_auth_scaffolding()
    {
        // Execute the command
        $this->artisan('module:make-auth', [
            'module' => $this->testModuleName
        ])
            ->expectsOutput("Authentication scaffolding created successfully for module [{$this->testModuleName}]")
            ->assertExitCode(0);

        // Check that controllers were created
        $controllersPath = $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/Auth';
        $this->assertTrue($this->files->isDirectory($controllersPath));

        // Check for controller files
        $this->assertTrue($this->files->exists($controllersPath . '/LoginController.php'));
        $this->assertTrue($this->files->exists($controllersPath . '/RegisterController.php'));
        $this->assertTrue($this->files->exists($controllersPath . '/ForgotPasswordController.php'));
        $this->assertTrue($this->files->exists($controllersPath . '/ResetPasswordController.php'));
        $this->assertTrue($this->files->exists($controllersPath . '/VerifyEmailController.php'));

        // Check that views were created
        $viewsPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/views/auth';
        $this->assertTrue($this->files->isDirectory($viewsPath));

        // Check for view files
        $this->assertTrue($this->files->exists($viewsPath . '/login.blade.php'));
        $this->assertTrue($this->files->exists($viewsPath . '/register.blade.php'));
        $this->assertTrue($this->files->exists($viewsPath . '/forgot-password.blade.php'));
        $this->assertTrue($this->files->exists($viewsPath . '/reset-password.blade.php'));
        $this->assertTrue($this->files->exists($viewsPath . '/verify-email.blade.php'));

        // Check for auth layout
        $layoutsPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/views/layouts';
        $this->assertTrue($this->files->isDirectory($layoutsPath));
        $this->assertTrue($this->files->exists($layoutsPath . '/auth-layout.blade.php'));

        // Check that middleware was created
        $middlewarePath = $this->modulesPath . '/' . $this->testModuleName . '/Http/Middleware';
        $this->assertTrue($this->files->isDirectory($middlewarePath));

        // Check for middleware files
        $this->assertTrue($this->files->exists($middlewarePath . '/Authenticate.php'));
        $this->assertTrue($this->files->exists($middlewarePath . '/RedirectIfAuthenticated.php'));

        // Check that routes were created
        $routesPath = $this->modulesPath . '/' . $this->testModuleName . '/Routes/auth.php';
        $this->assertTrue($this->files->exists($routesPath));

        // Check that service provider was updated
        $providerPath = $this->modulesPath . '/' . $this->testModuleName . '/Providers/' . $this->testModuleName . 'ServiceProvider.php';
        $providerContent = $this->files->get($providerPath);

        // Verify service provider contains auth route registration
        $this->assertStringContainsString('loadRoutesFrom(__DIR__ . \'/../Routes/auth.php\')', $providerContent);

        // Verify service provider contains middleware registration
        $this->assertStringContainsString('module.auth', $providerContent);
        $this->assertStringContainsString('module.guest', $providerContent);
    }

    /** @test */
    public function it_does_not_overwrite_existing_files_without_force_option()
    {
        // First run to create files
        $this->artisan('module:make-auth', [
            'module' => $this->testModuleName
        ])->run();

        // Modify a file to check it's not overwritten
        $loginControllerPath = $this->modulesPath . '/' . $this->testModuleName . '/Http/Controllers/Auth/LoginController.php';
        $originalContent = $this->files->get($loginControllerPath);
        $modifiedContent = $originalContent . "\n// Modified test content";
        $this->files->put($loginControllerPath, $modifiedContent);

        // Run command again without force
        $this->artisan('module:make-auth', [
            'module' => $this->testModuleName
        ])->run();

        // Check that file wasn't overwritten
        $this->assertEquals($modifiedContent, $this->files->get($loginControllerPath));

        // Run command with force option
        $this->artisan('module:make-auth', [
            'module' => $this->testModuleName,
            '--force' => true
        ])->run();

        // Check that file was overwritten
        $this->assertNotEquals($modifiedContent, $this->files->get($loginControllerPath));
    }

    /** @test */
    public function it_fails_when_module_does_not_exist()
    {
        // Execute the command with non-existent module
        $this->artisan('module:make-auth', [
            'module' => 'NonExistentModule'
        ])
            ->expectsOutput('Module [NonExistentModule] does not exist!')
            ->assertExitCode(1);
    }
}
