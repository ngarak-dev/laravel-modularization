<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

/**
 * Manages module enabled/disabled status.
 */
interface ModuleStatusManagerInterface
{
    /**
     * Enable a module.
     *
     * @throws \NgarakDev\Modularization\Exceptions\ModuleNotFoundException
     */
    public function enable(string $name): void;

    /**
     * Disable a module.
     *
     * @throws \NgarakDev\Modularization\Exceptions\ModuleNotFoundException
     */
    public function disable(string $name): void;

    /**
     * Check if a module is enabled.
     */
    public function isEnabled(string $name): bool;

    /**
     * Check if a module is disabled.
     */
    public function isDisabled(string $name): bool;

    /**
     * Toggle module status.
     *
     * @return bool Returns true if module is now enabled, false if disabled
     * @throws \NgarakDev\Modularization\Exceptions\ModuleNotFoundException
     */
    public function toggle(string $name): bool;
}
