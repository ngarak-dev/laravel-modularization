<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Facades;

use Illuminate\Support\Facades\Facade;
use NgarakDev\Modularization\ModularizationService;

/**
 * @method static array<string, array<string, mixed>> getModules()
 * @method static bool hasModule(string $name)
 * @method static bool isEnabled(string $name)
 * @method static bool enable(string $name)
 * @method static bool disable(string $name)
 * @method static string path(?string $module = null, string $path = '')
 * @method static string cache()
 * @method static bool clearCache()
 * @method static \NgarakDev\Modularization\ModuleManager manager()
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
