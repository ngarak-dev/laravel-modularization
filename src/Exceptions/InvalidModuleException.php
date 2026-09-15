<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

/**
 * Exception thrown when a module has invalid structure or configuration.
 */
class InvalidModuleException extends ModuleException
{
    public static function missingProvider(string $moduleName): self
    {
        return new self(
            "Module [{$moduleName}] is missing a service provider. Expected provider class in Providers/{$moduleName}ServiceProvider.php",
            $moduleName
        );
    }

    public static function invalidManifest(string $moduleName, string $reason): self
    {
        return new self(
            "Module [{$moduleName}] has an invalid module.json manifest: {$reason}",
            $moduleName
        );
    }

    public static function invalidStructure(string $moduleName, string $reason): self
    {
        return new self(
            "Module [{$moduleName}] has an invalid structure: {$reason}",
            $moduleName
        );
    }

    public static function invalidName(string $moduleName, string $reason): self
    {
        return new self(
            "Invalid module name [{$moduleName}]: {$reason}",
            $moduleName
        );
    }
}
