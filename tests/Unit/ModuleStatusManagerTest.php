<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use NgarakDev\Modularization\Support\ModuleStatusManager;
use Orchestra\Testbench\TestCase;

class ModuleStatusManagerTest extends TestCase
{
    protected Filesystem $files;
    protected string $modulesPath;
    protected ModuleStatusManager $manager;

    protected function getPackageProviders($app): array
    {
        return [ModularizationServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->modulesPath = sys_get_temp_dir() . '/status_manager_test_' . uniqid();
        $this->files->makeDirectory($this->modulesPath . '/MyModule', 0755, true);

        $this->manager = new ModuleStatusManager($this->files);
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    private function makeModule(string $name, bool $enabled = true): Module
    {
        return new Module(
            name: $name,
            path: $this->modulesPath . '/' . $name,
            enabled: $enabled,
            namespace: 'Modules\\' . $name,
            version: '1.0.0',
            description: '',
            requires: [],
        );
    }

    /** @test */
    public function it_reports_enabled_when_no_sentinel_file_exists(): void
    {
        $module = $this->makeModule('MyModule');

        $this->assertTrue($this->manager->isEnabled($module));
    }

    /** @test */
    public function it_reports_disabled_when_sentinel_file_exists(): void
    {
        $module = $this->makeModule('MyModule');
        $this->files->put($this->modulesPath . '/MyModule/.disabled', '');

        $this->assertFalse($this->manager->isEnabled($module));
    }

    /** @test */
    public function disable_creates_sentinel_file(): void
    {
        $module = $this->makeModule('MyModule');
        $sentinelPath = $this->modulesPath . '/MyModule/.disabled';

        $this->assertFalse($this->files->exists($sentinelPath));

        $this->manager->disable($module);

        $this->assertTrue($this->files->exists($sentinelPath));
    }

    /** @test */
    public function disable_writes_json_metadata_to_sentinel_file(): void
    {
        $module = $this->makeModule('MyModule');
        $sentinelPath = $this->modulesPath . '/MyModule/.disabled';

        $this->manager->disable($module);

        $content = $this->files->get($sentinelPath);
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('disabled_at', $data);
    }

    /** @test */
    public function enable_removes_sentinel_file(): void
    {
        $module = $this->makeModule('MyModule');
        $sentinelPath = $this->modulesPath . '/MyModule/.disabled';

        $this->files->put($sentinelPath, '');
        $this->assertTrue($this->files->exists($sentinelPath));

        $this->manager->enable($module);

        $this->assertFalse($this->files->exists($sentinelPath));
    }

    /** @test */
    public function enable_is_idempotent_when_already_enabled(): void
    {
        $module = $this->makeModule('MyModule');

        // Should not throw even when already enabled
        $this->manager->enable($module);
        $this->manager->enable($module);

        $this->assertTrue($this->manager->isEnabled($module));
    }

    /** @test */
    public function disable_is_idempotent_when_already_disabled(): void
    {
        $module = $this->makeModule('MyModule');

        $this->manager->disable($module);
        $this->manager->disable($module);

        $this->assertFalse($this->manager->isEnabled($module));
    }

    /** @test */
    public function it_updates_module_json_when_disabling(): void
    {
        $manifestPath = $this->modulesPath . '/MyModule/module.json';
        $this->files->put($manifestPath, json_encode([
            'name' => 'MyModule',
            'enabled' => true,
        ]));

        $module = $this->makeModule('MyModule');
        $this->manager->disable($module);

        $manifest = json_decode($this->files->get($manifestPath), true);
        $this->assertFalse($manifest['enabled']);
    }

    /** @test */
    public function it_updates_module_json_when_enabling(): void
    {
        $manifestPath = $this->modulesPath . '/MyModule/module.json';
        $this->files->put($manifestPath, json_encode([
            'name' => 'MyModule',
            'enabled' => false,
        ]));
        $this->files->put($this->modulesPath . '/MyModule/.disabled', '');

        $module = $this->makeModule('MyModule', false);
        $this->manager->enable($module);

        $manifest = json_decode($this->files->get($manifestPath), true);
        $this->assertTrue($manifest['enabled']);
    }

    /** @test */
    public function it_does_not_fail_when_module_json_is_absent(): void
    {
        $module = $this->makeModule('MyModule');

        // Should not throw - module.json is optional
        $this->manager->disable($module);
        $this->manager->enable($module);

        $this->assertTrue($this->manager->isEnabled($module));
    }
}
