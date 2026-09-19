<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

use Illuminate\Support\Collection;

/**
 * Repository for managing module instances.
 */
interface ModuleRepositoryInterface
{
    /**
     * Get all discovered modules.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function all(): Collection;

    /**
     * Get all enabled modules.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function enabled(): Collection;

    /**
     * Get all disabled modules.
     *
     * @return Collection<string, ModuleInterface>
     */
    public function disabled(): Collection;

    /**
     * Find a module by name.
     */
    public function find(string $name): ?ModuleInterface;

    /**
     * Find a module by name or throw an exception.
     *
     * @throws \NgarakDev\Modularization\Exceptions\ModuleNotFoundException
     */
    public function findOrFail(string $name): ModuleInterface;

    /**
     * Check if a module exists.
     */
    public function has(string $name): bool;

    /**
     * Register a module.
     */
    public function register(ModuleInterface $module): void;

    /**
     * Get modules ordered by their dependencies.
     *
     * @return Collection<string, ModuleInterface>
     * @throws \NgarakDev\Modularization\Exceptions\CircularDependencyException
     */
    public function getOrderedByDependencies(): Collection;

    /**
     * Get the count of all modules.
     */
    public function count(): int;
}
