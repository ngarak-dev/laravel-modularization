<?php

namespace NgarakDev\Modularization\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getModules()
 * @method static bool hasModule(string $name)
 * @method static bool isEnabled(string $name)
 * @method static bool enable(string $name)
 * @method static bool disable(string $name)
 *
 * @see \NgarakDev\Modularization\ModularizationService
 */
class Modularization extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'modularization';
    }
}
