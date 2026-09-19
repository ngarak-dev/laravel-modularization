<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Contracts;

/**
 * Loads module components (providers, routes, views, etc.).
 */
interface ModuleLoaderInterface
{
    /**
     * Load all components for a module.
     */
    public function load(ModuleInterface $module): void;

    /**
     * Load the module's service provider.
     */
    public function loadServiceProvider(ModuleInterface $module): void;

    /**
     * Load the module's routes.
     */
    public function loadRoutes(ModuleInterface $module): void;

    /**
     * Load the module's views.
     */
    public function loadViews(ModuleInterface $module): void;

    /**
     * Load the module's translations.
     */
    public function loadTranslations(ModuleInterface $module): void;

    /**
     * Load the module's migrations.
     */
    public function loadMigrations(ModuleInterface $module): void;

    /**
     * Load the module's configuration.
     */
    public function loadConfig(ModuleInterface $module): void;

    /**
     * Register the module's Livewire components.
     */
    public function loadLivewireComponents(ModuleInterface $module): void;
}
