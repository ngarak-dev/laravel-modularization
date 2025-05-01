# Laravel Modular Architecture with Repository Pattern

<p align="center">
<a href="https://github.com/ngarak-dev/laravel-modularization"><img src="https://img.shields.io/badge/Maintained%3F-yes-green.svg" alt="Maintenance"></a>
<a href="https://packagist.org/packages/ngarak-dev/laravel-modularization"><img src="https://img.shields.io/packagist/v/ngarak-dev/laravel-modularization.svg" alt="Latest Version"></a>
<a href="https://packagist.org/packages/ngarak-dev/laravel-modularization"><img src="https://img.shields.io/packagist/dt/ngarak-dev/laravel-modularization.svg" alt="Total Downloads"></a>
<a href="https://github.com/ngarak-dev/laravel-modularization/blob/main/LICENSE.md"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License"></a>
</p>

This package implements a modular architecture for Laravel applications, combining the Repository Pattern and Service Layer pattern to create maintainable, scalable applications organized by business domain rather than technical function.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Step-by-Step Usage Guide](#step-by-step-usage-guide)
- [Available Commands](#available-commands)
- [Module Structure](#module-structure)
- [Module Lifecycle](#module-lifecycle)
- [Design Patterns](#design-patterns)
- [Facade Usage](#facade-usage)
- [Configuration Options](#configuration-options)
- [Stub Customization](#stub-customization)
- [Advanced Usage](#advanced-usage)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Modular Architecture**: Organize code by business domain with auto-discovery
- **Command-Line Generation**: Generate modules, repositories, services, controllers with a single command
- **Repository Pattern**: Clean separation between data access and business logic
- **Service Layer**: Domain-specific business logic encapsulation
- **Auto-Discovery**: Automatic module registration, routes, views, translations and assets
- **Module Management**: Enable/disable modules or export them as packages
- **View & Layout System**: Module-specific views with dedicated layouts
- **API Support**: Built-in API controllers and routes
- **Livewire Integration**: Create interactive UIs with auto-registered components
- **Events & Translations**: Module-specific events and translations
- **Repository Enforcement**: Enforce repository pattern implementation

## Requirements

- PHP 8.0 or higher
- Laravel 9.0 or higher
- Composer

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
- Enable/disable repository pattern enforcement

### Step 4: Create Directory Structure

The package will automatically create the modules directory when you create your first module.

## Step-by-Step Usage Guide

### 1. Create Your First Module

```bash
php artisan module:make Products
```

This creates a new module with the following structure:

```
modules/Products/
├── Http/Controllers/
├── Models/
├── Providers/
│   └── ProductsServiceProvider.php
├── Repositories/
│   └── Interfaces/
├── Services/
│   └── Interfaces/
└── Routes/
    ├── web.php
    └── api.php
```

### 2. Add API Support (Optional)

```bash
php artisan module:make Orders --api
```

This adds API controllers and routes to your module:

```
modules/Orders/
├── Http/
│   ├── Controllers/
│   │   └── API/
│   │       └── OrdersController.php
...
└── Routes/
    ├── web.php
    └── api.php
```

### 3. Add Views (Optional)

```bash
php artisan module:make Customers --with-views
```

This adds view templates and layouts to your module:

```
modules/Customers/
...
├── Resources/
│   └── views/
│       ├── layouts/
│       │   ├── module-layout.blade.php
│       │   └── navigation.blade.php
│       └── customers/
│           ├── index.blade.php
│           ├── create.blade.php
│           ├── edit.blade.php
│           └── show.blade.php
...
```

### 4. Add Livewire Components (Optional)

```bash
php artisan module:make Inventory --with-livewire
```

This adds Livewire components and views to your module:

```
modules/Inventory/
...
├── Livewire/
│   ├── InventoryTable.php
│   └── InventoryForm.php
├── Resources/
│   └── views/
│       └── livewire/
│           ├── inventory-table.blade.php
│           └── inventory-form.blade.php
├── Routes/
│   ├── web.php
│   ├── api.php
│   └── livewire.php
...
```

### 5. Define Your Model

Create a model in your module:

```php
// modules/Products/Models/Product.php
namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'price', 'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
```

### 6. Create a Migration

```bash
php artisan make:migration create_products_table
```

Move this migration to your module's `Database/Migrations` directory and customize it:

```php
// modules/Products/Database/Migrations/xxxx_xx_xx_create_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
```

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
    public function getActive();
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

    public function getActive()
    {
        return $this->model->where('is_active', true)->get();
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
    public function getActiveProducts();
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
        // You can add business logic here before creating
        return $this->repository->create($data);
    }

    public function updateProduct($id, array $data)
    {
        // You can add business logic here before updating
        return $this->repository->update($id, $data);
    }

    public function deleteProduct($id)
    {
        return $this->repository->delete($id);
    }

    public function getActiveProducts()
    {
        return $this->repository->getActive();
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
use Modules\Products\Http\Requests\StoreProductRequest;
use Modules\Products\Http\Requests\UpdateProductRequest;

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

    public function create()
    {
        return view('products::products.create');
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->createProduct($request->validated());
        return redirect()->route('products.show', $product->id)
            ->with('success', 'Product created successfully.');
    }

    public function show($id)
    {
        $product = $this->productService->getProductById($id);
        return view('products::products.show', compact('product'));
    }

    public function edit($id)
    {
        $product = $this->productService->getProductById($id);
        return view('products::products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, $id)
    {
        $product = $this->productService->updateProduct($id, $request->validated());
        return redirect()->route('products.show', $product->id)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy($id)
    {
        $this->productService->deleteProduct($id);
        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }
}
```

For API controllers:

```php
// modules/Products/Http/Controllers/API/ProductsController.php
namespace Modules\Products\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Products\Services\Interfaces\ProductServiceInterface;
use Modules\Products\Http\Requests\StoreProductRequest;
use Modules\Products\Http\Requests\UpdateProductRequest;

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
        return response()->json(['data' => $products]);
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->createProduct($request->validated());
        return response()->json(['data' => $product], 201);
    }

    public function show($id)
    {
        $product = $this->productService->getProductById($id);
        return response()->json(['data' => $product]);
    }

    public function update(UpdateProductRequest $request, $id)
    {
        $product = $this->productService->updateProduct($id, $request->validated());
        return response()->json(['data' => $product]);
    }

    public function destroy($id)
    {
        $this->productService->deleteProduct($id);
        return response()->json(null, 204);
    }
}
```

### 11. Create Form Requests

Generate form requests for validation:

```php
// modules/Products/Http/Requests/StoreProductRequest.php
namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}

// modules/Products/Http/Requests/UpdateProductRequest.php
namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
```

### 12. Add Routes

Routes are already set up in the module, but you may need to customize them:

```php
// modules/Products/Routes/web.php
use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\ProductsController;

Route::middleware('web')->group(function() {
    Route::prefix('products')->group(function() {
        Route::get('/', [ProductsController::class, 'index'])->name('products.index');
        Route::get('/create', [ProductsController::class, 'create'])->name('products.create');
        Route::post('/', [ProductsController::class, 'store'])->name('products.store');
        Route::get('/{id}', [ProductsController::class, 'show'])->name('products.show');
        Route::get('/{id}/edit', [ProductsController::class, 'edit'])->name('products.edit');
        Route::put('/{id}', [ProductsController::class, 'update'])->name('products.update');
        Route::delete('/{id}', [ProductsController::class, 'destroy'])->name('products.destroy');
    });
});

// modules/Products/Routes/api.php
use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\API\ProductsController;

Route::middleware('api')->prefix('api')->group(function() {
    Route::apiResource('products', ProductsController::class);
});
```

### 13. Create Livewire Components (Optional)

You can add Livewire components to an existing module:

```bash
php artisan module:make-livewire Products ProductForm
php artisan module:make-livewire Products ProductTable
```

Update the components to work with your services:

```php
// modules/Products/Livewire/ProductForm.php
namespace Modules\Products\Livewire;

use Livewire\Component;
use Modules\Products\Services\Interfaces\ProductServiceInterface;

class ProductForm extends Component
{
    public $name;
    public $description;
    public $price;
    public $is_active = true;
    public $product;
    public $editing = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'is_active' => 'boolean',
    ];

    public function mount($productId = null, ProductServiceInterface $productService)
    {
        if ($productId) {
            $this->editing = true;
            $this->product = $productService->getProductById($productId);
            $this->name = $this->product->name;
            $this->description = $this->product->description;
            $this->price = $this->product->price;
            $this->is_active = $this->product->is_active;
        }
    }

    public function save(ProductServiceInterface $productService)
    {
        $validatedData = $this->validate();

        if ($this->editing) {
            $productService->updateProduct($this->product->id, $validatedData);
            $this->dispatch('productUpdated');
        } else {
            $productService->createProduct($validatedData);
            $this->dispatch('productCreated');
        }

        $this->reset(['name', 'description', 'price']);
        $this->is_active = true;

        session()->flash('success',
            $this->editing ? 'Product updated successfully!' : 'Product created successfully!');
    }

    public function render()
    {
        return view('products::livewire.product-form');
    }
}
```

Use the component in your views:

```php
@livewire('products::product-form')
```

## Available Commands

### Module Management

```bash
# Create a new module
php artisan module:make ModuleName [--api] [--with-views] [--with-livewire] [--force]

# Enable or disable a module
php artisan module:toggle ModuleName

# Export a module as a package
php artisan module:export ModuleName
```

### Authentication Scaffolding

```bash
# Generate authentication scaffolding for a module
php artisan module:make-auth ModuleName [--force]
```

This command creates a complete Laravel authentication system within your module, including:

- Login and registration controllers
- Password reset functionality
- Email verification
- Authentication middleware
- Blade templates with Tailwind CSS styling
- Authentication routes

The generated authentication system uses pure Blade templates (no Livewire) and follows Laravel's best practices while maintaining a clean, modular structure.

When you run this command, it generates the following structure in your module:

```
modules/YourModule/
├── Http/
│   ├── Controllers/
│   │   └── Auth/                       # Authentication controllers
│   │       ├── LoginController.php
│   │       ├── RegisterController.php
│   │       ├── ForgotPasswordController.php
│   │       ├── ResetPasswordController.php
│   │       └── VerifyEmailController.php
│   └── Middleware/                     # Authentication middleware
│       ├── Authenticate.php
│       └── RedirectIfAuthenticated.php
├── Resources/
│   └── views/
│       ├── auth/                       # Authentication views
│       │   ├── login.blade.php
│       │   ├── register.blade.php
│       │   ├── forgot-password.blade.php
│       │   ├── reset-password.blade.php
│       │   └── verify-email.blade.php
│       └── layouts/
│           └── auth-layout.blade.php   # Authentication layout
└── Routes/
    └── auth.php                        # Authentication routes
```

#### Sample Generated Code

**LoginController.php:**

```php
namespace Modules\YourModule\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('yourmodule::auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('yourmodule.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('yourmodule.login');
    }
}
```

**Auth Routes (auth.php):**

```php
use Illuminate\Support\Facades\Route;
use Modules\YourModule\Http\Controllers\Auth\LoginController;
use Modules\YourModule\Http\Controllers\Auth\RegisterController;
use Modules\YourModule\Http\Controllers\Auth\ForgotPasswordController;
use Modules\YourModule\Http\Controllers\Auth\ResetPasswordController;
use Modules\YourModule\Http\Controllers\Auth\VerifyEmailController;

Route::middleware('web')->group(function () {
    // Guest routes
    Route::middleware('module.guest')->group(function () {
        // Login routes
        Route::get('/login', [LoginController::class, 'showLoginForm'])
            ->name('yourmodule.login');
        Route::post('/login', [LoginController::class, 'login']);

        // Registration routes
        Route::get('/register', [RegisterController::class, 'showRegistrationForm'])
            ->name('yourmodule.register');
        Route::post('/register', [RegisterController::class, 'register']);

        // Password reset routes
        Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])
            ->name('yourmodule.password.request');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
            ->name('yourmodule.password.email');
        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
            ->name('yourmodule.password.reset');
        Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
            ->name('yourmodule.password.update');
    });

    // Auth routes
    Route::middleware('module.auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])
            ->name('yourmodule.logout');

        // Email verification routes
        Route::get('/email/verify', [VerifyEmailController::class, 'show'])
            ->name('yourmodule.verification.notice');
        Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
            ->name('yourmodule.verification.verify');
        Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
            ->name('yourmodule.verification.send');
    });
});
```

All files use your module's namespace and include clean, Tailwind CSS-styled views for a modern UI experience. The authentication system is fully contained within your module while following Laravel's authentication patterns and best practices.

### Component Generation

```bash
# Add a Livewire component to a module
php artisan module:make-livewire ModuleName ComponentName [--force] [--subdirectory=Subfolder] [--view-only] [--class-only]

# Create module-specific events
php artisan module:make-event ModuleName EventName

# Create module-specific translations
php artisan module:make-translation ModuleName Language
```

### Customization

```bash
# Publish stubs for customization
php artisan module:publish-stubs
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
│   ├── api.php                 # Module API routes
│   └── livewire.php            # Livewire-specific routes
├── Config/                     # Module config files
└── Database/
    ├── Migrations/             # Module-specific migrations
    ├── Seeders/                # Module-specific seeders
    └── Factories/              # Model factories
```

## Module Lifecycle

### Auto-Discovery Process

The package's service provider automatically:

1. Scans the configured modules directory
2. Registers each module's service provider
3. Loads routes (web, API, and Livewire)
4. Registers views, translations, and migrations
5. Auto-registers Livewire components
6. Loads module-specific assets and configurations

### Enabling and Disabling Modules

Modules can be enabled or disabled without removing their code:

```bash
# Disable a module
php artisan module:toggle ModuleName --disable

# Enable a module
php artisan module:toggle ModuleName --enable
```

When a module is disabled:

- Its routes are not registered
- Its service provider is not loaded
- Its views and translations are not available
- Its Livewire components are not registered

### Exporting Modules

Modules can be exported as standalone packages:

```bash
php artisan module:export ModuleName
```

This creates a package in the `packages` directory with:

- A properly structured Laravel package
- Composer configuration
- Service provider
- All module files

## Design Patterns

### Repository Pattern

The repository pattern separates data access logic from business logic:

```php
// Interface defines the contract
interface ProductRepositoryInterface { ... }

// Implementation handles actual data access
class ProductRepository implements ProductRepositoryInterface { ... }
```

Benefits:

- Makes code more testable by allowing mock repositories in tests
- Centralizes data access logic
- Enables easy swapping of data sources without affecting business logic

### Service Layer Pattern

The service layer contains business logic:

```php
// Interface defines the contract
interface ProductServiceInterface { ... }

// Implementation contains business rules
class ProductService implements ProductServiceInterface { ... }
```

Benefits:

- Separates business logic from controllers
- Promotes reusability across controllers (web, API)
- Makes business rules explicit and testable

## Facade Usage

The package provides a facade for easier access to the modularization service:

```php
use NgarakDev\Modularization\Facades\Modularization;

// Get all modules
$modules = Modularization::getModules();

// Check if a module exists
if (Modularization::hasModule('Products')) {
    // ...
}

// Check if a module is enabled
if (Modularization::isEnabled('Products')) {
    // ...
}

// Enable a module
Modularization::enable('Products');

// Disable a module
Modularization::disable('Products');
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
    'directories' => [
        'Http/Controllers',
        'Http/Controllers/API',
        'Http/Middleware',
        'Http/Requests',
        'Models',
        'Repositories',
        'Repositories/Interfaces',
        'Services',
        'Services/Interfaces',
        'Providers',
        'Database/Migrations',
        'Database/Seeders',
        'Database/Factories',
        'Routes',
        'Config',
        'Resources/views',
        'Resources/lang',
        'Livewire',
        'Tests/Unit',
        'Tests/Feature',
    ],

    // Auto-register controllers with routes
    'auto_register_controllers' => true,

    // Auto-register Livewire components
    'auto_register_livewire' => true,

    // Enforce repository interface implementation
    'enforce_repository_pattern' => true,
];
```

## Stub Customization

You can publish and customize the stubs used for code generation:

```bash
php artisan module:publish-stubs
```

This will copy all stubs to `stubs/vendor/modularization/` in your project root, including:

- Module layouts and views
- Controllers (web and API)
- Repository interfaces and implementations
- Service interfaces and implementations
- Livewire components and views
- Route files
- And more

You can then edit these stubs to match your coding style and requirements.

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
// In one module, create an event
namespace Modules\Orders\Events;

class OrderCreated
{
    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }
}

// Dispatch the event
event(new \Modules\Orders\Events\OrderCreated($order));

// In another module's service provider, listen for the event
$this->app['events']->listen(
    \Modules\Orders\Events\OrderCreated::class,
    function ($event) {
        // Handle event
    }
);
```

### Adding Custom Module Commands

You can create custom commands for your modules:

1. Create a Commands directory in your module
2. Create your command class
3. Register it in your module's service provider

```php
// In your module's service provider
public function boot()
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            \Modules\YourModule\Commands\YourCustomCommand::class,
        ]);
    }
}
```

## Troubleshooting

### Common Issues

**Issue**: Module views not found
**Solution**: Ensure you're using the correct namespace: `modulename::view-name`

**Issue**: Service bindings not working
**Solution**: Make sure your module's service provider is properly registered and bindings are in the `register()` method

**Issue**: Routes not accessible
**Solution**: Check that your routes are properly defined with the correct middleware and namespaces

### Debugging

To debug module discovery and registration:

```php
// Get all registered modules
$modules = app('modularization')->getModules();
dd($modules);

// Check if a specific module is enabled
$isEnabled = app('modularization')->isEnabled('ModuleName');
dd($isEnabled);
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
