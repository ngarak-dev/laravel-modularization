<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Support\ModulePathResolver;

final class ModuleDiscovery
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModulePathResolver $paths,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function discover(): array
    {
        if (! $this->files->isDirectory($this->paths->basePath())) {
            return [];
        }

        $modules = [];
        $directories = $this->files->directories($this->paths->basePath());
        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($directories as $directory) {
            $name = basename($directory);
            try {
                $normalized = $this->paths->normalizeName($name);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $manifest = $this->readManifest($directory);
            $modules[$normalized] = array_merge([
                'name' => $normalized,
                'path' => $directory,
                'enabled' => ! $this->files->exists($directory . DIRECTORY_SEPARATOR . '.disabled'),
                'requires' => [],
            ], $manifest);
            $modules[$normalized]['name'] = $normalized;
            $modules[$normalized]['path'] = $directory;
            $modules[$normalized]['enabled'] = ! $this->files->exists($directory . DIRECTORY_SEPARATOR . '.disabled');
            $modules[$normalized]['requires'] = array_values(array_filter(
                (array) ($modules[$normalized]['requires'] ?? [])
            ));
        }

        return $modules;
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $path): array
    {
        $manifestPath = $path . DIRECTORY_SEPARATOR . 'module.json';
        if (! $this->files->exists($manifestPath)) {
            return [];
        }

        $manifest = json_decode($this->files->get($manifestPath), true);
        if (! is_array($manifest)) {
            return [];
        }

        return $manifest;
    }
}
