<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

class CircularModuleDependencyException extends ModuleException
{
    /**
     * @param  array<int, string>  $cycle
     */
    public static function make(array $cycle): self
    {
        $path = implode(' -> ', $cycle);

        return new self(
            "Circular module dependency detected: {$path}. ".
            'Remove the cycle from the affected module.json `requires` lists.'
        );
    }
}
