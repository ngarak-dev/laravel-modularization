<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

final class ModuleNotFoundException extends ModuleException
{
    public static function forModule(string $name): static
    {
        return new self("Module [{$name}] was not found. Ensure it exists in the modules directory.");
    }
}
