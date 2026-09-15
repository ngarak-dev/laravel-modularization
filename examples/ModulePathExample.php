<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Examples;

/**
 * How to resolve paths inside a module.
 *
 * Prefer view/route helpers in application code. These helpers are for
 * filesystem tasks such as reading a module config file.
 */
final class ModulePathExample
{
    /**
     * @return array<string, string>
     */
    public function getModulePaths(string $moduleName): array
    {
        return [
            'modules' => modules_path(),
            'module' => module_path($moduleName),
            'controllers' => module_path($moduleName, 'Http/Controllers'),
            'models' => module_path($moduleName, 'Models'),
            'views' => module_path($moduleName, 'Resources/views'),
            'migrations' => module_path($moduleName, 'Database/Migrations'),
            'config' => module_path($moduleName, 'Config/config.php'),
        ];
    }
}
