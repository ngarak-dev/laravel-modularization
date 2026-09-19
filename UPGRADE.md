# Upgrade Guide

## Upgrading from 1.x to 2.0

Version 2.0 introduces a modernized architecture while maintaining backwards compatibility. Most existing code will continue to work without changes.

### New Features

#### Module Manifest (module.json)

Modules now support a `module.json` file:

```json
{
    "name": "Products",
    "description": "Product management",
    "version": "1.0.0",
    "enabled": true,
    "requires": ["Users"]
}
```

To add a manifest to existing modules:

```bash
# Create module.json in each module directory
echo '{"name": "ModuleName", "version": "1.0.0", "enabled": true}' > modules/ModuleName/module.json
```

#### New Commands

```bash
# List all modules
php artisan module:list

# Cache modules for production
php artisan module:cache

# Clear module cache
php artisan module:clear
```

#### Module Dependencies

Add dependencies to `module.json`:

```json
{
    "name": "Orders",
    "requires": ["Products", "Users"]
}
```

### Breaking Changes

None. All v1.x APIs continue to work in v2.0.

### Deprecations

The following are deprecated and will be removed in v3.0:

#### ModularizationService

```php
// Deprecated
$service = app('modularization');
$modules = $service->getModules();

// Use instead
$manager = app(ModuleManager::class);
$modules = $manager->all();
```

#### scanModules()

```php
// Deprecated
$service->scanModules();

// Use instead
$manager->refresh();
```

### Recommended Updates

#### 1. Add module.json to existing modules

```json
{
    "name": "YourModule",
    "description": "Module description",
    "version": "1.0.0",
    "enabled": true,
    "provider": "Modules\\YourModule\\Providers\\YourModuleServiceProvider",
    "requires": []
}
```

#### 2. Use ModuleManager instead of ModularizationService

```php
// Before
use NgarakDev\Modularization\ModularizationService;

$service = app(ModularizationService::class);
$modules = $service->getModules();

// After
use NgarakDev\Modularization\ModuleManager;

$manager = app(ModuleManager::class);
$modules = $manager->all();
```

#### 3. Use typed stubs

Regenerate stubs to get modern PHP features:

```bash
php artisan module:publish-stubs
```

#### 4. Enable caching in production

```bash
# In deployment script
php artisan module:cache
```

### Configuration Changes

New configuration options in `config/modularization.php`:

```php
return [
    // Existing options...
    
    // New: Cache settings
    'cache' => [
        'enabled' => env('MODULES_CACHE', false),
        'path' => env('MODULES_CACHE_PATH', null),
    ],
];
```

To update your config:

```bash
php artisan vendor:publish --tag=modularization-config --force
```

Then merge your customizations back in.

## Questions?

If you encounter issues upgrading, please open an issue on GitHub.
