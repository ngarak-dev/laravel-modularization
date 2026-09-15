<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

/**
 * Exception thrown when a module cannot be found.
 */
class ModuleNotFoundException extends ModuleException
{
    public static function withName(string $moduleName): self
    {
        return new self(
            "Module [{$moduleName}] not found. Ensure the module exists in your modules directory and the name is correct.",
            $moduleName
        );
    }

    public static function withPath(string $path): self
    {
        return new self(
            "Module not found at path [{$path}]. Ensure the directory exists and contains a valid module structure."
        );
    }
}
