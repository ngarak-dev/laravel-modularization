<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

class ModuleGenerationException extends ModuleException
{
    public static function make(string $what, string $reason): self
    {
        return new self("Unable to generate {$what}: {$reason}");
    }

    public static function alreadyExists(string $path): self
    {
        return new self(
            "File already exists at [{$path}]. Re-run with --force to overwrite."
        );
    }
}
