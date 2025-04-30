# Laravel Modular Architecture with Repository Pattern

This package implements a modular architecture in Laravel, combining the Repository Pattern and Service Layer to create maintainable, scalable applications organized by business domain rather than technical function.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Step-by-Step Usage Guide](#step-by-step-usage-guide)
- [Available Commands](#available-commands)
- [Module Structure](#module-structure)
- [Architecture Implementation](#architecture-implementation)
- [Design Patterns](#design-patterns)
- [Configuration Options](#configuration-options)
- [Advanced Usage](#advanced-usage)
- [License](#license)

## Features

- **Modular Architecture**: Organize code by business domain with auto-discovery
- **Repository Pattern**: Clean separation between data access and business logic
- **Service Layer**: Domain-specific business logic encapsulation
- **Code Generation**: Commands to scaffold modules, repositories, services, etc.
- **Views & Layouts**: Module-specific views with dedicated layouts
- **API Support**: Built-in API controllers and routes
- **Livewire Integration**: Create interactive UIs with auto-registered components
- **Events & Translations**: Module-specific events and translations
- **Module Management**: Enable/disable modules or export them as packages

## Installation

### Step 1: Install the Package

```bash
composer require ngarak-dev/laravel-modularization
```

### Step 2: Publish the Configuration

```bash
php artisan vendor:publish --provider="NgarakDev\Modularization\Providers\ModularizationServiceProvider" --tag="modularization-config"
```

### Step 3: Customize Configuration (Optional)

Edit `config/modularization.php` to:

- Change the modules directory (default: `modules/`)
- Adjust module namespace (default: `Modules`)
- Customize auto-registration settings

### Step 4: Create Directory Structure

The package will automatically create the modules directory when you create your first module.

## Step-by-Step Usage Guide

### 1. Create Your First Module

```bash
php artisan module:make Products
```

This creates a new module with the basic structure:

```
modules/Products/
├── Http/Controllers/
├── Models/
├── Providers/
├── Repositories/
├── Services/
└── ...
```

### 2. Add API Support (Optional)

```bash
php artisan module:make Orders --api
```

This adds API controllers and routes to your module.

### 3. Add Views (Optional)

```bash
php artisan module:make Customers --with-views
```

This adds view templates and layouts to your module.

### 4. Add Livewire Components (Optional)

```bash
php artisan module:make Inventory --with-livewire
```

This adds Livewire components and views to your module.

### 5. Define Your Model

Create a model in your module:

```php
// modules/Products/Models/Product.php
namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'description', 'price'
    ];
}
```

### 6. Create a Migration

```bash
php artisan make:migration create_products_table
```

Move this migration to your module's `Database/Migrations` directory.

### 7. Implement Repository Interface and Class

The repository interface and class should already be scaffolded. Update them to match your model:

```php
// modules/Products/Repositories/Interfaces/ProductRepositoryInterface.php
namespace Modules\Products\Repositories\Interfaces;

interface ProductRepositoryInterface
{
    public function getAll();
    public function findById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}

// modules/Products/Repositories/ProductRepository.php
namespace Modules\Products\Repositories;

use Modules\Products\Models\Product;
use Modules\Products\Repositories\Interfaces\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    protected $model;

    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    public function getAll()
    {
        return $this->model->all();
    }

    public function findById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        $model = $this->findById($id);
        $model->update($data);
        return $model;
    }

    public function delete($id)
    {
        $model = $this->findById($id);
        return $model->delete();
    }
}
```

### 8. Implement Service Interface and Class

```php
// modules/Products/Services/Interfaces/ProductServiceInterface.php
namespace Modules\Products\Services\Interfaces;

interface ProductServiceInterface
{
    public function getAllProducts();
    public function getProductById($id);
    public function createProduct(array $data);
    public function updateProduct($id, array $data);
    public function deleteProduct($id);
}

// modules/Products/Services/ProductService.php
namespace Modules\Products\Services;

use Modules\Products\Repositories\Interfaces\ProductRepositoryInterface;
use Modules\Products\Services\Interfaces\ProductServiceInterface;

class ProductService implements ProductServiceInterface
{
    protected $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllProducts()
    {
        return $this->repository->getAll();
    }

    public function getProductById($id)
    {
        return $this->repository->findById($id);
    }

    public function createProduct(array $data)
    {
        return $this->repository->create($data);
    }

    public function updateProduct($id, array $data)
    {
        return $this->repository->update($id, $data);
    }

    public function deleteProduct($id)
    {
        return $this->repository->delete($id);
    }
}
```

### 9. Register Dependencies in Service Provider

The module service provider is automatically created and registered. Bind your interfaces:

```php
// modules/Products/Providers/ProductsServiceProvider.php
namespace Modules\Products\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Products\Repositories\Interfaces\ProductRepositoryInterface;
use Modules\Products\Repositories\ProductRepository;
use Modules\Products\Services\Interfaces\ProductServiceInterface;
use Modules\Products\Services\ProductService;

class ProductsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
    }

    public function boot()
    {
        // Additional module boot logic here
    }
}
```

### 10. Update Controllers

The web and API controllers should already be scaffolded. Update them to use your service:

```php
// modules/Products/Http/Controllers/ProductsController.php
namespace Modules\Products\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Products\Services\Interfaces\ProductServiceInterface;

class ProductsController extends Controller
{
    protected $productService;

    public function __construct(ProductServiceInterface $productService)
    {
        $this->productService = $productService;
    }

    public function index()
    {
        $products = $this->productService->getAllProducts();
        return view('products::products.index', compact('products'));
    }

    public function show($id)
    {
        $product = $this->productService->getProductById($id);
        return view('products::products.show', compact('product'));
    }

    // Other actions...
}
```

### 11. Add Routes

Routes are already set up in the module, but you may need to customize them:

```php
// modules/Products/Routes/web.php
use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\ProductsController;

Route::middleware('web')->group(function() {
    Route::prefix('products')->group(function() {
        Route::get('/', [ProductsController::class, 'index'])->name('products.index');
        Route::get('/{id}', [ProductsController::class, 'show'])->name('products.show');
        // Other routes...
    });
});
```

### 12. Create Livewire Components (Optional)

You can add Livewire components to an existing module:

```bash
php artisan module:make-livewire Products ProductForm
```

Use the component in your views:

```php
@livewire('products::product-form')
```

## Available Commands

```bash
# Create a new module
php artisan module:make ModuleName [--api] [--with-views] [--with-livewire] [--force]

# Add a Livewire component to a module
php artisan module:make-livewire ModuleName ComponentName [--force] [--subdirectory=Subfolder] [--view-only] [--class-only]

# Create module-specific events
php artisan module:make-event ModuleName EventName

# Create module-specific translations
php artisan module:make-translation ModuleName Language

# Publish stubs for customization
php artisan module:publish-stubs

# Enable or disable a module
php artisan module:toggle ModuleName

# Export a module as a package
php artisan module:export ModuleName
```

## Module Structure

Each module follows a consistent structure:

```
modules/ModuleName/
├── Http/
│   ├── Controllers/            # Web controllers
│   │   └── API/                # API controllers
│   ├── Middleware/             # Module-specific middleware
│   └── Requests/               # Form requests with validation
├── Livewire/                   # Livewire components
├── Models/                     # Domain models
├── Providers/                  # Service providers
├── Repositories/               # Data access layer
│   └── Interfaces/             # Repository interfaces
├── Services/                   # Business logic layer
│   └── Interfaces/             # Service interfaces
├── Resources/
│   ├── views/                  # Module-specific views
│   │   ├── layouts/            # Module-specific layouts
│   │   └── livewire/           # Livewire component views
│   └── lang/                   # Module-specific translations
├── Routes/
│   ├── web.php                 # Module web routes
│   └── api.php                 # Module API routes
├── Config/                     # Module config files
└── Database/
    ├── Migrations/             # Module-specific migrations
    ├── Seeders/                # Module-specific seeders
    └── Factories/              # Model factories
```

## Architecture Implementation

The package implements a layered architecture:

1. **Controllers** handle HTTP requests and delegate to services
2. **Services** contain business logic and use repositories
3. **Repositories** handle data access through models
4. **Models** represent the database structure

### Service Provider Auto-Discovery

The package automatically:

- Scans the modules directory
- Registers each module's service provider
- Loads routes, views, translations, and migrations
- Registers Livewire components

## Design Patterns

### Repository Pattern

The repository pattern separates data access logic from business logic:

```php
// Interface defines the contract
interface ProductRepositoryInterface { ... }

// Implementation handles actual data access
class ProductRepository implements ProductRepositoryInterface { ... }
```

### Service Layer Pattern

The service layer contains business logic:

```php
// Interface defines the contract
interface ProductServiceInterface { ... }

// Implementation contains business rules
class ProductService implements ProductServiceInterface { ... }
```

## Configuration Options

Key options in `config/modularization.php`:

```php
return [
    // Path where modules are stored
    'modules_path' => 'modules',

    // Namespace for all modules
    'namespace' => 'Modules',

    // Default directories created in each module
    'directories' => [ ... ],

    // Auto-register controllers with routes
    'auto_register_controllers' => true,

    // Auto-register Livewire components
    'auto_register_livewire' => true,

    // Enforce repository interface implementation
    'enforce_repository_pattern' => true,
];
```

## Advanced Usage

### Module-Specific Layouts

Each module can have its own layouts:

```php
// In your controller
return view('modulename::page')->extends('modulename::layouts.module-layout');

// Or in your blade file
@extends('modulename::layouts.module-layout')
```

### Sharing Data Between Modules

Use Laravel's event system for cross-module communication:

```php
// In one module, dispatch an event
event(new \Modules\Orders\Events\OrderCreated($order));

// In another module's service provider, listen for the event
$this->app['events']->listen(
    \Modules\Orders\Events\OrderCreated::class,
    function ($event) {
        // Handle event
    }
);
```

### Exporting Modules as Packages

You can export a module as a standalone package:

```bash
php artisan module:export ModuleName
```

This creates a publishable package in the `packages` directory.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
