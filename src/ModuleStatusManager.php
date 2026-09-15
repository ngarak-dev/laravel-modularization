<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Support\ModuleName;

/**
 * Persists enable/disable state via a `.disabled` marker and module.json.
 */
final class ModuleStatusManager
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModulePathResolver $paths,
        private readonly ModuleManifest $manifest,
        private readonly ModuleCache $cache,
    ) {}

    public function enable(string $name): bool
    {
        $modulePath = $this->requireModule($name);
        $disabled = $this->paths->join($modulePath, '.disabled');

        if ($this->files->exists($disabled)) {
            $this->files->delete($disabled);
        }

        $this->writeEnabled($modulePath, $name, true);
        $this->cache->forget();

        return true;
    }

    public function disable(string $name): bool
    {
        $modulePath = $this->requireModule($name);
        $disabled = $this->paths->join($modulePath, '.disabled');

        $this->files->put($disabled, json_encode([
            'disabled_at' => date('c'),
            'disabled_by' => function_exists('get_current_user') ? get_current_user() : 'unknown',
        ], JSON_PRETTY_PRINT)."\n");

        $this->writeEnabled($modulePath, $name, false);
        $this->cache->forget();

        return true;
    }

    public function isEnabled(string $name): bool
    {
        $modulePath = $this->paths->path($name);

        if (! $this->files->isDirectory($modulePath)) {
            return false;
        }

        if ($this->files->exists($this->paths->join($modulePath, '.disabled'))) {
            return false;
        }

        $meta = $this->manifest->read($modulePath, ModuleName::parse($name)->studly());

        return (bool) ($meta['enabled'] ?? true);
    }

    private function requireModule(string $name): string
    {
        $modulePath = $this->paths->path($name);

        if (! $this->files->isDirectory($modulePath)) {
            throw ModuleNotFoundException::make($name, $this->paths->modulesPath());
        }

        return $modulePath;
    }

    private function writeEnabled(string $modulePath, string $name, bool $enabled): void
    {
        $jsonPath = $this->paths->join($modulePath, 'module.json');

        if (! $this->files->isFile($jsonPath)) {
            return;
        }

        $meta = $this->manifest->read($modulePath, $name);
        $meta['enabled'] = $enabled;
        unset($meta['path'], $meta['manifest_path']);

        $this->manifest->write($modulePath, $meta);
    }
}
