<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Facades;

use Illuminate\Support\Facades\Facade;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Module;

/**
 * @method static array<string, Module> getModules()
 * @method static array<string, Module> getEnabledModules()
 * @method static array<string, Module> getDisabledModules()
 * @method static Module findOrFail(string $name)
 * @method static Module|null find(string $name)
 * @method static bool hasModule(string $name)
 * @method static bool isEnabled(string $name)
 * @method static void enable(string $name)
 * @method static void disable(string $name)
 * @method static void refresh()
 *
 * @see ModularizationService
 */
class Modularization extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'modularization';
    }
}
