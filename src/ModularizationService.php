<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Support\ModuleCache;
use NgarakDev\Modularization\Support\ModuleDiscovery;
use NgarakDev\Modularization\Support\ModuleStatusManager;

/**
 * Central access point for module metadata and status.
 *
 * This service is registered as a singleton and is used by the service provider,
 * Artisan commands, and the Modularization facade.
 *
 * Module discovery runs once per request (or is loaded from cache in production).
 * To rebuild the cache, run: php artisan module:cache
 */
final class ModularizationService
{
    /** @var array<string, Module>|null */
    private ?array $modules = null;

    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly ModuleStatusManager $statusManager,
        private readonly ModuleCache $cache,
    ) {}

    /**
     * Get all discovered modules (enabled and disabled).
     *
     * @return array<string, Module>
     */
    public function getModules(): array
    {
        return $this->loadModules();
    }

    /**
     * Get only enabled modules.
     *
     * @return array<string, Module>
     */
    public function getEnabledModules(): array
    {
        return array_filter($this->loadModules(), fn (Module $m) => $m->isEnabled());
    }

    /**
     * Get only disabled modules.
     *
     * @return array<string, Module>
     */
    public function getDisabledModules(): array
    {
        return array_filter($this->loadModules(), fn (Module $m) => $m->isDisabled());
    }

    /**
     * Find a module by name.
     *
     * @throws ModuleNotFoundException
     */
    public function findOrFail(string $name): Module
    {
        $modules = $this->loadModules();

        if (! isset($modules[$name])) {
            throw ModuleNotFoundException::forModule($name);
        }

        return $modules[$name];
    }

    /**
     * Find a module by name or return null.
     */
    public function find(string $name): ?Module
    {
        return $this->loadModules()[$name] ?? null;
    }

    /**
     * Determine whether the given module exists.
     */
    public function hasModule(string $name): bool
    {
        return isset($this->loadModules()[$name]);
    }

    /**
     * Determine whether the given module is enabled.
     */
    public function isEnabled(string $name): bool
    {
        $module = $this->find($name);

        return $module !== null && $module->isEnabled();
    }

    /**
     * Enable a module persistently.
     *
     * @throws ModuleNotFoundException
     */
    public function enable(string $name): void
    {
        $module = $this->findOrFail($name);
        $this->statusManager->enable($module);
        $this->invalidateMemoryCache();
    }

    /**
     * Disable a module persistently.
     *
     * @throws ModuleNotFoundException
     */
    public function disable(string $name): void
    {
        $module = $this->findOrFail($name);
        $this->statusManager->disable($module);
        $this->invalidateMemoryCache();
    }

    /**
     * Force a re-scan of the modules directory, bypassing cache.
     */
    public function refresh(): void
    {
        $this->modules = null;
        $this->loadModules(forceDiscover: true);
    }

    /**
     * @return array<string, Module>
     */
    private function loadModules(bool $forceDiscover = false): array
    {
        if ($this->modules !== null && ! $forceDiscover) {
            return $this->modules;
        }

        // Try loading from file cache first (production)
        if (! $forceDiscover && $this->cache->isCached()) {
            $cached = $this->cache->load();
            if ($cached !== null) {
                $this->modules = $cached;

                return $this->modules;
            }
        }

        $this->modules = $this->discovery->discover();

        return $this->modules;
    }

    /**
     * Invalidate the in-memory module cache so the next access re-reads from disk.
     */
    private function invalidateMemoryCache(): void
    {
        $this->modules = null;
    }
}
