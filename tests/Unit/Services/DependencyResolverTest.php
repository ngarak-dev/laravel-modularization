<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit\Services;

use NgarakDev\Modularization\Contracts\ModuleRepositoryInterface;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;
use NgarakDev\Modularization\Services\DependencyResolver;
use NgarakDev\Modularization\Support\Module;
use PHPUnit\Framework\TestCase;

class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    private ModuleRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(ModuleRepositoryInterface::class);
        $this->resolver = new DependencyResolver($this->repository);
    }

    /** @test */
    public function it_validates_module_with_no_dependencies(): void
    {
        $module = new Module('Products', '/modules/Products', 'Modules\\Products');

        $this->repository->method('has')->willReturn(true);

        $this->resolver->validate($module);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_validates_module_with_satisfied_dependencies(): void
    {
        $module = new Module('Orders', '/modules/Orders', 'Modules\\Orders', true, [
            'requires' => ['Products'],
        ]);

        $dependencyModule = new Module('Products', '/modules/Products', 'Modules\\Products', true);

        $this->repository->method('has')->with('Products')->willReturn(true);
        $this->repository->method('find')->with('Products')->willReturn($dependencyModule);

        $this->resolver->validate($module);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_exception_for_missing_dependency(): void
    {
        $module = new Module('Orders', '/modules/Orders', 'Modules\\Orders', true, [
            'requires' => ['Products'],
        ]);

        $this->repository->method('has')->with('Products')->willReturn(false);

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage('Products');

        $this->resolver->validate($module);
    }

    /** @test */
    public function it_throws_exception_for_disabled_dependency(): void
    {
        $module = new Module('Orders', '/modules/Orders', 'Modules\\Orders', true, [
            'requires' => ['Products'],
        ]);

        $disabledDependency = new Module('Products', '/modules/Products', 'Modules\\Products', false);

        $this->repository->method('has')->with('Products')->willReturn(true);
        $this->repository->method('find')->with('Products')->willReturn($disabledDependency);

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage('disabled');

        $this->resolver->validate($module);
    }

    /** @test */
    public function it_reports_multiple_missing_dependencies(): void
    {
        $module = new Module('Orders', '/modules/Orders', 'Modules\\Orders', true, [
            'requires' => ['Products', 'Users', 'Payments'],
        ]);

        $this->repository->method('has')->willReturnCallback(function ($name) {
            return $name === 'Products';
        });

        $this->repository->method('find')->willReturnCallback(function ($name) {
            if ($name === 'Products') {
                return new Module('Products', '/modules/Products', 'Modules\\Products', true);
            }

            return null;
        });

        try {
            $this->resolver->validate($module);
            $this->fail('Expected ModuleDependencyException to be thrown');
        } catch (ModuleDependencyException $e) {
            $this->assertCount(2, $e->getMissingDependencies());
            $this->assertContains('Users', $e->getMissingDependencies());
            $this->assertContains('Payments', $e->getMissingDependencies());
        }
    }

    /** @test */
    public function it_finds_dependent_modules(): void
    {
        $ordersModule = new Module('Orders', '/modules/Orders', 'Modules\\Orders', true, [
            'requires' => ['Products'],
        ]);
        $shippingModule = new Module('Shipping', '/modules/Shipping', 'Modules\\Shipping', true, [
            'requires' => ['Products', 'Orders'],
        ]);
        $productsModule = new Module('Products', '/modules/Products', 'Modules\\Products', true);

        $allModules = collect([
            'Orders' => $ordersModule,
            'Shipping' => $shippingModule,
            'Products' => $productsModule,
        ]);

        $this->repository->method('all')->willReturn($allModules);

        $dependents = $this->resolver->getDependents('Products');

        $this->assertCount(2, $dependents);
        $this->assertTrue($dependents->has('Orders'));
        $this->assertTrue($dependents->has('Shipping'));
    }
}
