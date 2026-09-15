# Module Architecture

This document explains how the module system works in this Laravel package.

## Overview

The modular architecture organizes code by business domain rather than technical function. Each module is a self-contained unit with all components needed for a specific domain.

## Module Structure

```
modules/ModuleName/
├── Config/
│   └── config.php           # Module configuration
├── Database/
│   ├── Factories/           # Model factories
│   ├── Migrations/          # Database migrations
│   └── Seeders/             # Database seeders
├── Http/
│   ├── Controllers/         # Web controllers
│   │   └── API/             # API controllers
│   ├── Middleware/          # Module-specific middleware
│   └── Requests/            # Form request validation
├── Livewire/                # Livewire components
├── Models/                  # Eloquent models
├── Providers/
│   └── ModuleServiceProvider.php
├── Repositories/
│   ├── Interfaces/          # Repository contracts
│   └── ModuleRepository.php
├── Resources/
│   ├── assets/              # CSS, JS, images
│   ├── lang/                # Translation files
│   └── views/               # Blade templates
├── Routes/
│   ├── api.php              # API routes
│   ├── livewire.php         # Livewire routes
│   └── web.php              # Web routes
├── Services/
│   ├── Interfaces/          # Service contracts
│   └── ModuleService.php
├── Tests/
│   ├── Feature/             # Feature tests
│   └── Unit/                # Unit tests
└── module.json              # Module manifest
```

## Module Manifest (module.json)

Every module has a `module.json` file that defines its metadata:

```json
{
    "name": "Products",
    "description": "Product management module",
    "version": "1.0.0",
    "enabled": true,
    "provider": "Modules\\Products\\Providers\\ProductsServiceProvider",
    "requires": [],
    "routes": {
        "prefix": "products",
        "middleware": ["web"]
    },
    "menu": {
        "title": "Products",
        "icon": "fa fa-box"
    }
}
```

### Manifest Fields

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Module name (PascalCase) |
| `description` | string | Human-readable description |
| `version` | string | Semantic version |
| `enabled` | boolean | Whether module is active |
| `provider` | string | Service provider class |
| `requires` | array | Module dependencies |
| `routes.prefix` | string | URL prefix for routes |
| `routes.middleware` | array | Default middleware |
| `menu.title` | string | Display name in menus |
| `menu.icon` | string | Icon class (Font Awesome) |

## Module Lifecycle

### 1. Discovery

On application boot, the `ModuleDiscovery` service scans the modules directory:

```php
$discovery = app(ModuleDiscoveryInterface::class);
$modules = $discovery->discover();
```

In production, use caching to skip filesystem scanning:

```bash
php artisan module:cache
```

### 2. Registration

Modules are registered in dependency order. The `ModuleRepository` performs topological sorting to ensure dependencies load first.

### 3. Loading

The `ModuleLoader` handles loading each module's components:

- Service provider registration
- Route loading (web, API, Livewire)
- View namespace registration
- Translation loading
- Migration registration
- Livewire component registration
- Configuration merging

### 4. Booting

The `ModuleManager` coordinates the boot process:

```php
$manager = app(ModuleManager::class);
$manager->boot();
```

## Module Dependencies

Declare dependencies in `module.json`:

```json
{
    "name": "Orders",
    "requires": ["Products", "Users"]
}
```

### Dependency Rules

1. Dependencies must exist in the modules directory
2. Dependencies must be enabled
3. Circular dependencies are not allowed
4. Modules load in dependency order

### Dependency Validation

The package validates dependencies at boot time:

```php
$resolver = app(DependencyResolver::class);
$resolver->validateAll(); // Throws on missing/disabled dependencies
```

## Enable/Disable Modules

### Via Command

```bash
php artisan module:toggle Products --disable
php artisan module:toggle Products --enable
```

### Via Code

```php
use NgarakDev\Modularization\Facades\Modularization;

Modularization::disable('Products');
Modularization::enable('Products');
```

### Disabled Module Behavior

When a module is disabled:

- A `.disabled` file is created in the module directory
- The `enabled` field in `module.json` is set to `false`
- Routes are not registered
- Service provider is not loaded
- Views, translations, and migrations are not registered
- Livewire components are not available

## Module Cache

For production, cache module metadata:

```bash
# Generate cache
php artisan module:cache

# Clear cache
php artisan module:clear
```

The cache is stored in `bootstrap/cache/modules.php` and contains:

- Module names and paths
- Namespace mappings
- Enabled/disabled status
- Manifest data
- Dependency information

## Accessing Modules

### Helper Functions

```php
// Get module path
module_path('Products');
module_path('Products', 'Http/Controllers');

// Get module manager
$manager = modules();

// Get specific module
$module = module('Products');

// Check if enabled
if (module_enabled('Products')) {
    // ...
}
```

### Facade

```php
use NgarakDev\Modularization\Facades\Modularization;

// Get all modules
$all = Modularization::all();

// Get enabled modules
$enabled = Modularization::enabled();

// Find module by name
$module = Modularization::find('Products');

// Check existence
Modularization::has('Products');

// Check status
Modularization::isEnabled('Products');
```

### Service Injection

```php
use NgarakDev\Modularization\ModuleManager;

class SomeController
{
    public function __construct(
        private ModuleManager $modules,
    ) {}

    public function index()
    {
        $enabled = $this->modules->enabled();
    }
}
```

## Cross-Module Communication

### Via Service Injection

```php
// In OrdersController
use Modules\Products\Services\Interfaces\ProductsServiceInterface;

public function __construct(
    private ProductsServiceInterface $products,
) {}
```

### Via Events

```php
// In Products module - dispatch event
event(new ProductCreated($product));

// In Orders module - listen for event
class OrdersServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(ProductCreated::class, function ($event) {
            // Handle product creation
        });
    }
}
```

## Best Practices

1. **Keep modules focused**: One domain per module
2. **Minimize dependencies**: Prefer events over direct coupling
3. **Use interfaces**: Depend on contracts, not implementations
4. **Test in isolation**: Each module should have its own tests
5. **Cache in production**: Always run `module:cache` in production
6. **Document dependencies**: Keep `module.json` accurate
