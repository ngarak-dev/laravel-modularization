<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;

/**
 * Backwards-compatible public API used by the Modularization facade.
 */
class ModularizationService
{
    public function __construct(
        protected Filesystem $files,
        protected ModuleManager $manager,
    ) {}

    /**
     * Scan for all available modules.
     */
    public function refresh(): void
    {
        $this->manager->refresh();
        $this->manager->discover(false);
    }

    public function find(string $name): ?Module
    {
        return $this->manager->find($name);
    }

    public function findOrFail(string $name): Module
    {
        return $this->manager->get($name);
    }

    public function scanModules(): void
    {
        $this->manager->discover(false);
    }

    /**
     * Get all modules as a name-indexed array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getModules(): array
    {
        return $this->manager->getModules();
    }

    public function hasModule(string $name): bool
    {
        return $this->manager->hasModule($name);
    }

    public function isEnabled(string $name): bool
    {
        return $this->manager->isEnabled($name);
    }

    public function enable(string $name): bool
    {
        return $this->manager->enable($name);
    }

    public function disable(string $name): bool
    {
        return $this->manager->disable($name);
    }

    public function manager(): ModuleManager
    {
        return $this->manager;
    }

    public function path(?string $module = null, string $path = ''): string
    {
        return $this->manager->path($module, $path);
    }

    public function cache(): string
    {
        return $this->manager->cache();
    }

    public function clearCache(): bool
    {
        return $this->manager->clearCache();
    }
}
