<?php

if (!function_exists('module_path')) {
    /**
     * Get the path to the specified module.
     *
     * @param string $name The name of the module
     * @param string $path The path to append to the module path
     * @return string
     */
    function module_path($name, $path = '')
    {
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $name;

        return $path ? $modulePath . '/' . ltrim($path, '/') : $modulePath;
    }
}
