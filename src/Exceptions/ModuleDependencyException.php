<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

final class ModuleDependencyException extends ModuleException
{
    public static function missing(string $module, string $dependency): static
    {
        return new self(
            "Module [{$module}] requires module [{$dependency}], but [{$dependency}] is not installed or is disabled. "
            ."Install the [{$dependency}] module or check its enabled status with 'php artisan module:list'.",
        );
    }

    /** @param string[] $chain */
    public static function circular(string $module, array $chain): static
    {
        $path = implode(' → ', $chain);

        return new self(
            "Circular dependency detected for module [{$module}]: {$path} → {$module}. "
            ."Review your module.json 'requires' declarations to remove the circular reference.",
        );
    }
}
