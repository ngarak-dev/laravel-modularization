<?php

namespace NgarakDev\Modularization\Examples;

/**
 * This file demonstrates how to use the module_path() helper function 
 * provided by the Laravel Modularization package.
 */
class ModulePathExample
{
    /**
     * Example of using module_path() function.
     *
     * @param string $moduleName
     * @return array
     */
    public function getModulePaths(string $moduleName): array
    {
        // Get the base path to the module
        $modulePath = module_path($moduleName);

        // Get path to various directories within the module
        $paths = [
            'module' => $modulePath,
            'controllers' => module_path($moduleName, 'Http/Controllers'),
            'models' => module_path($moduleName, 'Models'),
            'views' => module_path($moduleName, 'Resources/views'),
            'migrations' => module_path($moduleName, 'Database/Migrations'),
            'config' => module_path($moduleName, 'Config/config.php'),
        ];

        return $paths;
    }

    /**
     * Example of loading a view using module_path().
     *
     * @param string $moduleName
     * @param string $view
     * @return string
     */
    public function loadModuleView(string $moduleName, string $view): string
    {
        $viewPath = module_path($moduleName, "Resources/views/{$view}.blade.php");

        if (file_exists($viewPath)) {
            // In a real application, you would use Laravel's view() helper
            // This is just to demonstrate the path construction
            return "View loaded from: {$viewPath}";
        }

        return "View not found at: {$viewPath}";
    }
}
