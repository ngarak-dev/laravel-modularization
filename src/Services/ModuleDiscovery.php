<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleDiscoveryInterface;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Support\Module;
use NgarakDev\Modularization\Support\ModulePathResolver;

/**
 * Discovers modules from the filesystem.
 */
final class ModuleDiscovery implements ModuleDiscoveryInterface
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly ModulePathResolver $pathResolver,
    ) {
    }

    /**
     * @return Collection<string, ModuleInterface>
     */
    public function discover(): Collection
    {
        $basePath = $this->getBasePath();

        if (!$this->files->isDirectory($basePath)) {
            return collect();
        }

        $directories = $this->files->directories($basePath);
        $modules = collect();

        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            $module = $this->createModuleFromPath($moduleName, $directory);

            if ($module !== null) {
                $modules->put($moduleName, $module);
            }
        }

        return $modules;
    }

    public function discoverModule(string $name): ?ModuleInterface
    {
        $path = $this->pathResolver->getModulePath($name);

        if (!$this->files->isDirectory($path)) {
            return null;
        }

        return $this->createModuleFromPath($name, $path);
    }

    public function getBasePath(): string
    {
        return $this->pathResolver->getBasePath();
    }

    public function exists(string $name): bool
    {
        return $this->pathResolver->exists($name);
    }

    /**
     * Create a Module instance from a directory path.
     */
    private function createModuleFromPath(string $name, string $path): ?Module
    {
        $manifest = $this->loadManifest($path);
        $enabled = $this->isModuleEnabled($path, $manifest);
        $namespace = $this->pathResolver->getModuleNamespace($name);

        return new Module(
            name: $manifest['name'] ?? $name,
            path: $path,
            namespace: $namespace,
            enabled: $enabled,
            manifest: $manifest,
        );
    }

    /**
     * Load the module manifest (module.json or Config/config.php).
     *
     * @return array<string, mixed>
     */
    private function loadManifest(string $path): array
    {
        $manifest = [];

        $moduleJsonPath = $path . '/module.json';
        if ($this->files->exists($moduleJsonPath)) {
            $content = $this->files->get($moduleJsonPath);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $manifest = $decoded;
            }
        }

        $configPath = $path . '/Config/config.php';
        if ($this->files->exists($configPath)) {
            $config = require $configPath;
            if (is_array($config)) {
                $manifest = array_merge($manifest, $config);
            }
        }

        return $manifest;
    }

    /**
     * Determine if a module is enabled.
     *
     * @param array<string, mixed> $manifest
     */
    private function isModuleEnabled(string $path, array $manifest): bool
    {
        if ($this->files->exists($path . '/.disabled')) {
            return false;
        }

        return $manifest['enabled'] ?? true;
    }
}
