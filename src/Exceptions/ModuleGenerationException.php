<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

/**
 * Exception thrown when module or component generation fails.
 */
class ModuleGenerationException extends ModuleException
{
    public static function alreadyExists(string $moduleName): self
    {
        return new self(
            "Module [{$moduleName}] already exists. Use --force to overwrite.",
            $moduleName
        );
    }

    public static function directoryCreationFailed(string $path, string $reason = ''): self
    {
        $message = "Failed to create directory [{$path}]";
        if ($reason) {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    public static function fileCreationFailed(string $path, string $reason = ''): self
    {
        $message = "Failed to create file [{$path}]";
        if ($reason) {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    public static function stubNotFound(string $stubName): self
    {
        return new self(
            "Stub [{$stubName}] not found. Ensure the stub file exists in the stubs directory."
        );
    }
}
