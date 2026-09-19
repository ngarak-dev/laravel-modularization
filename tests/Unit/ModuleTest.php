<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Module;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    #[Test]
    public function it_creates_module_with_basic_properties(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            description: 'Product management',
            enabled: true,
        );

        $this->assertSame('Products', $module->getName());
        $this->assertSame('/app/modules/Products', $module->getPath());
        $this->assertSame('Modules\\Products', $module->getNamespace());
        $this->assertTrue($module->isEnabled());
        $this->assertSame('Product management', $module->getDescription());
    }

    #[Test]
    public function it_returns_dependencies_from_requires(): void
    {
        $module = new Module(
            name: 'Orders',
            path: '/app/modules/Orders',
            namespace: 'Modules\\Orders',
            requires: ['Products', 'Users'],
        );

        $this->assertSame(['Products', 'Users'], $module->getDependencies());
    }

    #[Test]
    public function it_returns_empty_array_when_no_dependencies(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
        );

        $this->assertSame([], $module->getDependencies());
    }

    #[Test]
    public function it_returns_default_version_when_not_specified(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
        );

        $this->assertSame('1.0.0', $module->getVersion());
    }

    #[Test]
    public function it_resolves_provider_class(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            provider: 'Custom\\Provider',
        );

        $this->assertSame('Custom\\Provider', $module->getProviderClass());
    }

    #[Test]
    public function it_resolves_subpath_correctly(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
        );

        $this->assertSame('/app/modules/Products', $module->subPath());
        $this->assertSame('/app/modules/Products'.DIRECTORY_SEPARATOR.'Http/Controllers', $module->subPath('Http/Controllers'));
    }

    #[Test]
    public function it_can_be_serialized_to_array(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            version: '1.2.0',
            enabled: true,
        );

        $array = $module->toArray();

        $this->assertSame('Products', $array['name']);
        $this->assertSame('/app/modules/Products', $array['path']);
        $this->assertSame('Modules\\Products', $array['namespace']);
        $this->assertTrue($array['enabled']);
        $this->assertSame('1.2.0', $array['manifest']['version']);
    }

    #[Test]
    public function it_can_be_created_from_array(): void
    {
        $module = Module::fromArray([
            'name' => 'Products',
            'path' => '/app/modules/Products',
            'namespace' => 'Modules\\Products',
            'enabled' => false,
            'manifest' => ['version' => '2.0.0'],
        ]);

        $this->assertSame('Products', $module->getName());
        $this->assertSame('/app/modules/Products', $module->getPath());
        $this->assertFalse($module->isEnabled());
        $this->assertSame('2.0.0', $module->getVersion());
    }

    #[Test]
    public function it_can_update_enabled_status_immutably(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            enabled: true,
        );

        $disabled = $module->withEnabled(false);

        $this->assertTrue($module->isEnabled());
        $this->assertFalse($disabled->isEnabled());
        $this->assertSame('disabled', $disabled->status());
    }

    #[Test]
    public function installing_and_broken_lifecycle_are_exposed(): void
    {
        $installing = (new Module(name: 'A', path: '/a', namespace: 'Modules\\A'))->withLifecycle('installing');
        $broken = (new Module(name: 'B', path: '/b', namespace: 'Modules\\B'))->withLifecycle('broken', 'boom');

        $this->assertSame('installing', $installing->status());
        $this->assertSame('broken', $broken->status());
        $this->assertFalse($broken->valid);
    }
}
