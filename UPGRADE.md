# Upgrade Guide

## Upgrading from 1.0 to 1.1.0

Version 1.1.0 unifies discovery, caching, status, and registration behind `ModuleManager` while keeping the 1.x public API. Existing modules continue to load.

### New Features

#### Module Manifest (module.json)

Modules now support a `module.json` file:

```json
{
    "name": "Products",
    "description": "Product management",
    "version": "1.0.0",
    "enabled": true,
    "requires": ["Users:^1.0"]
}
```

`requires` entries may be plain names (`"Users"`), `Name:constraint` strings, or a map of name to constraint. Constraints use Composer-style `^`, `~`, `>=`, `<=`, `>`, `<`, or an exact version.

To add a manifest to existing modules:

```bash
echo '{"name": "ModuleName", "version": "1.0.0", "enabled": true}' > modules/ModuleName/module.json
```

#### New Commands

```bash
php artisan module:list
php artisan module:cache
php artisan module:clear
php artisan module:discover
```

`module:cache` also writes `bootstrap/cache/modules-registry.json` for other apps that need the same inventory.

#### Lifecycle

`.disabled`, `.installing`, and `.broken` marker files are honored. Listen for `ModuleDiscovered`, `ModuleEnabled`, `ModuleDisabled`, `ModuleRegistered`, and `ModuleBroken`.

#### Route names

Set `modularization.routes.fail_on_collision` to `true` to refuse booting when two enabled modules declare the same `->name()`.

### Breaking Changes

None required for existing 1.x applications. Duplicate Support/Services runtime classes were removed; type-hint `ModuleManager` and the `Contracts\*` interfaces instead of the deleted `Support\Module*` / `Services\*` classes if you extended those internals.

### Deprecations

The following remain available and will be removed in 2.0:

#### ModularizationService

```php
// Deprecated
$service = app('modularization');
$modules = $service->getModules();

// Use instead
$manager = app(\NgarakDev\Modularization\ModuleManager::class);
$modules = $manager->all();
```

Package version is `NgarakDev\Modularization\ModularizationService::VERSION` (`1.1.0`).

### Recommended Updates

1. Add `module.json` to existing modules.
2. Prefer `ModuleManager` over `ModularizationService`.
3. Run `php artisan module:publish-stubs` to pick up typed stubs.
4. Run `php artisan module:cache` in production deploys.
5. Set `dump_autoload` if you want `module:make` to refresh Composer automatically.

```bash
php artisan vendor:publish --tag=modularization-config --force
```

Then merge your customizations back in.

## Questions?

If you encounter issues upgrading, please open an issue on GitHub.
