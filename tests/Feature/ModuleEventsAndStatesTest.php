<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Support\Facades\Event;
use NgarakDev\Modularization\Events\ModuleDisabled;
use NgarakDev\Modularization\Events\ModuleDiscovered;
use NgarakDev\Modularization\Events\ModuleEnabled;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModuleEventsAndStatesTest extends TestCase
{
    #[Test]
    public function discovery_dispatches_discovered_events(): void
    {
        Event::fake([ModuleDiscovered::class]);
        $this->writeModuleFixture('Catalog');

        $this->app->make(ModuleManager::class)->discover(false);

        Event::assertDispatched(ModuleDiscovered::class, fn (ModuleDiscovered $event): bool => $event->module->name === 'Catalog');
    }

    #[Test]
    public function enable_and_disable_dispatch_lifecycle_events(): void
    {
        Event::fake([ModuleEnabled::class, ModuleDisabled::class]);
        $this->writeModuleFixture('Catalog');
        $manager = $this->app->make(ModuleManager::class);

        $manager->disable('Catalog');
        $manager->enable('Catalog');

        Event::assertDispatched(ModuleDisabled::class);
        Event::assertDispatched(ModuleEnabled::class);
    }

    #[Test]
    public function installing_and_broken_markers_change_status(): void
    {
        $this->writeModuleFixture('Catalog');
        $this->files->put($this->modulePath('Catalog', '.installing'), 'now');

        $manager = $this->app->make(ModuleManager::class);
        $manager->refresh();
        $this->assertSame('installing', $manager->get('Catalog')->status());
        $this->assertFalse($manager->get('Catalog')->enabled);

        $this->files->delete($this->modulePath('Catalog', '.installing'));
        $this->files->put($this->modulePath('Catalog', '.broken'), 'failed');
        $manager->refresh();

        $this->assertSame('broken', $manager->get('Catalog')->status());
        $this->assertFalse($manager->get('Catalog')->valid);
    }

    #[Test]
    public function vite_inputs_are_collected_from_module_assets(): void
    {
        $this->writeModuleFixture('Catalog');
        $this->files->ensureDirectoryExists($this->modulePath('Catalog', 'Resources/assets/js'), 0755);
        $this->files->ensureDirectoryExists($this->modulePath('Catalog', 'Resources/assets/css'), 0755);
        $this->files->put($this->modulePath('Catalog', 'Resources/assets/js/app.js'), '// js');
        $this->files->put($this->modulePath('Catalog', 'Resources/assets/css/app.css'), '/* css */');

        $this->app->make(ModuleManager::class)->refresh();
        $this->app->make(ModuleManager::class)->boot();

        $inputs = config('modularization.vite.inputs', []);
        $this->assertNotEmpty($inputs);
        $this->assertTrue(collect($inputs)->contains(fn ($input): bool => str_ends_with((string) $input, 'app.js')));
    }

    #[Test]
    public function registry_can_be_exported_and_read(): void
    {
        $this->writeModuleFixture('Catalog', ['version' => '3.2.1']);
        $path = $this->app->make(ModuleManager::class)->exportRegistry();
        $decoded = json_decode($this->files->get($path), true);

        $this->assertSame('Catalog', $decoded['modules']['Catalog']['name']);
        $this->assertSame('3.2.1', $decoded['modules']['Catalog']['version']);
    }
}
