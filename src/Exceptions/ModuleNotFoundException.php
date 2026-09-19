<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

class ModuleNotFoundException extends ModuleException
{
    public static function make(string $name, ?string $modulesPath = null): self
    {
        return new self("Module [{$name}] does not exist!");
    }

    public static function withName(string $name): self
    {
        return self::make($name);
    }
}
