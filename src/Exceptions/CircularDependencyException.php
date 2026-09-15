<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

/**
 * Exception thrown when circular dependencies are detected between modules.
 */
class CircularDependencyException extends ModuleException
{
    /**
     * @var array<string>
     */
    protected array $dependencyChain = [];

    /**
     * @param array<string> $chain The dependency chain that forms the cycle
     */
    public static function detected(array $chain): self
    {
        $chainStr = implode(' -> ', $chain);
        $moduleName = $chain[0] ?? 'Unknown';

        $exception = new self(
            "Circular dependency detected: {$chainStr}. Review your module dependencies and remove the circular reference.",
            $moduleName
        );
        $exception->dependencyChain = $chain;
        return $exception;
    }

    /**
     * Get the dependency chain that forms the cycle.
     *
     * @return array<string>
     */
    public function getDependencyChain(): array
    {
        return $this->dependencyChain;
    }
}
