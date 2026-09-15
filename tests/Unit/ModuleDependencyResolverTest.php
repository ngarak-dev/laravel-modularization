<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Exceptions\ModuleDependencyException;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\Support\ModuleDependencyResolver;
use PHPUnit\Framework\TestCase;

class ModuleDependencyResolverTest extends TestCase
{
    private function makeModule(string $name, array $requires = [], bool $enabled = true): Module
    {
        return new Module(
            name: $name,
            path: '/modules/' . $name,
            enabled: $enabled,
            namespace: 'Modules\\' . $name,
            version: '1.0.0',
            description: '',
            requires: $requires,
        );
    }

    /** @test */
    public function it_resolves_modules_with_no_dependencies(): void
    {
        $modules = [
            'Alpha' => $this->makeModule('Alpha'),
            'Beta'  => $this->makeModule('Beta'),
        ];

        $resolver = new ModuleDependencyResolver($modules);
        $resolved = $resolver->resolve();

        $this->assertCount(2, $resolved);
        $this->assertArrayHasKey('Alpha', $resolved);
        $this->assertArrayHasKey('Beta', $resolved);
    }

    /** @test */
    public function it_resolves_modules_in_dependency_order(): void
    {
        $modules = [
            'Orders'   => $this->makeModule('Orders', ['Users']),
            'Users'    => $this->makeModule('Users'),
            'Payments' => $this->makeModule('Payments', ['Users', 'Orders']),
        ];

        $resolver = new ModuleDependencyResolver($modules);
        $resolved = $resolver->resolve();
        $names = array_keys($resolved);

        $usersPos    = array_search('Users', $names, true);
        $ordersPos   = array_search('Orders', $names, true);
        $paymentsPos = array_search('Payments', $names, true);

        $this->assertLessThan($ordersPos, $usersPos, 'Users must load before Orders');
        $this->assertLessThan($paymentsPos, $ordersPos, 'Orders must load before Payments');
        $this->assertLessThan($paymentsPos, $usersPos, 'Users must load before Payments');
    }

    /** @test */
    public function it_throws_when_dependency_is_missing(): void
    {
        $modules = [
            'Orders' => $this->makeModule('Orders', ['Users']),
        ];

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/Users/');

        $resolver = new ModuleDependencyResolver($modules);
        $resolver->validate();
    }

    /** @test */
    public function it_throws_when_dependency_is_disabled(): void
    {
        $modules = [
            'Orders' => $this->makeModule('Orders', ['Users']),
            'Users'  => $this->makeModule('Users', [], false),
        ];

        $this->expectException(ModuleDependencyException::class);

        $resolver = new ModuleDependencyResolver($modules);
        $resolver->validate();
    }

    /** @test */
    public function it_detects_direct_circular_dependency(): void
    {
        $modules = [
            'Alpha' => $this->makeModule('Alpha', ['Beta']),
            'Beta'  => $this->makeModule('Beta', ['Alpha']),
        ];

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        $resolver = new ModuleDependencyResolver($modules);
        $resolver->resolve();
    }

    /** @test */
    public function it_detects_indirect_circular_dependency(): void
    {
        $modules = [
            'A' => $this->makeModule('A', ['B']),
            'B' => $this->makeModule('B', ['C']),
            'C' => $this->makeModule('C', ['A']),
        ];

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        $resolver = new ModuleDependencyResolver($modules);
        $resolver->resolve();
    }

    /** @test */
    public function it_resolves_a_chain_of_dependencies(): void
    {
        $modules = [
            'D' => $this->makeModule('D', ['C']),
            'C' => $this->makeModule('C', ['B']),
            'B' => $this->makeModule('B', ['A']),
            'A' => $this->makeModule('A'),
        ];

        $resolver = new ModuleDependencyResolver($modules);
        $resolved = $resolver->resolve();
        $names = array_keys($resolved);

        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            $names,
            'Modules should be resolved in chain order A→B→C→D'
        );
    }

    /** @test */
    public function it_handles_module_with_multiple_dependencies(): void
    {
        $modules = [
            'Shipping' => $this->makeModule('Shipping', ['Orders', 'Products']),
            'Products' => $this->makeModule('Products'),
            'Orders'   => $this->makeModule('Orders', ['Products']),
        ];

        $resolver = new ModuleDependencyResolver($modules);
        $resolved = $resolver->resolve();

        $names = array_keys($resolved);
        $this->assertContains('Products', $names);
        $this->assertLessThan(
            array_search('Orders', $names, true),
            array_search('Products', $names, true)
        );
        $this->assertLessThan(
            array_search('Shipping', $names, true),
            array_search('Orders', $names, true)
        );
    }

    /** @test */
    public function it_resolves_empty_module_list(): void
    {
        $resolver = new ModuleDependencyResolver([]);
        $resolved = $resolver->resolve();

        $this->assertIsArray($resolved);
        $this->assertEmpty($resolved);
    }

    /** @test */
    public function validate_passes_when_all_dependencies_are_met(): void
    {
        $modules = [
            'Orders' => $this->makeModule('Orders', ['Users']),
            'Users'  => $this->makeModule('Users'),
        ];

        $resolver = new ModuleDependencyResolver($modules);

        // Should not throw
        $resolver->validate();
        $this->assertTrue(true);
    }
}
