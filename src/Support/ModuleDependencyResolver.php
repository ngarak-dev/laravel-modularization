<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use NgarakDev\Modularization\Exceptions\ModuleDependencyException;
use NgarakDev\Modularization\Module;

/**
 * Resolves module load order based on declared dependencies.
 *
 * Validates that all required modules exist and are enabled,
 * detects circular dependencies, and returns modules in safe boot order.
 */
final class ModuleDependencyResolver
{
    /** @param array<string, Module> $modules */
    public function __construct(private readonly array $modules) {}

    /**
     * Return modules sorted in dependency-safe load order.
     *
     * @return array<string, Module>
     *
     * @throws ModuleDependencyException
     */
    public function resolve(): array
    {
        $this->validate();

        $ordered = [];
        $visited = [];

        foreach ($this->modules as $name => $module) {
            $this->visit($name, $visited, $ordered, []);
        }

        return $ordered;
    }

    /**
     * Validate that all dependencies exist and are enabled.
     *
     * @throws ModuleDependencyException
     */
    public function validate(): void
    {
        foreach ($this->modules as $name => $module) {
            foreach ($module->requires as $dependency) {
                if (! isset($this->modules[$dependency])) {
                    throw ModuleDependencyException::missing($name, $dependency);
                }

                if (! $this->modules[$dependency]->isEnabled()) {
                    throw ModuleDependencyException::missing($name, $dependency);
                }
            }
        }
    }

    /**
     * @param  array<string, bool>  $visited
     * @param  array<string, Module>  $ordered
     * @param  string[]  $chain
     *
     * @throws ModuleDependencyException
     */
    private function visit(string $name, array &$visited, array &$ordered, array $chain): void
    {
        if (isset($ordered[$name])) {
            return;
        }

        if (in_array($name, $chain, true)) {
            throw ModuleDependencyException::circular($name, $chain);
        }

        if (! isset($this->modules[$name])) {
            return;
        }

        $chain[] = $name;

        foreach ($this->modules[$name]->requires as $dep) {
            $this->visit($dep, $visited, $ordered, $chain);
        }

        $ordered[$name] = $this->modules[$name];
    }
}
