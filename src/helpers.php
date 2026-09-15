<?php

use NgarakDev\Modularization\Support\ModulePathResolver;

if (!function_exists('module_path')) {
    /**
     * Get the path to the specified module.
     *
     * @param string $name The name of the module
     * @param string $path The path to append to the module path
     * @return string
     */
    function module_path(string $name, string $path = ''): string
    {
        $resolver = new ModulePathResolver(
            base_path((string) config('modularization.modules_path', 'modules'))
        );

        return $path === '' ? $resolver->module($name) : $resolver->child($name, $path);
    }
}
