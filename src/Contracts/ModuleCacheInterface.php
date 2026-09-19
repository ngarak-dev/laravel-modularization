<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

/**
 * Persists discovered module metadata.
 */
interface ModuleCacheInterface
{
    public function exists(): bool;

    /**
     * @return array<string, ModuleInterface>|null
     */
    public function get(): ?array;

    /**
     * @param  array<string, ModuleInterface>  $modules
     */
    public function put(array $modules): string;

    public function forget(): bool;

    public function getCachePath(): string;
}
