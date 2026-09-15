<?php

declare(strict_types=1);

use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Support\ModulePathResolver;

if (!function_exists('module_path')) {
    /**
     * Get the path to a module or a path within a module.
     *
     * @param string $name The name of the module
     * @param string $path Optional path within the module
     * @return string The full path to the module or the path within it
     */
    function module_path(string $name, string $path = ''): string
    {
        /** @var ModulePathResolver $resolver */
        $resolver = app(ModulePathResolver::class);

        return $resolver->path($name, $path);
    }
}

if (!function_exists('modules')) {
    /**
     * Get the module manager instance.
     *
     * @return ModuleManager
     */
    function modules(): ModuleManager
    {
        return app(ModuleManager::class);
    }
}

if (!function_exists('module')) {
    /**
     * Get a module by name.
     *
     * @param string $name The name of the module
     * @return \NgarakDev\Modularization\Contracts\ModuleInterface|null
     */
    function module(string $name): ?\NgarakDev\Modularization\Contracts\ModuleInterface
    {
        return modules()->find($name);
    }
}

if (!function_exists('module_enabled')) {
    /**
     * Check if a module is enabled.
     *
     * @param string $name The name of the module
     * @return bool
     */
    function module_enabled(string $name): bool
    {
        return modules()->isEnabled($name);
    }
}
