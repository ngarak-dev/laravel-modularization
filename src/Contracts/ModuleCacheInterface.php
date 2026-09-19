<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

use Illuminate\Support\Collection;

/**
 * Handles module metadata caching.
 */
interface ModuleCacheInterface
{
    /**
     * Check if the cache exists and is valid.
     */
    public function exists(): bool;

    /**
     * Get cached modules.
     *
     * @return Collection<string, ModuleInterface>|null
     */
    public function get(): ?Collection;

    /**
     * Cache the modules.
     *
     * @param  Collection<string, ModuleInterface>  $modules
     */
    public function put(Collection $modules): void;

    /**
     * Clear the cache.
     */
    public function clear(): void;

    /**
     * Get the cache file path.
     */
    public function getCachePath(): string;
}
