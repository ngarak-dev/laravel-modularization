<?php

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleTranslationCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModuleTranslationTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'TranslationTest';
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

        $this->app->singleton('command.module.make-translation', function ($app) {
            return new MakeModuleTranslationCommand($app['files']);
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
    public function it_can_generate_translation_files()
    {
        // Execute the command
        $this->artisan('module:make-translation', [
            'module' => $this->testModuleName
        ])
            ->expectsOutput("Translation files created successfully for module [{$this->testModuleName}]")
            ->assertExitCode(0);

        // Check that the translation directories were created for default languages
        $langPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/lang';
        $this->assertTrue($this->files->isDirectory($langPath));

        foreach (['en', 'es', 'fr', 'de'] as $lang) {
            $langDir = $langPath . '/' . $lang;
            $this->assertTrue($this->files->isDirectory($langDir));

            // Check for translation files
            $this->assertTrue($this->files->exists($langDir . '/general.php'));
            $this->assertTrue($this->files->exists($langDir . '/validation.php'));
            $this->assertTrue($this->files->exists($langDir . '/' . strtolower($this->testModuleName) . '.php'));
        }
    }

    /** @test */
    public function it_can_generate_specific_languages()
    {
        $languages = ['en', 'ar', 'ja'];

        // Execute the command with specific languages
        $this->artisan('module:make-translation', [
            'module' => $this->testModuleName,
            '--languages' => $languages
        ])
            ->assertExitCode(0);

        // Check that only the specified language directories were created
        $langPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/lang';

        foreach ($languages as $lang) {
            $langDir = $langPath . '/' . $lang;
            $this->assertTrue($this->files->isDirectory($langDir));
        }

        // Check that other languages were not created
        $this->assertFalse($this->files->isDirectory($langPath . '/de'));
        $this->assertFalse($this->files->isDirectory($langPath . '/es'));
    }

    /** @test */
    public function it_can_create_translations_with_module_creation()
    {
        $moduleName = 'TransWithModule';
        $modulePath = $this->modulesPath . '/' . $moduleName;

        // Clean up any existing module
        if ($this->files->isDirectory($modulePath)) {
            $this->files->deleteDirectory($modulePath);
        }

        // Create module with translations
        $this->artisan('make:module', [
            'name' => $moduleName,
            '--with-translations' => true
        ])->assertExitCode(0);

        // Check that the translation directories were created
        $langPath = $modulePath . '/Resources/lang';
        $this->assertTrue($this->files->isDirectory($langPath));

        foreach (['en', 'es', 'fr', 'de'] as $lang) {
            $langDir = $langPath . '/' . $lang;
            $this->assertTrue($this->files->isDirectory($langDir));

            // Check for translation files
            $this->assertTrue($this->files->exists($langDir . '/general.php'));
            $this->assertTrue($this->files->exists($langDir . '/validation.php'));
            $this->assertTrue($this->files->exists($langDir . '/' . strtolower($moduleName) . '.php'));
        }

        // Clean up
        if ($this->files->isDirectory($modulePath)) {
            $this->files->deleteDirectory($modulePath);
        }
    }

    /** @test */
    public function it_contains_correct_translation_content()
    {
        // Execute the command
        $this->artisan('module:make-translation', [
            'module' => $this->testModuleName,
            '--languages' => ['en']
        ])->assertExitCode(0);

        $langPath = $this->modulesPath . '/' . $this->testModuleName . '/Resources/lang/en';

        // Check general translations
        $generalContent = include $langPath . '/general.php';
        $this->assertIsArray($generalContent);
        $this->assertArrayHasKey('created', $generalContent);
        $this->assertArrayHasKey('updated', $generalContent);
        $this->assertArrayHasKey('deleted', $generalContent);
        $this->assertArrayHasKey('actions', $generalContent);

        // Check validation translations
        $validationContent = include $langPath . '/validation.php';
        $this->assertIsArray($validationContent);
        $this->assertArrayHasKey('required', $validationContent);
        $this->assertArrayHasKey('email', $validationContent);
        $this->assertArrayHasKey('min', $validationContent);
        $this->assertArrayHasKey('max', $validationContent);

        // Check module-specific translations
        $moduleContent = include $langPath . '/' . strtolower($this->testModuleName) . '.php';
        $this->assertIsArray($moduleContent);
        $this->assertArrayHasKey('details', $moduleContent);
        $this->assertArrayHasKey('name', $moduleContent);
        $this->assertArrayHasKey('created_at', $moduleContent);

        // Check that the module name is in the translations
        $moduleNameLower = strtolower($this->testModuleName);
        $this->assertArrayHasKey($moduleNameLower, $moduleContent);
        $this->assertArrayHasKey('create_' . $moduleNameLower, $moduleContent);
        $this->assertArrayHasKey('edit_' . $moduleNameLower, $moduleContent);
    }
}
