<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

use Illuminate\Support\Collection;

/**
 * Discovers modules from the filesystem.
 */
interface ModuleDiscoveryInterface
{
    /**
     * Discover all modules.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function discover(): Collection;

    /**
     * Discover a single module by name.
     */
    public function discoverModule(string $name): ?ModuleInterface;

    /**
     * Get the base path where modules are stored.
     */
    public function getBasePath(): string;

    /**
     * Check if a module directory exists.
     */
    public function exists(string $name): bool;
}
