<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

class ModuleDependencyException extends ModuleException
{
    /**
     * @var array<int, string>
     */
    protected array $missingDependencies = [];

    /**
     * @param  array<int, string>|string  $missing
     */
    public static function missing(string $module, array|string $missing): self
    {
        if (is_string($missing)) {
            $exception = new self(
                "Module [{$module}] requires module [{$missing}], but [{$missing}] is not installed or is disabled. ".
                "Install the [{$missing}] module or check its enabled status with 'php artisan module:list'."
            );
            $exception->missingDependencies = [$missing];

            return $exception;
        }

        return self::missingDependencies($module, $missing);
    }

    /**
     * @param  array<int, string>  $missing
     */
    public static function missingDependencies(string $module, array $missing): self
    {
        $list = implode(', ', $missing);
        $exception = new self(
            "Module [{$module}] requires missing module(s): {$list}. ".
            'Install or enable the required module(s), or remove them from module.json.'
        );
        $exception->missingDependencies = $missing;

        return $exception;
    }

    public static function dependencyDisabled(string $module, string $dependency): self
    {
        $exception = new self(
            "Module [{$module}] requires [{$dependency}], which is disabled. Enable it first or remove the requirement."
        );
        $exception->missingDependencies = [$dependency];

        return $exception;
    }

    /**
     * @param  array<int, string>  $chain
     */
    public static function circular(string $module, array $chain): self
    {
        $path = implode(' → ', $chain);

        return new self(
            "Circular dependency detected for module [{$module}]: {$path} → {$module}. ".
            "Review your module.json 'requires' declarations to remove the circular reference."
        );
    }

    /**
     * @return array<int, string>
     */
    public function getMissingDependencies(): array
    {
        return $this->missingDependencies;
    }
}
