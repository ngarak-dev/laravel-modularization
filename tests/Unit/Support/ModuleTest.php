<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit\Support;

use NgarakDev\Modularization\Support\Module;
use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    /** @test */
    public function it_creates_module_with_basic_properties(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            enabled: true,
            manifest: ['description' => 'Product management'],
        );

        $this->assertSame('Products', $module->getName());
        $this->assertSame('/app/modules/Products', $module->getPath());
        $this->assertSame('Modules\\Products', $module->getNamespace());
        $this->assertTrue($module->isEnabled());
        $this->assertSame('Product management', $module->getDescription());
    }

    /** @test */
    public function it_returns_dependencies_from_manifest(): void
    {
        $module = new Module(
            name: 'Orders',
            path: '/app/modules/Orders',
            namespace: 'Modules\\Orders',
            manifest: [
                'requires' => ['Products', 'Users'],
            ],
        );

        $this->assertSame(['Products', 'Users'], $module->getDependencies());
    }

    /** @test */
    public function it_returns_empty_array_when_no_dependencies(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
        );

        $this->assertSame([], $module->getDependencies());
    }

    /** @test */
    public function it_returns_default_version_when_not_specified(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
        );

        $this->assertSame('1.0.0', $module->getVersion());
    }

    /** @test */
    public function it_returns_version_from_manifest(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            manifest: ['version' => '2.1.0'],
        );

        $this->assertSame('2.1.0', $module->getVersion());
    }

    /** @test */
    public function it_resolves_provider_class_from_manifest(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            manifest: ['provider' => 'Custom\\Provider'],
        );

        $this->assertSame('Custom\\Provider', $module->getProviderClass());
    }

    /** @test */
    public function it_resolves_subpath_correctly(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
        );

        $this->assertSame('/app/modules/Products', $module->path());
        $this->assertSame('/app/modules/Products/Http/Controllers', $module->path('Http/Controllers'));
    }

    /** @test */
    public function it_can_be_serialized_to_array(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            enabled: true,
            manifest: ['version' => '1.2.0'],
        );

        $array = $module->toArray();

        $this->assertSame('Products', $array['name']);
        $this->assertSame('/app/modules/Products', $array['path']);
        $this->assertSame('Modules\\Products', $array['namespace']);
        $this->assertTrue($array['enabled']);
        $this->assertSame(['version' => '1.2.0'], $array['manifest']);
    }

    /** @test */
    public function it_can_be_created_from_array(): void
    {
        $data = [
            'name' => 'Products',
            'path' => '/app/modules/Products',
            'namespace' => 'Modules\\Products',
            'enabled' => false,
            'manifest' => ['version' => '2.0.0'],
        ];

        $module = Module::fromArray($data);

        $this->assertSame('Products', $module->getName());
        $this->assertSame('/app/modules/Products', $module->getPath());
        $this->assertFalse($module->isEnabled());
        $this->assertSame('2.0.0', $module->getVersion());
    }

    /** @test */
    public function it_can_update_enabled_status(): void
    {
        $module = new Module(
            name: 'Products',
            path: '/app/modules/Products',
            namespace: 'Modules\\Products',
            enabled: true,
        );

        $this->assertTrue($module->isEnabled());

        $module->setEnabled(false);
        $this->assertFalse($module->isEnabled());

        $module->setEnabled(true);
        $this->assertTrue($module->isEnabled());
    }
}
