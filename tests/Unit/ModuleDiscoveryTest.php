<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use NgarakDev\Modularization\Support\ModuleDiscovery;
use Orchestra\Testbench\TestCase;

class ModuleDiscoveryTest extends TestCase
{
    protected Filesystem $files;
    protected string $modulesPath;
    protected ModuleDiscovery $discovery;

    protected function getPackageProviders($app)
    {
        return [ModularizationServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->modulesPath = base_path('test_discovery_modules');

        if ($this->files->isDirectory($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        $this->files->makeDirectory($this->modulesPath, 0755, true);

        $this->discovery = new ModuleDiscovery(
            files: $this->files,
            modulesPath: $this->modulesPath,
            defaultNamespace: 'Modules',
        );
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_returns_empty_array_when_modules_directory_does_not_exist(): void
    {
        $this->files->deleteDirectory($this->modulesPath);

        $discovery = new ModuleDiscovery($this->files, $this->modulesPath, 'Modules');
        $modules = $discovery->discover();

        $this->assertEmpty($modules);
    }

    /** @test */
    public function it_discovers_modules_from_directory(): void
    {
        $this->files->makeDirectory($this->modulesPath . '/Alpha');
        $this->files->makeDirectory($this->modulesPath . '/Beta');

        $modules = $this->discovery->discover();

        $this->assertCount(2, $modules);
        $this->assertArrayHasKey('Alpha', $modules);
        $this->assertArrayHasKey('Beta', $modules);
    }

    /** @test */
    public function it_creates_module_from_directory_without_manifest(): void
    {
        $this->files->makeDirectory($this->modulesPath . '/MyModule');

        $modules = $this->discovery->discover();
        $module = $modules['MyModule'];

        $this->assertInstanceOf(Module::class, $module);
        $this->assertSame('MyModule', $module->name);
        $this->assertTrue($module->isEnabled());
        $this->assertSame('Modules\\MyModule', $module->namespace);
    }

    /** @test */
    public function it_reads_module_manifest_when_present(): void
    {
        $this->files->makeDirectory($this->modulesPath . '/ManifestModule');
        $this->files->put($this->modulesPath . '/ManifestModule/module.json', json_encode([
            'name' => 'ManifestModule',
            'version' => '2.5.0',
            'description' => 'A test module',
            'requires' => ['OtherModule'],
        ]));

        $modules = $this->discovery->discover();
        $module = $modules['ManifestModule'];

        $this->assertSame('2.5.0', $module->version);
        $this->assertSame('A test module', $module->description);
        $this->assertSame(['OtherModule'], $module->requires);
    }

    /** @test */
    public function it_marks_module_disabled_when_dot_disabled_file_exists(): void
    {
        $this->files->makeDirectory($this->modulesPath . '/DisabledModule');
        $this->files->put($this->modulesPath . '/DisabledModule/.disabled', '');

        $modules = $this->discovery->discover();

        $this->assertTrue($modules['DisabledModule']->isDisabled());
    }

    /** @test */
    public function it_dot_disabled_overrides_manifest_enabled_true(): void
    {
        $this->files->makeDirectory($this->modulesPath . '/ConfusedModule');
        $this->files->put($this->modulesPath . '/ConfusedModule/module.json', json_encode([
            'enabled' => true,
        ]));
        $this->files->put($this->modulesPath . '/ConfusedModule/.disabled', '');

        $modules = $this->discovery->discover();
        $this->assertTrue($modules['ConfusedModule']->isDisabled());
    }

    /** @test */
    public function it_rejects_invalid_module_names(): void
    {
        $this->assertFalse($this->discovery->isValidModuleName(''));
        $this->assertFalse($this->discovery->isValidModuleName('..'));
        $this->assertFalse($this->discovery->isValidModuleName('../etc'));
        $this->assertFalse($this->discovery->isValidModuleName('foo/bar'));
        $this->assertFalse($this->discovery->isValidModuleName('foo bar'));
        $this->assertFalse($this->discovery->isValidModuleName('1StartWithNumber'));
    }

    /** @test */
    public function it_accepts_valid_module_names(): void
    {
        $this->assertTrue($this->discovery->isValidModuleName('Orders'));
        $this->assertTrue($this->discovery->isValidModuleName('OrderManagement'));
        $this->assertTrue($this->discovery->isValidModuleName('order_management'));
        $this->assertTrue($this->discovery->isValidModuleName('order-management'));
        $this->assertTrue($this->discovery->isValidModuleName('Module123'));
    }

    /** @test */
    public function it_skips_directories_with_invalid_names(): void
    {
        // Create some valid and some with names that look like hidden dirs
        $this->files->makeDirectory($this->modulesPath . '/ValidModule');
        $this->files->makeDirectory($this->modulesPath . '/.hidden');

        $modules = $this->discovery->discover();

        $this->assertArrayHasKey('ValidModule', $modules);
        $this->assertArrayNotHasKey('.hidden', $modules);
    }
}
