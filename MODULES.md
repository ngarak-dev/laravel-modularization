# Module System Reference

This document describes how the module system works in the `ngarak-dev/laravel-modularization` package.

## Table of Contents

- [Introduction](#introduction)
- [Module Structure](#module-structure)
- [Module Registration](#module-registration)
- [Creating a Module](#creating-a-module)
- [Module Components](#module-components)
- [Integration Examples](#integration-examples)

## Introduction

The modular architecture organizes code by business domain rather than technical function, improving maintainability and scalability. Each module is a self-contained unit with all necessary components.

### Benefits

- **Separation of Concerns**: Isolate business domains from each other
- **Maintainability**: Changes within a module don't affect other modules
- **Team Collaboration**: Different teams can work on different modules
- **Testability**: Modules can be tested in isolation
- **Reusability**: Modules can be reused across projects

## Module Structure

Each module follows a consistent structure:

```
modules/ModuleName/
├── Config/                     # Module-specific configuration
├── Http/
│   ├── Controllers/            # Web and API controllers
│   └── Requests/               # Form requests with validation
├── Livewire/                   # Livewire components (optional)
├── Models/                     # Domain models
├── Providers/
│   └── ModuleNameServiceProvider.php
├── Repositories/               # Data access layer
│   ├── Interfaces/             # Repository interfaces
│   └── ModuleNameRepository.php
├── Resources/
│   ├── views/                  # Module-specific Blade views
│   └── lang/                   # Module-specific translations
│       └── en/
│           ├── general.php
│           ├── validation.php
│           └── module.php
├── Routes/
│   ├── web.php                 # Module web routes
│   └── api.php                 # Module API routes
├── Services/                   # Business logic layer
│   ├── Interfaces/             # Service interfaces
│   └── ModuleNameService.php
├── database/
│   └── migrations/             # Module-specific migrations
└── module.json                 # Module manifest (metadata & dependencies)
```

## Module Registration

Modules are automatically discovered and registered by the `ModulesServiceProvider`. This provider:

1. Scans the `app/Modules` directory for module folders
2. Loads all module routes, views, translations, and migrations
3. Registers module service providers
4. Auto-registers Livewire components

### How Auto-Discovery Works

The `ModulesServiceProvider` uses the following process:

```php
class ModulesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadModules();
    }

    private function loadModules()
    {
        $modules = File::directories(app_path('Modules'));

        foreach ($modules as $module) {
            $this->loadRoutes($module);
            $this->loadViews($module);
            $this->loadMigrations($module);
            $this->loadTranslations($module);
            $this->registerLivewireComponents($module);
            $this->registerProviders($module);
        }
    }

    // Other methods for loading specific module components...
}
```

## Creating a Module

You can create a new module using the provided artisan command:

```bash
php artisan make:module ModuleName
```

This command creates the necessary directory structure and base files.

### Options

- `--api`: Generate API controllers and routes
- `--with-views`: Generate view files
- `--with-livewire`: Generate Livewire components
- `--force`: Overwrite existing module files

## Module Components

### Controllers

Controllers handle HTTP requests and delegate business logic to services:

```php
namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Services\Interfaces\ProductServiceInterface;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductServiceInterface $productService)
    {
        $this->productService = $productService;
    }

    public function index()
    {
        $products = $this->productService->getAllProducts();
        return view('products::index', compact('products'));
    }
}
```

### Repositories

Repositories handle data access operations:

```php
namespace App\Modules\Products\Repositories;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Repositories\Interfaces\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    public function getAll()
    {
        return Product::all();
    }

    public function findById($id)
    {
        return Product::findOrFail($id);
    }
}
```

### Services

Services contain business logic:

```php
namespace App\Modules\Products\Services;

use App\Modules\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Modules\Products\Services\Interfaces\ProductServiceInterface;

class ProductService implements ProductServiceInterface
{
    protected $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getAllProducts()
    {
        return $this->productRepository->getAll();
    }
}
```

### Livewire Components

Livewire components handle real-time UI interactions:

```php
namespace App\Modules\Products\Livewire;

use Livewire\Component;
use App\Modules\Products\Services\Interfaces\ProductServiceInterface;

class ProductList extends Component
{
    public $products = [];

    public function mount(ProductServiceInterface $productService)
    {
        $this->products = $productService->getAllProducts();
    }

    public function render()
    {
        return view('products::livewire.product-list');
    }
}
```

## Integration Examples

### User Profile Module Integration

The User Profile module demonstrates effective integration with other modules through:

1. **Event-Based Communication**: Using Laravel events to notify other modules of profile changes
2. **Service Interfaces**: Other modules can interact with user profiles through a clean service interface
3. **API Endpoints**: REST API for CRUD operations on user profiles

Example of the User Profile service interface:

```php
namespace App\Modules\UserProfile\Services\Interfaces;

interface UserProfileServiceInterface
{
    public function getUserProfile($userId);
    public function updateUserProfile($userId, array $data);
    public function deleteUserProfile($userId);
    public function createUserProfile($userId, array $data);
}
```

Other modules can consume the User Profile service:

```php
// In another module's service
public function someBusinessFunction($userId)
{
    $userProfile = $this->userProfileService->getUserProfile($userId);

    // Process based on user profile data
    if ($userProfile->hasPermission('some-action')) {
        // Perform action
    }
}
```

### Cross-Module Events

Example of using events for cross-module communication:

```php
// In UserProfile module
class ProfileUpdatedEvent
{
    public $userId;
    public $profile;

    public function __construct($userId, $profile)
    {
        $this->userId = $userId;
        $this->profile = $profile;
    }
}

// In UserProfile service
public function updateUserProfile($userId, array $data)
{
    $profile = $this->userProfileRepository->update($userId, $data);
    event(new ProfileUpdatedEvent($userId, $profile));
    return $profile;
}

// In another module's service provider
$this->app['events']->listen(
    \App\Modules\UserProfile\Events\ProfileUpdatedEvent::class,
    function ($event) {
        // React to profile updates
    }
);
```
