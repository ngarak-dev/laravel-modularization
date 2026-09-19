<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Events;

use NgarakDev\Modularization\Module;

final class ModuleEnabled
{
    public function __construct(public readonly Module $module) {}
}
