<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

/**
 * Exception thrown when module dependencies cannot be satisfied.
 */
class ModuleDependencyException extends ModuleException
{
    /**
     * @var array<string>
     */
    protected array $missingDependencies = [];

    public static function missingDependency(string $moduleName, string $dependencyName): self
    {
        $exception = new self(
            "Module [{$moduleName}] depends on [{$dependencyName}], but it is not installed or enabled.",
            $moduleName
        );
        $exception->missingDependencies = [$dependencyName];
        return $exception;
    }

    /**
     * @param array<string> $dependencies
     */
    public static function missingDependencies(string $moduleName, array $dependencies): self
    {
        $depList = implode(', ', $dependencies);
        $exception = new self(
            "Module [{$moduleName}] has unsatisfied dependencies: [{$depList}]. Install and enable these modules first.",
            $moduleName
        );
        $exception->missingDependencies = $dependencies;
        return $exception;
    }

    public static function dependencyDisabled(string $moduleName, string $dependencyName): self
    {
        $exception = new self(
            "Module [{$moduleName}] depends on [{$dependencyName}], but it is disabled. Enable [{$dependencyName}] first.",
            $moduleName
        );
        $exception->missingDependencies = [$dependencyName];
        return $exception;
    }

    /**
     * Get the list of missing dependencies.
     *
     * @return array<string>
     */
    public function getMissingDependencies(): array
    {
        return $this->missingDependencies;
    }
}
