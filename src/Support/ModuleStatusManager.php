<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Module;

/**
 * Manages persistent enable/disable state for modules.
 *
 * State is persisted via a .disabled sentinel file inside the module directory.
 * This mechanism is lightweight and does not require a database.
 *
 * When a module has a module.json the 'enabled' key is also updated so that
 * both the manifest and the sentinel file remain consistent.
 */
final class ModuleStatusManager
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Enable a module persistently.
     *
     * @throws ModuleNotFoundException
     */
    public function enable(Module $module): void
    {
        $sentinelPath = $module->path.'/.disabled';

        if ($this->files->exists($sentinelPath)) {
            $this->files->delete($sentinelPath);
        }

        $this->updateManifestEnabled($module, true);
    }

    /**
     * Disable a module persistently.
     *
     * @throws ModuleNotFoundException
     */
    public function disable(Module $module): void
    {
        $sentinelPath = $module->path.'/.disabled';

        if (! $this->files->exists($sentinelPath)) {
            $this->files->put($sentinelPath, (string) json_encode([
                'disabled_at' => now()->toDateTimeString(),
                'disabled_by' => get_current_user(),
            ]));
        }

        $this->updateManifestEnabled($module, false);
    }

    /**
     * Check whether a module is currently enabled on disk.
     */
    public function isEnabled(Module $module): bool
    {
        return ! $this->files->exists($module->path.'/.disabled');
    }

    /**
     * Update the 'enabled' field inside a module.json if one exists.
     */
    private function updateManifestEnabled(Module $module, bool $enabled): void
    {
        $manifestPath = $module->path.'/module.json';

        if (! $this->files->exists($manifestPath)) {
            return;
        }

        $manifest = json_decode($this->files->get($manifestPath), true) ?? [];
        $manifest['enabled'] = $enabled;

        $this->files->put(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
