<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Module;

/**
 * Discovers modules on the filesystem.
 *
 * Each directory inside the modules root is considered a module.
 * A module may optionally contain a module.json manifest for richer metadata.
 * If no manifest is present the module is described by its directory name alone.
 *
 * A module is considered disabled when EITHER:
 *   - its module.json sets "enabled": false, OR
 *   - a .disabled file exists in the module directory (legacy support).
 */
final class ModuleDiscovery
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $modulesPath,
        private readonly string $defaultNamespace,
    ) {}

    /**
     * Discover all modules in the configured path.
     *
     * @return array<string, Module>
     */
    public function discover(): array
    {
        if (! $this->files->isDirectory($this->modulesPath)) {
            return [];
        }

        $modules = [];

        foreach ($this->files->directories($this->modulesPath) as $directory) {
            $name = basename($directory);

            if (! $this->isValidModuleName($name)) {
                continue;
            }

            $module = $this->loadModule($directory);
            $modules[$module->name] = $module;
        }

        return $modules;
    }

    /**
     * Load a single module from its directory.
     */
    public function loadModule(string $directory): Module
    {
        $manifestPath = $directory.'/module.json';

        if ($this->files->exists($manifestPath)) {
            $manifest = json_decode($this->files->get($manifestPath), true) ?? [];

            // Legacy .disabled file takes precedence over manifest
            if ($this->files->exists($directory.'/.disabled')) {
                $manifest['enabled'] = false;
            }

            return Module::fromManifest($directory, $manifest, $this->defaultNamespace);
        }

        // No manifest – derive from directory
        $enabled = ! $this->files->exists($directory.'/.disabled');

        return Module::fromDirectory($directory, $this->defaultNamespace, $enabled);
    }

    /**
     * Validate a module name to prevent path traversal and injection attacks.
     */
    public function isValidModuleName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name);
    }

    /**
     * Assert a module name is valid, throwing if it is not.
     *
     * @throws InvalidModuleException
     */
    public function assertValidModuleName(string $name): void
    {
        if (! $this->isValidModuleName($name)) {
            throw InvalidModuleException::invalidName($name);
        }
    }
}
