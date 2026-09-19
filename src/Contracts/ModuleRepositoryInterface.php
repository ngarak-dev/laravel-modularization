<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;

/**
 * Repository for discovering and querying modules.
 */
interface ModuleRepositoryInterface
{
    /**
     * @return array<string, ModuleInterface>
     */
    public function all(): array;

    /**
     * @return array<string, ModuleInterface>
     */
    public function enabled(): array;

    /**
     * @return array<string, ModuleInterface>
     */
    public function disabled(): array;

    public function find(string $name): ?ModuleInterface;

    /**
     * @throws ModuleNotFoundException
     */
    public function findOrFail(string $name): ModuleInterface;

    public function has(string $name): bool;

    public function count(): int;

    /**
     * @return array<string, ModuleInterface>
     */
    public function getOrderedByDependencies(): array;
}
