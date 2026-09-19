<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Services;

use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleDiscoveryInterface;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Contracts\ModuleRepositoryInterface;
use NgarakDev\Modularization\Exceptions\CircularDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;

/**
 * Repository for managing module instances.
 */
final class ModuleRepository implements ModuleRepositoryInterface
{
    /**
     * @var Collection<string, ModuleInterface>
     */
    private Collection $modules;

    private bool $discovered = false;

    public function __construct(
        private readonly ModuleDiscoveryInterface $discovery,
    ) {
        $this->modules = collect();
    }

    /**
     * @return Collection<string, ModuleInterface>
     */
    public function all(): Collection
    {
        $this->ensureDiscovered();

        return $this->modules;
    }

    /**
     * @return Collection<string, ModuleInterface>
     */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (ModuleInterface $module) => $module->isEnabled());
    }

    /**
     * @return Collection<string, ModuleInterface>
     */
    public function disabled(): Collection
    {
        return $this->all()->filter(fn (ModuleInterface $module) => ! $module->isEnabled());
    }

    public function find(string $name): ?ModuleInterface
    {
        $this->ensureDiscovered();

        return $this->modules->get($name);
    }

    public function findOrFail(string $name): ModuleInterface
    {
        $module = $this->find($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name);
        }

        return $module;
    }

    public function has(string $name): bool
    {
        $this->ensureDiscovered();

        return $this->modules->has($name);
    }

    public function register(ModuleInterface $module): void
    {
        $this->ensureDiscovered();
        $this->modules->put($module->getName(), $module);
    }

    /**
     * @return Collection<string, ModuleInterface>
     */
    public function getOrderedByDependencies(): Collection
    {
        $modules = $this->enabled();
        $sorted = collect();
        $visited = [];
        $visiting = [];

        foreach ($modules as $name => $module) {
            if (! isset($visited[$name])) {
                $this->topologicalSort($name, $modules, $sorted, $visited, $visiting);
            }
        }

        return $sorted;
    }

    public function count(): int
    {
        return $this->all()->count();
    }

    /**
     * Force rediscovery of modules.
     */
    public function refresh(): void
    {
        $this->discovered = false;
        $this->modules = collect();
        $this->ensureDiscovered();
    }

    /**
     * Set modules directly (used for caching).
     *
     * @param  Collection<string, ModuleInterface>  $modules
     */
    public function setModules(Collection $modules): void
    {
        $this->modules = $modules;
        $this->discovered = true;
    }

    /**
     * Ensure modules have been discovered.
     */
    private function ensureDiscovered(): void
    {
        if (! $this->discovered) {
            $this->modules = $this->discovery->discover();
            $this->discovered = true;
        }
    }

    /**
     * Perform topological sort for dependency ordering.
     *
     * @param  Collection<string, ModuleInterface>  $modules
     * @param  Collection<string, ModuleInterface>  $sorted
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $visiting
     *
     * @throws CircularDependencyException
     */
    private function topologicalSort(
        string $name,
        Collection $modules,
        Collection $sorted,
        array &$visited,
        array &$visiting
    ): void {
        if (isset($visiting[$name])) {
            $chain = array_keys($visiting);
            $chain[] = $name;
            throw CircularDependencyException::detected($chain);
        }

        if (isset($visited[$name])) {
            return;
        }

        $visiting[$name] = true;

        $module = $modules->get($name);
        if ($module !== null) {
            foreach ($module->getDependencies() as $dependency) {
                if ($modules->has($dependency)) {
                    $this->topologicalSort($dependency, $modules, $sorted, $visited, $visiting);
                }
            }

            $sorted->put($name, $module);
        }

        unset($visiting[$name]);
        $visited[$name] = true;
    }
}
