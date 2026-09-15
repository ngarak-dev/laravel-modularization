<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\ModuleManager;

/**
 * Facade for the module manager.
 *
 * @method static Collection<string, ModuleInterface> all()
 * @method static Collection<string, ModuleInterface> enabled()
 * @method static Collection<string, ModuleInterface> disabled()
 * @method static ModuleInterface|null find(string $name)
 * @method static ModuleInterface findOrFail(string $name)
 * @method static bool has(string $name)
 * @method static bool isEnabled(string $name)
 * @method static bool isDisabled(string $name)
 * @method static void enable(string $name)
 * @method static void disable(string $name)
 * @method static bool toggle(string $name)
 * @method static int count()
 * @method static void cache()
 * @method static void clearCache()
 * @method static bool isCached()
 * @method static void refresh()
 * @method static array getStatusSummary()
 *
 * @see \NgarakDev\Modularization\ModuleManager
 */
class Modularization extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ModuleManager::class;
    }
}
