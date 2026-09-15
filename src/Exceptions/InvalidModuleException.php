<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

class InvalidModuleException extends ModuleException
{
    public static function make(string $name, string $reason, ?string $hint = null): self
    {
        $message = "Module [{$name}] is invalid: {$reason}";

        if ($hint !== null) {
            $message .= " {$hint}";
        }

        return new self($message);
    }

    public static function invalidName(string $name): self
    {
        return new self(
            "[{$name}] is not a valid module name. Use a PHP class name such as Billing or UserProfile. ".
            'Path separators, dots, and special characters are not allowed.'
        );
    }
}
