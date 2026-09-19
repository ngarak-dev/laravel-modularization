<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;

/**
 * Serializes module metadata so applications can share a registry file.
 */
final class ModuleRegistry
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
    ) {}

    /**
     * @param  array<string, Module>  $modules
     * @return array<string, mixed>
     */
    public function export(array $modules): array
    {
        $entries = [];

        foreach ($modules as $name => $module) {
            $entries[$name] = [
                'name' => $module->name,
                'version' => $module->version,
                'namespace' => $module->namespace,
                'description' => $module->description,
                'enabled' => $module->enabled,
                'status' => $module->status(),
                'requires' => $module->requires,
                'provider' => $module->provider,
            ];
        }

        return [
            'generated_at' => date('c'),
            'namespace' => $this->configuration->namespace(),
            'modules_path' => $this->configuration->modulesPathRelative(),
            'modules' => $entries,
        ];
    }

    /**
     * @param  array<string, Module>  $modules
     */
    public function write(array $modules, ?string $path = null): string
    {
        $path ??= $this->configuration->registryPath();
        $this->files->ensureDirectoryExists(dirname($path), 0755);
        $this->files->put(
            $path,
            json_encode($this->export($modules), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public function read(?string $path = null): array
    {
        $path ??= $this->configuration->registryPath();

        if (! $this->files->isFile($path)) {
            return [];
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
