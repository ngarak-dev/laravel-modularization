<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Contracts\ModuleStatusManagerInterface;
use NgarakDev\Modularization\Events\ModuleBroken;
use NgarakDev\Modularization\Events\ModuleDisabled;
use NgarakDev\Modularization\Events\ModuleEnabled;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Support\ModuleName;

/**
 * Persists enable/disable state via a `.disabled` marker and module.json.
 */
final class ModuleStatusManager implements ModuleStatusManagerInterface
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

        if (function_exists('event')) {
            event(new ModuleEnabled(new Module(
                name: $name,
                path: $modulePath,
                namespace: '',
                enabled: true,
                lifecycle: 'enabled',
            )));
        }

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

        if (function_exists('event')) {
            event(new ModuleDisabled(new Module(
                name: $name,
                path: $modulePath,
                namespace: '',
                enabled: false,
                lifecycle: 'disabled',
            )));
        }

        return true;
    }

    public function markInstalling(string $name): bool
    {
        $modulePath = $this->requireModule($name);
        $this->files->put($this->paths->join($modulePath, '.installing'), date('c')."\n");
        $this->cache->forget();

        return true;
    }

    public function markBroken(string $name, string $reason = ''): bool
    {
        $modulePath = $this->requireModule($name);
        $this->files->put($this->paths->join($modulePath, '.broken'), ($reason !== '' ? $reason : 'broken')."\n");
        $this->cache->forget();

        if (function_exists('event')) {
            event(new ModuleBroken(new Module(
                name: $name,
                path: $modulePath,
                namespace: '',
                enabled: false,
                valid: false,
                lifecycle: 'broken',
                invalidReason: $reason !== '' ? $reason : 'module marked broken',
            ), $reason));
        }

        return true;
    }

    public function clearLifecycleMarkers(string $name): bool
    {
        $modulePath = $this->requireModule($name);
        $this->files->delete($this->paths->join($modulePath, '.installing'));
        $this->files->delete($this->paths->join($modulePath, '.broken'));
        $this->cache->forget();

        return true;
    }

    public function isDisabled(string $name): bool
    {
        return ! $this->isEnabled($name);
    }

    public function toggle(string $name): bool
    {
        if ($this->isEnabled($name)) {
            $this->disable($name);

            return false;
        }

        $this->enable($name);

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
