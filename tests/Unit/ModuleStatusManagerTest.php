<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\ModuleStatusManager;
use NgarakDev\Modularization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModuleStatusManagerTest extends TestCase
{
    #[Test]
    public function it_reports_enabled_when_no_sentinel_file_exists(): void
    {
        $this->writeModuleFixture('MyModule');

        $this->assertTrue($this->app->make(ModuleStatusManager::class)->isEnabled('MyModule'));
    }

    #[Test]
    public function it_reports_disabled_when_sentinel_file_exists(): void
    {
        $this->writeModuleFixture('MyModule');
        $this->files->put($this->modulePath('MyModule', '.disabled'), '');

        $this->assertFalse($this->app->make(ModuleStatusManager::class)->isEnabled('MyModule'));
    }

    #[Test]
    public function disable_creates_sentinel_file(): void
    {
        $this->writeModuleFixture('MyModule');
        $sentinelPath = $this->modulePath('MyModule', '.disabled');
        $this->assertFalse($this->files->exists($sentinelPath));

        $this->app->make(ModuleStatusManager::class)->disable('MyModule');

        $this->assertTrue($this->files->exists($sentinelPath));
    }

    #[Test]
    public function disable_writes_json_metadata_to_sentinel_file(): void
    {
        $this->writeModuleFixture('MyModule');
        $this->app->make(ModuleStatusManager::class)->disable('MyModule');

        $data = json_decode($this->files->get($this->modulePath('MyModule', '.disabled')), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('disabled_at', $data);
    }

    #[Test]
    public function enable_removes_sentinel_file(): void
    {
        $this->writeModuleFixture('MyModule');
        $this->files->put($this->modulePath('MyModule', '.disabled'), '');

        $this->app->make(ModuleStatusManager::class)->enable('MyModule');

        $this->assertFalse($this->files->exists($this->modulePath('MyModule', '.disabled')));
    }

    #[Test]
    public function enable_is_idempotent_when_already_enabled(): void
    {
        $this->writeModuleFixture('MyModule');
        $manager = $this->app->make(ModuleStatusManager::class);

        $manager->enable('MyModule');
        $manager->enable('MyModule');

        $this->assertTrue($manager->isEnabled('MyModule'));
    }

    #[Test]
    public function disable_is_idempotent_when_already_disabled(): void
    {
        $this->writeModuleFixture('MyModule');
        $manager = $this->app->make(ModuleStatusManager::class);

        $manager->disable('MyModule');
        $manager->disable('MyModule');

        $this->assertFalse($manager->isEnabled('MyModule'));
    }

    #[Test]
    public function it_updates_module_json_when_disabling(): void
    {
        $this->writeModuleFixture('MyModule');
        $this->app->make(ModuleStatusManager::class)->disable('MyModule');

        $manifest = json_decode($this->files->get($this->modulePath('MyModule', 'module.json')), true);
        $this->assertFalse($manifest['enabled']);
    }

    #[Test]
    public function it_updates_module_json_when_enabling(): void
    {
        $this->writeModuleFixture('MyModule', ['enabled' => false]);
        $this->files->put($this->modulePath('MyModule', '.disabled'), '');

        $this->app->make(ModuleStatusManager::class)->enable('MyModule');

        $manifest = json_decode($this->files->get($this->modulePath('MyModule', 'module.json')), true);
        $this->assertTrue($manifest['enabled']);
    }

    #[Test]
    public function it_does_not_fail_when_module_json_is_absent(): void
    {
        $path = $this->modulePath('MyModule');
        $this->files->ensureDirectoryExists($path, 0755);

        $manager = $this->app->make(ModuleStatusManager::class);
        $manager->disable('MyModule');
        $manager->enable('MyModule');

        $this->assertTrue($manager->isEnabled('MyModule'));
    }

    #[Test]
    public function toggle_flips_enabled_state(): void
    {
        $this->writeModuleFixture('MyModule');
        $manager = $this->app->make(ModuleStatusManager::class);

        $this->assertFalse($manager->toggle('MyModule'));
        $this->assertTrue($manager->isDisabled('MyModule'));
        $this->assertTrue($manager->toggle('MyModule'));
        $this->assertTrue($manager->isEnabled('MyModule'));
    }
}
