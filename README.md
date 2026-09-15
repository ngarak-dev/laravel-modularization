# Laravel Modular Architecture

<p align="center">
<a href="https://github.com/ngarak-dev/laravel-modularization/actions"><img src="https://github.com/ngarak-dev/laravel-modularization/workflows/Tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/ngarak-dev/laravel-modularization"><img src="https://img.shields.io/packagist/v/ngarak-dev/laravel-modularization.svg" alt="Latest Version"></a>
<a href="https://packagist.org/packages/ngarak-dev/laravel-modularization"><img src="https://img.shields.io/packagist/dt/ngarak-dev/laravel-modularization.svg" alt="Total Downloads"></a>
<a href="https://github.com/ngarak-dev/laravel-modularization/blob/main/LICENSE.md"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License"></a>
</p>

A modern, production-ready Laravel package for building modular applications using domain-driven organization, Repository Pattern, and Service Layer architecture.

## Features

- **Domain-Driven Modules**: Organize code by business domain with auto-discovery
- **Repository Pattern**: Clean separation between data access and business logic
- **Service Layer**: Encapsulate business logic with dedicated service classes
- **Module Dependencies**: Declare and validate inter-module dependencies
- **Performance Optimized**: Module caching for production environments
- **Livewire Integration**: Auto-registered Livewire components per module
- **Complete Generators**: Generate modules, controllers, models, repositories, services, and more
- **Modern PHP**: Strict types, typed properties, and PHP 8.1+ features
- **Fully Tested**: Comprehensive test suite with PHPStan static analysis

## Requirements

- PHP 8.1 or higher
- Laravel 10.0, 11.0, or 12.0

## Installation

```bash
composer require ngarak-dev/laravel-modularization
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="NgarakDev\Modularization\Providers\ModularizationServiceProvider" --tag="modularization-config"
```

## Quick Start

### Create Your First Module

```bash
php artisan module:make Products
```

This creates a complete module structure:

```
modules/Products/
├── Config/
│   └── config.php
├── Database/
│   ├── Migrations/
│   ├── Seeders/
│   └── Factories/
├── Http/
│   ├── Controllers/
│   │   └── ProductsController.php
│   ├── Middleware/
│   └── Requests/
│       └── ProductsRequest.php
├── Livewire/
├── Models/
│   └── Products.php
├── Providers/
│   └── ProductsServiceProvider.php
├── Repositories/
│   ├── Interfaces/
│   │   └── ProductsRepositoryInterface.php
│   └── ProductsRepository.php
├── Resources/
│   ├── assets/
│   ├── lang/
│   └── views/
├── Routes/
│   └── web.php
├── Services/
│   ├── Interfaces/
│   │   └── ProductsServiceInterface.php
│   └── ProductsService.php
├── Tests/
│   ├── Feature/
│   └── Unit/
└── module.json
```

### List All Modules

```bash
php artisan module:list
```

### Enable/Disable Modules

```bash
php artisan module:toggle Products --disable
php artisan module:toggle Products --enable
```

## Module Structure

### module.json Manifest

Each module has a `module.json` manifest file:

```json
{
    "name": "Products",
    "description": "Product management module",
    "version": "1.0.0",
    "enabled": true,
    "provider": "Modules\\Products\\Providers\\ProductsServiceProvider",
    "requires": ["Users"],
    "routes": {
        "prefix": "products",
        "middleware": ["web"]
    }
}
```

### Module Dependencies

Declare dependencies in `module.json`:

```json
{
    "name": "Orders",
    "requires": ["Products", "Users"]
}
```

The package will:
- Validate all dependencies exist and are enabled
- Load modules in the correct dependency order
- Detect and prevent circular dependencies

## Available Commands

### Module Management

| Command | Description |
|---------|-------------|
| `module:make {name}` | Create a new module |
| `module:list` | List all modules and their status |
| `module:toggle {name}` | Enable or disable a module |
| `module:cache` | Cache module metadata for production |
| `module:clear` | Clear the module cache |
| `module:export {name}` | Export module as a standalone package |

### Module Generation Options

