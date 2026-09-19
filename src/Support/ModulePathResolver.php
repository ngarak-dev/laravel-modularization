<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use Illuminate\Contracts\Foundation\Application;
use NgarakDev\Modularization\Exceptions\InvalidModuleNameException;

/**
 * Resolves and validates module filesystem paths.
 *
 * Accepts either a Laravel application (contract/services API) or a raw modules
 * base path (hardening API used by unit tests).
 */
final class ModulePathResolver
{
    public function __construct(
        private readonly Application|string $appOrBasePath,
    ) {}

    public function basePath(): string
    {
        return $this->getBasePath();
    }

    public function getBasePath(): string
    {
        if ($this->appOrBasePath instanceof Application) {
            $configPath = (string) $this->appOrBasePath['config']->get('modularization.modules_path', 'modules');

            if (str_starts_with($configPath, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $configPath) === 1) {
                return $configPath;
            }

            return $this->appOrBasePath->basePath($configPath);
        }

        return $this->appOrBasePath;
    }

    public function module(string $name): string
    {
        $normalized = $this->normalizeName($name);

        return $this->getBasePath().DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $normalized);
    }

    public function getModulePath(string $moduleName): string
    {
        return $this->module($moduleName);
    }

    public function normalizeName(string $name): string
    {
        $name = trim(str_replace('/', '\\', $name), '\\');

        if ($name === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)*$/', $name)) {
            throw new InvalidModuleNameException(
                "Invalid module name [{$name}]. Use letters, numbers, and namespace separators only."
            );
        }

        return implode('\\', array_map(static fn (string $part): string => ucfirst($part), explode('\\', $name)));
    }

    public function child(string $module, string $relativePath): string
    {
        $path = $this->module($module);
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));

        if (in_array('..', explode(DIRECTORY_SEPARATOR, $relativePath), true) || str_contains($relativePath, "\0")) {
            throw new InvalidModuleNameException('The requested module path contains an unsafe segment.');
        }

        $candidate = $path.DIRECTORY_SEPARATOR.$relativePath;
        $root = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($candidate, $root) && $candidate !== rtrim($path, DIRECTORY_SEPARATOR)) {
            throw new InvalidModuleNameException('The requested module path escapes its module directory.');
        }

        return $candidate;
    }

    public function path(string $moduleName, string $subPath = ''): string
    {
        if ($subPath === '') {
            return $this->getModulePath($moduleName);
        }

        return $this->child($moduleName, $subPath);
    }

    public function getNamespace(): string
    {
        if ($this->appOrBasePath instanceof Application) {
            return (string) $this->appOrBasePath['config']->get('modularization.namespace', 'Modules');
        }

        return 'Modules';
    }

    public function getModuleNamespace(string $moduleName): string
    {
        return $this->getNamespace().'\\'.$moduleName;
    }

    public function getRoutesPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Routes');
    }

    public function getViewsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Resources/views');
    }

    public function getTranslationsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Resources/lang');
    }

    public function getMigrationsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Database/Migrations');
    }

    public function getConfigPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Config');
    }

    public function getLivewirePath(string $moduleName): string
    {
        return $this->path($moduleName, 'Livewire');
    }

    public function getAssetsPath(string $moduleName): string
    {
        return $this->path($moduleName, 'Resources/assets');
    }

    public function exists(string $moduleName): bool
    {
        try {
            return is_dir($this->getModulePath($moduleName));
        } catch (InvalidModuleNameException) {
            return false;
        }
    }
}
