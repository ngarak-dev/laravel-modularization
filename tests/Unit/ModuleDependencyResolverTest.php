<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\DependencyResolver;
use NgarakDev\Modularization\Exceptions\CircularModuleDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;
use NgarakDev\Modularization\Module;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ModuleDependencyResolverTest extends TestCase
{
    private function makeModule(string $name, array $requires = [], bool $enabled = true, string $version = '1.0.0'): Module
    {
        return new Module(
            name: $name,
            path: '/modules/'.$name,
            namespace: 'Modules\\'.$name,
            version: $version,
            enabled: $enabled,
            requires: $requires,
            requirementConstraints: array_fill_keys($requires, '*'),
        );
    }

    #[Test]
    public function it_resolves_modules_with_no_dependencies(): void
    {
        $modules = [
            'Alpha' => $this->makeModule('Alpha'),
            'Beta' => $this->makeModule('Beta'),
        ];

        $resolved = (new DependencyResolver)->sort($modules);

        $this->assertCount(2, $resolved);
        $this->assertArrayHasKey('Alpha', $resolved);
        $this->assertArrayHasKey('Beta', $resolved);
    }

    #[Test]
    public function it_resolves_modules_in_dependency_order(): void
    {
        $modules = [
            'Orders' => $this->makeModule('Orders', ['Users']),
            'Users' => $this->makeModule('Users'),
            'Payments' => $this->makeModule('Payments', ['Users', 'Orders']),
        ];

        $names = array_keys((new DependencyResolver)->sort($modules));

        $this->assertLessThan(array_search('Orders', $names, true), array_search('Users', $names, true));
        $this->assertLessThan(array_search('Payments', $names, true), array_search('Orders', $names, true));
    }

    #[Test]
    public function it_throws_when_dependency_is_missing(): void
    {
        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/Users/');

        (new DependencyResolver)->sort([
            'Orders' => $this->makeModule('Orders', ['Users']),
        ], true);
    }

    #[Test]
    public function it_reports_disabled_dependencies_as_missing(): void
    {
        $resolver = new DependencyResolver;
        $modules = [
            'Orders' => $this->makeModule('Orders', ['Users']),
            'Users' => $this->makeModule('Users', [], false),
        ];

        $this->assertSame(['Users'], $resolver->missingDependencies($modules['Orders'], $modules));
    }

    #[Test]
    public function it_detects_direct_circular_dependency(): void
    {
        $this->expectException(CircularModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        (new DependencyResolver)->sort([
            'Alpha' => $this->makeModule('Alpha', ['Beta']),
            'Beta' => $this->makeModule('Beta', ['Alpha']),
        ]);
    }

    #[Test]
    public function it_detects_indirect_circular_dependency(): void
    {
        $this->expectException(CircularModuleDependencyException::class);

        (new DependencyResolver)->sort([
            'A' => $this->makeModule('A', ['B']),
            'B' => $this->makeModule('B', ['C']),
            'C' => $this->makeModule('C', ['A']),
        ]);
    }

    #[Test]
    public function it_resolves_a_chain_of_dependencies(): void
    {
        $resolved = (new DependencyResolver)->sort([
            'D' => $this->makeModule('D', ['C']),
            'C' => $this->makeModule('C', ['B']),
            'B' => $this->makeModule('B', ['A']),
            'A' => $this->makeModule('A'),
        ]);

        $this->assertSame(['A', 'B', 'C', 'D'], array_keys($resolved));
    }

    #[Test]
    public function it_handles_module_with_multiple_dependencies(): void
    {
        $names = array_keys((new DependencyResolver)->sort([
            'Shipping' => $this->makeModule('Shipping', ['Orders', 'Products']),
            'Products' => $this->makeModule('Products'),
            'Orders' => $this->makeModule('Orders', ['Products']),
        ]));

        $this->assertLessThan(array_search('Orders', $names, true), array_search('Products', $names, true));
        $this->assertLessThan(array_search('Shipping', $names, true), array_search('Orders', $names, true));
    }

    #[Test]
    public function it_resolves_empty_module_list(): void
    {
        $this->assertSame([], (new DependencyResolver)->sort([]));
    }

    #[Test]
    public function unsatisfied_semver_constraints_are_missing_dependencies(): void
    {
        $orders = new Module(
            name: 'Orders',
            path: '/modules/Orders',
            namespace: 'Modules\\Orders',
            requires: ['Users'],
            requirementConstraints: ['Users' => '^2.0'],
        );
        $users = $this->makeModule('Users', [], true, '1.4.0');

        $missing = (new DependencyResolver)->missingDependencies($orders, [
            'Orders' => $orders,
            'Users' => $users,
        ]);

        $this->assertNotEmpty($missing);
        $this->assertStringContainsString('Users', $missing[0]);
    }
}
