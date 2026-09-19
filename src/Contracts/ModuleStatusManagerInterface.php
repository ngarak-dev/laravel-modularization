<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;

/**
 * Manages module enabled/disabled status.
 */
interface ModuleStatusManagerInterface
{
    /**
     * @throws ModuleNotFoundException
     */
    public function enable(string $name): bool;

    /**
     * @throws ModuleNotFoundException
     */
    public function disable(string $name): bool;

    public function isEnabled(string $name): bool;

    public function isDisabled(string $name): bool;

    /**
     * @return bool True when the module is enabled after toggling
     *
     * @throws ModuleNotFoundException
     */
    public function toggle(string $name): bool;
}
