<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

final class InvalidModuleException extends ModuleException
{
    public static function invalidName(string $name): static
    {
        return new self(
            "Module name [{$name}] is invalid. Module names must be alphanumeric and may contain underscores or hyphens, and cannot traverse directory paths.",
        );
    }

    public static function missingProvider(string $module, string $providerClass): static
    {
        return new self(
            "Module [{$module}] declares provider [{$providerClass}] but the class does not exist. "
            ."Run 'composer dump-autoload' or verify the module namespace in config/modularization.php.",
        );
    }
}