```bash
# Basic module
php artisan module:make Products

# With API support
php artisan module:make Products --api

# With views
php artisan module:make Products --with-views

# With Livewire components
php artisan module:make Products --with-livewire

# With translations
php artisan module:make Products --with-translations

# Combined options
php artisan module:make Products --api --with-views --with-livewire
```

### Component Generation

```bash
# Create Livewire component in a module
php artisan module:make-livewire Products ProductForm

# Create module migration
php artisan module:make-migration create_products_table Products

# Run module migrations
php artisan module:migrate Products
php artisan module:migrate-all
```

## Configuration

Publish and customize `config/modularization.php`:

```php
return [
    // Where modules are stored (relative to base_path)
    'modules_path' => 'modules',

    // Base namespace for all modules
    'namespace' => 'Modules',

    // Auto-registration settings
    'auto_register_providers' => true,
    'auto_register_routes' => true,
    'auto_register_views' => true,
    'auto_register_translations' => true,
    'auto_register_migrations' => true,
    'auto_register_livewire' => true,

    // Generator settings
    'enforce_repository_pattern' => true,
];
```

## Caching for Production

For production, cache module metadata to avoid filesystem scanning:

```bash
# Cache modules
php artisan module:cache

# Clear cache (after adding/removing modules)
php artisan module:clear
```

Add to your deployment script:

```bash
php artisan module:cache
php artisan config:cache
php artisan route:cache
```

## Using the Facade

```php
use NgarakDev\Modularization\Facades\Modularization;

// Get all modules
$modules = Modularization::all();

// Get enabled modules
$enabled = Modularization::enabled();

// Check if module exists
if (Modularization::has('Products')) {
    // ...
}

// Check if module is enabled
if (Modularization::isEnabled('Products')) {
    // ...
}

// Enable/disable programmatically
Modularization::enable('Products');
Modularization::disable('Products');
```

## Helper Functions

```php
// Get module path
$path = module_path('Products');
// /path/to/app/modules/Products

$controllersPath = module_path('Products', 'Http/Controllers');
// /path/to/app/modules/Products/Http/Controllers

// Get module manager
$manager = modules();

// Get a specific module
$module = module('Products');

// Check if enabled
if (module_enabled('Products')) {
    // ...
}
```

## Using Module Views

Views are namespaced by module name:

```php
// In controller
return view('products::index');

// In blade
@extends('products::layouts.module-layout')
```

## Using Module Translations

```php
// In PHP
__('products::messages.welcome')

// In Blade
{{ __('products::messages.welcome') }}
```

## Repository Pattern

Generated repositories provide a clean data access layer:

```php
// Repository Interface
interface ProductsRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): ?Products;
    public function create(array $data): Products;
    public function update(Products $model, array $data): Products;
    public function delete(Products $model): bool;
}

// Usage in Service
class ProductsService implements ProductsServiceInterface
{
    public function __construct(
        protected ProductsRepositoryInterface $repository,
    ) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }
}
```

## Service Layer

Services encapsulate business logic:

```php
class ProductsService implements ProductsServiceInterface
{
    public function __construct(
        protected ProductsRepositoryInterface $repository,
    ) {}

    public function create(array $data): Products
    {
        // Add business logic here
        return $this->repository->create($data);
    }
}
```

## Livewire Components

With `--with-livewire`, components are auto-registered:

```blade
{{-- In any blade view --}}
<livewire:products.products-table />
<livewire:products.products-form />
```

## Testing

Run the package tests:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

Run static analysis:

```bash
composer analyse
```

Check code style:

```bash
composer format-check
```

## Upgrading

See [CHANGELOG.md](CHANGELOG.md) for version history and upgrade notes.

### From v1.x to v2.0

The v2.0 release introduces a modernized architecture while maintaining backwards compatibility:

1. **New `module.json` Manifest**: Modules now support a `module.json` file for metadata and dependencies.

2. **New Commands**: 
   - `module:list` - List all modules
   - `module:cache` - Cache module data
   - `module:clear` - Clear module cache

3. **Facade Changes**: The `Modularization` facade now proxies to `ModuleManager`. Legacy methods still work but are deprecated.

4. **Typed Code**: All new code uses strict types and typed properties.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please email ngarakiringo@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
