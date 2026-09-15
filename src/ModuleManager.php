<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleCacheInterface;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Contracts\ModuleLoaderInterface;
use NgarakDev\Modularization\Contracts\ModuleRepositoryInterface;
use NgarakDev\Modularization\Contracts\ModuleStatusManagerInterface;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Services\DependencyResolver;

/**
 * Main entry point for module management.
 *
 * This class provides a clean public API for working with modules.
 */
final class ModuleManager
{
    private bool $booted = false;

    public function __construct(
        private readonly ModuleRepositoryInterface $repository,
        private readonly ModuleLoaderInterface $loader,
        private readonly ModuleStatusManagerInterface $statusManager,
        private readonly ModuleCacheInterface $cache,
        private readonly DependencyResolver $dependencyResolver,
    ) {
    }

    /**
     * Boot all enabled modules.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if ($this->cache->exists()) {
            $cached = $this->cache->get();
            if ($cached !== null) {
                $this->repository->setModules($cached);
            }
        }

        $modules = $this->repository->getOrderedByDependencies();

        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $this->loader->load($module);
            }
        }

        $this->booted = true;
    }

    /**
     * Get all modules.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Get all enabled modules.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function enabled(): Collection
    {
        return $this->repository->enabled();
    }

    /**
     * Get all disabled modules.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function disabled(): Collection
    {
        return $this->repository->disabled();
    }

    /**
     * Find a module by name.
     */
    public function find(string $name): ?ModuleInterface
    {
        return $this->repository->find($name);
    }

    /**
     * Find a module by name or throw an exception.
     *
     * @throws ModuleNotFoundException
     */
    public function findOrFail(string $name): ModuleInterface
    {
        return $this->repository->findOrFail($name);
    }

    /**
     * Check if a module exists.
     */
    public function has(string $name): bool
    {
        return $this->repository->has($name);
    }

    /**
     * Check if a module is enabled.
     */
    public function isEnabled(string $name): bool
    {
        return $this->statusManager->isEnabled($name);
    }

    /**
     * Check if a module is disabled.
     */
    public function isDisabled(string $name): bool
    {
        return $this->statusManager->isDisabled($name);
    }

    /**
     * Enable a module.
     *
     * @throws ModuleNotFoundException
     */
    public function enable(string $name): void
    {
        $this->statusManager->enable($name);
        $this->clearCache();
    }

    /**
     * Disable a module.
     *
     * @throws ModuleNotFoundException
     */
    public function disable(string $name): void
    {
        $this->statusManager->disable($name);
        $this->clearCache();
    }

    /**
     * Toggle module status.
     *
     * @return bool Returns true if module is now enabled, false if disabled
     * @throws ModuleNotFoundException
     */
    public function toggle(string $name): bool
    {
        $result = $this->statusManager->toggle($name);
        $this->clearCache();
        return $result;
    }

    /**
     * Get the count of all modules.
     */
    public function count(): int
    {
        return $this->repository->count();
    }

    /**
     * Get modules ordered by their dependencies.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function getOrderedByDependencies(): Collection
    {
        return $this->repository->getOrderedByDependencies();
    }

    /**
     * Validate dependencies for all modules.
     */
    public function validateDependencies(): void
    {
        $this->dependencyResolver->validateAll();
    }

    /**
     * Get modules that depend on a given module.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function getDependents(string $moduleName): Collection
    {
        return $this->dependencyResolver->getDependents($moduleName);
    }

    /**
     * Cache module metadata.
     */
    public function cache(): void
    {
        $this->cache->put($this->repository->all());
    }

    /**
     * Clear the module cache.
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * Check if the module cache exists.
     */
    public function isCached(): bool
    {
        return $this->cache->exists();
    }

    /**
     * Refresh the module list.
     */
    public function refresh(): void
    {
        $this->repository->refresh();
        $this->booted = false;
    }

    /**
     * Get module status summary.
     *
     * @return array{total: int, enabled: int, disabled: int}
     */
    public function getStatusSummary(): array
    {
        return [
            'total' => $this->repository->count(),
            'enabled' => $this->repository->enabled()->count(),
            'disabled' => $this->repository->disabled()->count(),
        ];
    }
}
