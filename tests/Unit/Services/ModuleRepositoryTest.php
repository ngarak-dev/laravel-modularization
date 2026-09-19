<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit\Services;

use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleDiscoveryInterface;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Exceptions\CircularDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Services\ModuleRepository;
use NgarakDev\Modularization\Support\Module;
use PHPUnit\Framework\TestCase;

class ModuleRepositoryTest extends TestCase
{
    private ModuleRepository $repository;

    private ModuleDiscoveryInterface $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = $this->createMock(ModuleDiscoveryInterface::class);
        $this->repository = new ModuleRepository($this->discovery);
    }

    /** @test */
    public function it_discovers_modules_on_first_access(): void
    {
        $modules = $this->createModuleCollection(['Products', 'Orders']);

        $this->discovery
            ->expects($this->once())
            ->method('discover')
            ->willReturn($modules);

        $result = $this->repository->all();

        $this->assertCount(2, $result);
        $this->assertTrue($result->has('Products'));
        $this->assertTrue($result->has('Orders'));
    }

    /** @test */
    public function it_caches_discovered_modules(): void
    {
        $modules = $this->createModuleCollection(['Products']);

        $this->discovery
            ->expects($this->once())
            ->method('discover')
            ->willReturn($modules);

        $this->repository->all();
        $this->repository->all();
        $this->repository->all();
    }

    /** @test */
    public function it_returns_only_enabled_modules(): void
    {
        $modules = collect([
            'Products' => new Module('Products', '/modules/Products', 'Modules\\Products', true),
            'Orders' => new Module('Orders', '/modules/Orders', 'Modules\\Orders', false),
            'Users' => new Module('Users', '/modules/Users', 'Modules\\Users', true),
        ]);

        $this->discovery->method('discover')->willReturn($modules);

        $enabled = $this->repository->enabled();

        $this->assertCount(2, $enabled);
        $this->assertTrue($enabled->has('Products'));
        $this->assertTrue($enabled->has('Users'));
        $this->assertFalse($enabled->has('Orders'));
    }

    /** @test */
    public function it_returns_only_disabled_modules(): void
    {
        $modules = collect([
            'Products' => new Module('Products', '/modules/Products', 'Modules\\Products', true),
            'Orders' => new Module('Orders', '/modules/Orders', 'Modules\\Orders', false),
        ]);

        $this->discovery->method('discover')->willReturn($modules);

        $disabled = $this->repository->disabled();

        $this->assertCount(1, $disabled);
        $this->assertTrue($disabled->has('Orders'));
    }

    /** @test */
    public function it_finds_module_by_name(): void
    {
        $modules = $this->createModuleCollection(['Products', 'Orders']);
        $this->discovery->method('discover')->willReturn($modules);

        $module = $this->repository->find('Products');

        $this->assertNotNull($module);
        $this->assertSame('Products', $module->getName());
    }

    /** @test */
    public function it_returns_null_for_non_existent_module(): void
    {
        $modules = $this->createModuleCollection(['Products']);
        $this->discovery->method('discover')->willReturn($modules);

        $module = $this->repository->find('NonExistent');

        $this->assertNull($module);
    }

    /** @test */
    public function it_throws_exception_when_module_not_found(): void
    {
        $modules = $this->createModuleCollection(['Products']);
        $this->discovery->method('discover')->willReturn($modules);

        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('NonExistent');

        $this->repository->findOrFail('NonExistent');
    }

    /** @test */
    public function it_checks_if_module_exists(): void
    {
        $modules = $this->createModuleCollection(['Products']);
        $this->discovery->method('discover')->willReturn($modules);

        $this->assertTrue($this->repository->has('Products'));
        $this->assertFalse($this->repository->has('Orders'));
    }

    /** @test */
    public function it_registers_new_modules(): void
    {
        $modules = $this->createModuleCollection(['Products']);
        $this->discovery->method('discover')->willReturn($modules);

        $newModule = new Module('Orders', '/modules/Orders', 'Modules\\Orders');
        $this->repository->register($newModule);

        $this->assertTrue($this->repository->has('Orders'));
    }

    /** @test */
    public function it_counts_modules(): void
    {
        $modules = $this->createModuleCollection(['Products', 'Orders', 'Users']);
        $this->discovery->method('discover')->willReturn($modules);

        $this->assertSame(3, $this->repository->count());
    }

    /** @test */
    public function it_orders_modules_by_dependencies(): void
    {
        $modules = collect([
            'Orders' => new Module('Orders', '/modules/Orders', 'Modules\\Orders', true, [
                'requires' => ['Products', 'Users'],
            ]),
            'Products' => new Module('Products', '/modules/Products', 'Modules\\Products', true),
            'Users' => new Module('Users', '/modules/Users', 'Modules\\Users', true),
        ]);

        $this->discovery->method('discover')->willReturn($modules);

        $ordered = $this->repository->getOrderedByDependencies();
        $orderedKeys = $ordered->keys()->all();

        $productsIndex = array_search('Products', $orderedKeys);
        $usersIndex = array_search('Users', $orderedKeys);
        $ordersIndex = array_search('Orders', $orderedKeys);

        $this->assertTrue($productsIndex < $ordersIndex);
        $this->assertTrue($usersIndex < $ordersIndex);
    }

    /** @test */
    public function it_detects_circular_dependencies(): void
    {
        $modules = collect([
            'A' => new Module('A', '/modules/A', 'Modules\\A', true, ['requires' => ['B']]),
            'B' => new Module('B', '/modules/B', 'Modules\\B', true, ['requires' => ['C']]),
            'C' => new Module('C', '/modules/C', 'Modules\\C', true, ['requires' => ['A']]),
        ]);

        $this->discovery->method('discover')->willReturn($modules);

        $this->expectException(CircularDependencyException::class);

        $this->repository->getOrderedByDependencies();
    }

    /**
     * @param  array<string>  $names
     * @return Collection<string, ModuleInterface>
     */
    private function createModuleCollection(array $names): Collection
    {
        $modules = collect();

        foreach ($names as $name) {
            $modules->put($name, new Module(
                $name,
                "/modules/{$name}",
                "Modules\\{$name}",
            ));
        }

        return $modules;
    }
}
