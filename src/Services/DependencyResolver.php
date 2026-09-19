<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Services;

use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Contracts\ModuleRepositoryInterface;
use NgarakDev\Modularization\Exceptions\CircularDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;

/**
 * Resolves and validates module dependencies.
 */
final class DependencyResolver
{
    public function __construct(
        private readonly ModuleRepositoryInterface $repository,
    ) {}

    /**
     * Validate dependencies for a module.
     *
     * @throws ModuleDependencyException
     */
    public function validate(ModuleInterface $module): void
    {
        $missing = [];

        foreach ($module->getDependencies() as $dependency) {
            if (! $this->repository->has($dependency)) {
                $missing[] = $dependency;

                continue;
            }

            $depModule = $this->repository->find($dependency);
            if ($depModule !== null && ! $depModule->isEnabled()) {
                throw ModuleDependencyException::dependencyDisabled(
                    $module->getName(),
                    $dependency
                );
            }
        }

        if (! empty($missing)) {
            throw ModuleDependencyException::missingDependencies($module->getName(), $missing);
        }
    }

    /**
     * Validate dependencies for all enabled modules.
     *
     * @throws ModuleDependencyException
     */
    public function validateAll(): void
    {
        foreach ($this->repository->enabled() as $module) {
            $this->validate($module);
        }
    }

    /**
     * Get modules sorted by dependency order.
     *
     * @return Collection<string, ModuleInterface>
     *
     * @throws CircularDependencyException
     */
    public function getSortedModules(): Collection
    {
        return $this->repository->getOrderedByDependencies();
    }

    /**
     * Check for circular dependencies among all modules.
     *
     * @throws CircularDependencyException
     */
    public function checkCircularDependencies(): void
    {
        $this->repository->getOrderedByDependencies();
    }

    /**
     * Get the dependency tree for a module.
     *
     * @return array<string, array<string>>
     */
    public function getDependencyTree(string $moduleName): array
    {
        $module = $this->repository->find($moduleName);

        if ($module === null) {
            return [];
        }

        return $this->buildDependencyTree($module, []);
    }

    /**
     * Get modules that depend on a given module.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function getDependents(string $moduleName): Collection
    {
        return $this->repository->all()->filter(function (ModuleInterface $module) use ($moduleName) {
            return in_array($moduleName, $module->getDependencies(), true);
        });
    }

    /**
     * Build a dependency tree recursively.
     *
     * @param  array<string>  $visited
     * @return array<string, array<string>>
     */
    private function buildDependencyTree(ModuleInterface $module, array $visited): array
    {
        $name = $module->getName();

        if (in_array($name, $visited, true)) {
            return [];
        }

        $visited[] = $name;
        $tree = [$name => $module->getDependencies()];

        foreach ($module->getDependencies() as $dependency) {
            $depModule = $this->repository->find($dependency);
            if ($depModule !== null) {
                $tree = array_merge($tree, $this->buildDependencyTree($depModule, $visited));
            }
        }

        return $tree;
    }
}
