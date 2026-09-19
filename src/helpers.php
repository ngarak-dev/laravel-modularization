<?php

declare(strict_types=1);

use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Support\ModuleName;

if (! function_exists('module_path')) {
    /**
     * Get the path to the specified module, or a path inside it.
     */
    function module_path(string $name, string $path = ''): string
    {
        if (function_exists('app') && app()->bound('modularization')) {
            return app('modularization')->path($name, $path);
        }

        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $module = ModuleName::parse($name)->studly();

        return $path === ''
            ? $modulesPath.DIRECTORY_SEPARATOR.$module
            : $modulesPath.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}

if (! function_exists('modules_path')) {
    /**
     * Get the configured modules directory.
     */
    function modules_path(string $path = ''): string
    {
        if (function_exists('app') && app()->bound('modularization')) {
            return app('modularization')->path(null, $path);
        }

        $base = base_path(config('modularization.modules_path', 'modules'));

        return $path === ''
            ? $base
            : $base.DIRECTORY_SEPARATOR.ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}

if (! function_exists('modules')) {
    /**
     * Get the module manager.
     */
    function modules(): ModuleManager
    {
        return app(ModuleManager::class);
    }
}

if (! function_exists('module')) {
    /**
     * Get a module by name.
     */
    function module(string $name): ?ModuleInterface
    {
        return modules()->find($name);
    }
}

if (! function_exists('module_enabled')) {
    /**
     * Whether the named module exists and is enabled.
     */
    function module_enabled(string $name): bool
    {
        return modules()->isEnabled($name);
    }
}
