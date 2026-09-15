<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use NgarakDev\Modularization\Exceptions\CircularModuleDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;

/**
 * Validates module requirements and produces a safe load order.
 */
final class DependencyResolver
{
    /**
     * @param  array<string, Module>  $modules
     * @return array<string, Module>
     */
    public function sort(array $modules, bool $failOnMissing = true): array
    {
        $this->assertNoCycles($modules);

        if ($failOnMissing) {
            $this->assertDependenciesExist($modules);
        }

        $sorted = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $name) use (&$visit, &$sorted, &$visiting, &$visited, $modules, $failOnMissing): void {
            if (isset($visited[$name])) {
                return;
            }

            if (! isset($modules[$name])) {
                return;
            }

            $visiting[$name] = true;

            foreach ($modules[$name]->requires as $dependency) {
                if (! isset($modules[$dependency])) {
                    if ($failOnMissing) {
                        throw ModuleDependencyException::missing($name, [$dependency]);
                    }

                    continue;
                }

                $visit($dependency);
            }

            unset($visiting[$name]);
            $visited[$name] = true;
            $sorted[$name] = $modules[$name];
        };

        foreach (array_keys($modules) as $name) {
            $visit($name);
        }

        return $sorted;
    }

    /**
     * @param  array<string, Module>  $modules
     * @return array<int, string>
     */
    public function missingDependencies(Module $module, array $modules): array
    {
        $missing = [];

        foreach ($module->requires as $dependency) {
            if (! isset($modules[$dependency]) || ! $modules[$dependency]->enabled || ! $modules[$dependency]->valid) {
                $missing[] = $dependency;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, Module>  $modules
     */
    private function assertDependenciesExist(array $modules): void
    {
        foreach ($modules as $module) {
            if (! $module->enabled) {
                continue;
            }

            $missing = [];

            foreach ($module->requires as $dependency) {
                if (! isset($modules[$dependency])) {
                    $missing[] = $dependency;
                }
            }

            if ($missing !== []) {
                throw ModuleDependencyException::missing($module->name, $missing);
            }
        }
    }

    /**
     * @param  array<string, Module>  $modules
     */
    private function assertNoCycles(array $modules): void
    {
        $state = [];

        $visit = function (string $name, array $stack) use (&$visit, &$state, $modules): void {
            if (! isset($modules[$name])) {
                return;
            }

            $state[$name] = 'visiting';
            $stack[] = $name;

            foreach ($modules[$name]->requires as $dependency) {
                if (! isset($modules[$dependency])) {
                    continue;
                }

                if (($state[$dependency] ?? null) === 'visiting') {
                    $cycle = array_slice($stack, (int) array_search($dependency, $stack, true));
                    $cycle[] = $dependency;
                    throw CircularModuleDependencyException::make($cycle);
                }

                if (($state[$dependency] ?? null) !== 'visited') {
                    $visit($dependency, $stack);
                }
            }

            $state[$name] = 'visited';
        };

        foreach (array_keys($modules) as $name) {
            if (! isset($state[$name])) {
                $visit($name, []);
            }
        }
    }
}
