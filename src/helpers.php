<?php

declare(strict_types=1);

if (! function_exists('module_path')) {
    /**
     * Get the absolute path to a module, or a path within it.
     *
     * Examples:
     *   module_path('Products')                      → /path/to/app/modules/Products
     *   module_path('Products', 'Resources/views')   → /path/to/app/modules/Products/Resources/views
     *
     * @param  string  $name  Module name (StudlyCase)
     * @param  string  $path  Optional sub-path within the module
     */
    function module_path(string $name, string $path = ''): string
    {
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath.'/'.$name;

        return $path !== '' ? $modulePath.'/'.ltrim($path, '/') : $modulePath;
    }
}
