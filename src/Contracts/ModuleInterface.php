<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

/**
 * Represents a single module in the application.
 */
interface ModuleInterface
{
    /**
     * Get the module name.
     */
    public function getName(): string;

    /**
     * Get the module path.
     */
    public function getPath(): string;

    /**
     * Get the module namespace.
     */
    public function getNamespace(): string;

    /**
     * Check if the module is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the module's service provider class name.
     */
    public function getProviderClass(): ?string;

    /**
     * Get module manifest/configuration data.
     *
     * @return array<string, mixed>
     */
    public function getManifest(): array;

    /**
     * Get the module's dependencies.
     *
     * @return array<string>
     */
    public function getDependencies(): array;

    /**
     * Get the module description.
     */
    public function getDescription(): string;

    /**
     * Get the module version.
     */
    public function getVersion(): string;
}
