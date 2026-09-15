<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

class ModuleDependencyException extends ModuleException
{
    /**
     * @param  array<int, string>  $missing
     */
    public static function missing(string $module, array $missing): self
    {
        $list = implode(', ', $missing);

        return new self(
            "Module [{$module}] requires missing module(s): {$list}. ".
            'Install or enable the required module(s), or remove them from module.json.'
        );
    }
}
