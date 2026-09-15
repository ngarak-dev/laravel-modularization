<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleInterface;

/**
 * Legacy service class for backwards compatibility.
 *
 * @deprecated Use ModuleManager instead. This class will be removed in v2.0.
 */
class ModularizationService
{
    public function __construct(
        private readonly ModuleManager $manager,
    ) {
    }

    /**
     * Get all modules.
     *
     * @return array<string, array{name: string, path: string, enabled: bool}>
     * @deprecated Use ModuleManager::all() instead
     */
    public function getModules(): array
    {
        return $this->manager->all()->map(function (ModuleInterface $module) {
            return [
                'name' => $module->getName(),
                'path' => $module->getPath(),
                'enabled' => $module->isEnabled(),
            ];
        })->all();
    }

    /**
     * Check if a module exists.
     *
     * @deprecated Use ModuleManager::has() instead
     */
    public function hasModule(string $name): bool
    {
        return $this->manager->has($name);
    }

    /**
     * Check if a module is enabled.
     *
     * @deprecated Use ModuleManager::isEnabled() instead
     */
    public function isEnabled(string $name): bool
    {
        return $this->manager->isEnabled($name);
    }

    /**
     * Enable a module.
     *
     * @deprecated Use ModuleManager::enable() instead
     */
    public function enable(string $name): bool
    {
        try {
            $this->manager->enable($name);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Disable a module.
     *
     * @deprecated Use ModuleManager::disable() instead
     */
    public function disable(string $name): bool
    {
        try {
            $this->manager->disable($name);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Scan for modules.
     *
     * @deprecated Modules are discovered automatically
     */
    public function scanModules(): void
    {
        $this->manager->refresh();
    }

    /**
     * Get the underlying module manager.
     */
    public function getManager(): ModuleManager
    {
        return $this->manager;
    }
}
