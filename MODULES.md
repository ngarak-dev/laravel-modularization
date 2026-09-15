# Module system

This document describes how modules work in this package. Keep it in sync with the implementation; the README is the getting-started guide.

## Lifecycle

1. **Discover** — `ModuleDiscovery` scans `modules/` (or a compiled cache) for directories with valid names.
2. **Describe** — `ModuleManifest` reads `module.json`, then `Config/config.php`. A `.disabled` file overrides `enabled`.
3. **Sort** — `DependencyResolver` orders modules by `requires` and rejects cycles.
4. **Register** — `ModuleRegistrar` registers each enabled, valid module’s provider, routes, views, translations, migrations, config, and Livewire components.
5. **Manage** — `ModuleStatusManager` persists enable/disable; `ModuleCache` writes `bootstrap/cache/modules.php`.

The public facade (`Modularization`) delegates to `ModuleManager` through `ModularizationService` so existing `getModules()`, `hasModule()`, `isEnabled()`, `enable()`, and `disable()` calls keep working.

## What makes a valid module

A directory under the configured modules path whose name is a PHP class name (`Billing`, `UserProfile`). Path separators and `..` are rejected.

A module should have:

- `Providers/{Name}ServiceProvider.php`
- `module.json` or `Config/config.php`

Missing providers are listed as **invalid** by `module:list` and are not booted.

## Registration with Laravel

The package service provider boots `ModuleManager`. It does not replace Laravel’s provider, route, or view systems. Module service providers should bind interfaces; the package loads shared resources so modules do not have to copy boilerplate.

If a module registers routes itself, set `auto_register_controllers` to `false` to avoid duplicates.

## Commands

| Command | Role |
| --- | --- |
| `module:make` / `make:module` | Create a module |
| `module:list` | Status table |
| `module:toggle` | Enable/disable |
| `module:discover` | Rescan |
| `module:cache` / `module:clear` | Metadata cache |
| `module:make-*` | Generators inside a module |
| `module:migrate` / `module:migrate-all` | Module migrations |
| `module:export` | Package export |
| `module:make-auth` / `module:make-manager` | Larger scaffolds |

## Path conventions

Generated modules use `Http`, `Routes`, `Database`, `Resources`. Discovery accepts those names and the lowercase Laravel equivalents so older trees still boot.
