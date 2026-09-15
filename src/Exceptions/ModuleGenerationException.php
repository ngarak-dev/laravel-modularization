<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

final class ModuleGenerationException extends ModuleException
{
    public static function stubNotFound(string $stubPath): static
    {
        return new self(
            "Stub file not found at [{$stubPath}]. "
            ."Run 'php artisan module:publish-stubs' to publish stubs, or check that the package is installed correctly.",
        );
    }

    public static function cannotWriteFile(string $path): static
    {
        return new self(
            "Cannot write generated file to [{$path}]. Check directory permissions.",
        );
    }
}
