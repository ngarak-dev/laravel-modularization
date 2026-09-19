<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

/**
 * Discovers modules from the filesystem or compiled cache.
 */
interface ModuleDiscoveryInterface
{
    /**
     * @return array<string, ModuleInterface>
     */
    public function discover(bool $useCache = true): array;

    /**
     * @return array<string, ModuleInterface>
     */
    public function scan(): array;

    public function isValidModuleName(string $name): bool;

    public function exists(string $name): bool;
}
