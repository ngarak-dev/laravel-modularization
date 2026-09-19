<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Exceptions\CircularModuleDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModuleManagerTest extends TestCase
{
    #[Test]
    public function it_discovers_modules_from_the_filesystem(): void
    {
        $this->writeModuleFixture('Products');
        $this->writeModuleFixture('Orders');

        $manager = $this->app->make(ModuleManager::class);
        $manager->refresh();

        $this->assertSame(2, $manager->count());
        $this->assertTrue($manager->has('Products'));
        $this->assertTrue($manager->has('Orders'));
    }

    #[Test]
    public function it_returns_only_enabled_and_disabled_modules(): void
    {
        $this->writeModuleFixture('Products');
        $this->writeModuleFixture('Orders');
        $this->app->make(ModuleManager::class)->disable('Orders');
        $this->app->make(ModuleManager::class)->refresh();

        $enabled = $this->app->make(ModuleManager::class)->enabled();
        $disabled = $this->app->make(ModuleManager::class)->disabled();

        $this->assertArrayHasKey('Products', $enabled);
        $this->assertArrayHasKey('Orders', $disabled);
        $this->assertArrayNotHasKey('Orders', $enabled);
    }

    #[Test]
    public function it_finds_module_by_name(): void
    {
        $this->writeModuleFixture('Products');
        $manager = $this->app->make(ModuleManager::class);

        $this->assertSame('Products', $manager->find('Products')?->getName());
        $this->assertNull($manager->find('Missing'));
    }

    #[Test]
    public function it_throws_when_module_is_missing(): void
    {
        $this->expectException(ModuleNotFoundException::class);
        $this->app->make(ModuleManager::class)->findOrFail('Missing');
    }

    #[Test]
    public function it_orders_modules_by_dependencies(): void
    {
        $this->writeModuleFixture('Products');
        $this->writeModuleFixture('Users');
        $this->writeModuleFixture('Orders', ['requires' => ['Products', 'Users']]);

        $ordered = array_keys($this->app->make(ModuleManager::class)->getOrderedByDependencies());

        $this->assertLessThan(array_search('Orders', $ordered, true), array_search('Products', $ordered, true));
        $this->assertLessThan(array_search('Orders', $ordered, true), array_search('Users', $ordered, true));
    }

    #[Test]
    public function it_detects_circular_dependencies(): void
    {
        $this->writeModuleFixture('Alpha', ['requires' => ['Beta']]);
        $this->writeModuleFixture('Beta', ['requires' => ['Alpha']]);

        $this->expectException(CircularModuleDependencyException::class);
        $this->app->make(ModuleManager::class)->getOrderedByDependencies();
    }

    #[Test]
    public function get_dependents_finds_modules_that_require_another(): void
    {
        $this->writeModuleFixture('Products');
        $this->writeModuleFixture('Orders', ['requires' => ['Products']]);
        $this->writeModuleFixture('Shipping', ['requires' => ['Products', 'Orders']]);

        $dependents = $this->app->make(ModuleManager::class)->getDependents('Products');

        $this->assertTrue($dependents->has('Orders'));
        $this->assertTrue($dependents->has('Shipping'));
    }
}
