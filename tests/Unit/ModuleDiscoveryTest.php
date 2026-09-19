<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Exceptions\CircularModuleDependencyException;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Tests\TestCase;

final class ModuleDiscoveryTest extends TestCase
{
    public function test_manifest_metadata_is_discovered_deterministically(): void
    {
        $this->artisan('module:make', [
            'name' => 'Orders',
            '--requires' => ['Users'],
        ])->assertSuccessful();

        $this->app->make(ModuleManager::class)->refresh();
        $module = $this->app->make(ModuleManager::class)->get('Orders');

        $this->assertSame('Orders', $module->name);
        $this->assertSame(['Users'], $module->requires);
    }

    public function test_circular_dependencies_are_rejected(): void
    {
        $this->artisan('module:make', [
            'name' => 'Orders',
            '--requires' => ['Users'],
        ])->assertSuccessful();
        $this->artisan('module:make', [
            'name' => 'Users',
            '--requires' => ['Orders'],
        ])->assertSuccessful();

        $manager = $this->app->make(ModuleManager::class);
        $manager->refresh();

        $this->expectException(CircularModuleDependencyException::class);
        $manager->inLoadOrder();
    }
}
