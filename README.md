# Laravel Modularization

<p align="center">
<a href="https://github.com/ngarak-dev/laravel-modularization/actions"><img src="https://github.com/ngarak-dev/laravel-modularization/workflows/Tests/badge.svg" alt="Tests"></a>
<a href="https://packagist.org/packages/ngarak-dev/laravel-modularization"><img src="https://img.shields.io/packagist/v/ngarak-dev/laravel-modularization.svg" alt="Latest Version"></a>
<a href="https://packagist.org/packages/ngarak-dev/laravel-modularization"><img src="https://img.shields.io/packagist/dt/ngarak-dev/laravel-modularization.svg" alt="Total Downloads"></a>
<a href="https://github.com/ngarak-dev/laravel-modularization/blob/main/LICENSE.md"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License"></a>
</p>

Organize large Laravel applications by **business domain** rather than technical layer. Each module is a self-contained unit with its own controllers, models, repositories, services, views, routes, migrations, and tests — living under `modules/`.

This package is **not** a clone of `nwidart/laravel-modules`. Its focus is on the **Repository Pattern**, **Service Layer**, and **domain-oriented organization** with a minimal footprint. It generates clean, modern PHP that your team will actually want to maintain.

---

## Table of Contents

- [Why Modular?](#why-modular)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Module Structure](#module-structure)
- [Module Manifest (module.json)](#module-manifest-modulejson)
- [Module Dependencies](#module-dependencies)
- [Generating Classes](#generating-classes)
- [Routes](#routes)
- [Controllers](#controllers)
- [Services and Repositories](#services-and-repositories)
- [Models](#models)
- [Views](#views)
- [API Modules](#api-modules)
- [Livewire Components](#livewire-components)
- [Events](#events)
- [Translations](#translations)
- [Enabling and Disabling Modules](#enabling-and-disabling-modules)
- [Caching Module Metadata](#caching-module-metadata)
- [Available Commands](#available-commands)
- [Testing Modules](#testing-modules)
- [Configuration](#configuration)
- [Stub Customization](#stub-customization)
- [Upgrade Guide](#upgrade-guide)
- [Contributing](#contributing)
- [License](#license)

---

## Why Modular?

A standard Laravel application organizes code by technical layer: `app/Http/Controllers`, `app/Models`, `app/Services`. This works well for small applications but breaks down as the codebase grows, because every feature is scattered across many directories.

Modular architecture organizes code by **business domain** instead:

```
modules/
  Billing/
  Orders/
  Inventory/
  Users/
```

Each module is cohesive and independently understandable. A developer working on `Billing` only needs to look inside `modules/Billing/`.

This package enforces the **Repository Pattern** and **Service Layer** as first-class architectural patterns within each module, making data access and business logic explicitly separated and easily testable.

---

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- Composer

Optional:
- Livewire 3.x (for `--with-livewire` support)

---

## Installation

```bash
composer require ngarak-dev/laravel-modularization
```

The package is auto-discovered by Laravel. Publish the configuration file:

```bash
php artisan vendor:publish --tag=modularization-config
```

This creates `config/modularization.php`.

---

## Quick Start

```bash
# Create a module
php artisan module:make Billing

# List all modules and their status
php artisan module:list

# Your new module is ready at:
# modules/Billing/
```

---

## Module Structure

A generated module looks like this:

```
modules/
  Billing/
    Config/
      config.php
    Http/
      Controllers/
        BillingController.php
    Models/
      Billing.php
    Providers/
      BillingServiceProvider.php
    Repositories/
      Interfaces/
        BillingRepositoryInterface.php
      BillingRepository.php
    Resources/
      lang/
        en/
          general.php
          validation.php
          module.php
      views/
        index.blade.php
        create.blade.php
        edit.blade.php
        show.blade.php
        layout.blade.php
    Routes/
      web.php
      api.php
    Services/
      Interfaces/
        BillingServiceInterface.php
      BillingService.php
    database/
      migrations/
    module.json
```

---

## Module Manifest (module.json)

Each module contains a `module.json` file that acts as its machine-readable contract:

```json
{
    "name": "Billing",
    "namespace": "Modules\\Billing",
    "version": "1.0.0",
    "description": "Handles billing, invoices, and payment processing",
    "enabled": true,
    "requires": [
        "Users"
    ]
}
```

Fields:

| Field | Description |
|-------|-------------|
| `name` | Module name (matches directory name) |
| `namespace` | PHP namespace root for this module |
| `version` | Semver version string |
| `description` | Human-readable description |
| `enabled` | Whether this module is active |
| `requires` | Other module names this module depends on |

---

## Module Dependencies

Declare inter-module dependencies in `module.json`:

```json
{
    "name": "Orders",
    "requires": ["Users", "Products"]
}
```

The package will:

1. Validate that all required modules exist and are enabled at boot.
2. Detect circular dependencies (`A → B → A`).
3. Load modules in a safe dependency order (dependencies first).
4. Throw a descriptive `ModuleDependencyException` with a fix hint when dependencies are missing.

---

## Generating Classes

### Create a new module

```bash
php artisan module:make ModuleName
```

Options:

```bash
--api                   Include API routes and controller
--with-views            Include Blade views
--with-livewire         Include a Livewire component
--with-livewire-only    Only create a Livewire component (no traditional controller)
--with-crud             Generate CRUD operations
--resource=Name         Add a named resource (can be used multiple times)
--with-translation      Generate translation files
--force                 Overwrite existing files
```

Examples:

```bash
# Full module with API, views, and CRUD
php artisan module:make Shop --api --with-views --with-crud

# Module with a Livewire component for the main entity
php artisan module:make Dashboard --with-livewire

# Module with multiple resources
php artisan module:make Catalog --resource=Product --resource=Category

# Module with translation files
php artisan module:make Blog --with-translation
```

### Generate inside an existing module

```bash
# Livewire component
php artisan module:make-livewire ComponentName --module=ModuleName

# Event and listener
php artisan module:make-event EventName --module=ModuleName

# Translation files
php artisan module:make-translation --module=ModuleName --lang=en,fr,es

# Migration
php artisan module:make-migration create_orders_table --module=Orders
```

---

## Routes

### Web Routes

Each module's `Routes/web.php` is auto-loaded and namespaced:

```php
// modules/Billing/Routes/web.php
use Modules\Billing\Http\Controllers\BillingController;

Route::prefix('billing')->name('billing.')->group(function () {
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::get('/create', [BillingController::class, 'create'])->name('create');
    Route::post('/', [BillingController::class, 'store'])->name('store');
    Route::get('/{id}', [BillingController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [BillingController::class, 'edit'])->name('edit');
    Route::put('/{id}', [BillingController::class, 'update'])->name('update');
    Route::delete('/{id}', [BillingController::class, 'destroy'])->name('destroy');
});
```

### API Routes

API routes at `Routes/api.php` are automatically loaded under the `api` middleware group:

```php
// modules/Billing/Routes/api.php
use Modules\Billing\Http\Controllers\BillingController;

Route::prefix('billing')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('invoices', BillingController::class);
});
```

---

## Controllers

Generated controllers receive the module service via constructor injection:

```php
// modules/Billing/Http/Controllers/BillingController.php
namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Billing\Services\Interfaces\BillingServiceInterface;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingServiceInterface $billingService,
    ) {}

    public function index()
    {
        $items = $this->billingService->getAll();
        return view('billing::index', compact('items'));
    }
}
```

---

## Services and Repositories

### Service Interface

```php
// modules/Billing/Services/Interfaces/BillingServiceInterface.php
namespace Modules\Billing\Services\Interfaces;

interface BillingServiceInterface
{
    public function getAll(): mixed;
    public function create(array $data): mixed;
    public function update(int $id, array $data): mixed;
    public function delete(int $id): bool;
}
```

### Service Implementation

```php
// modules/Billing/Services/BillingService.php
namespace Modules\Billing\Services;

use Modules\Billing\Repositories\Interfaces\BillingRepositoryInterface;
use Modules\Billing\Services\Interfaces\BillingServiceInterface;

class BillingService implements BillingServiceInterface
{
    public function __construct(
        private readonly BillingRepositoryInterface $repository,
    ) {}

    public function getAll(): mixed
    {
        return $this->repository->all();
    }

    public function create(array $data): mixed
    {
        return $this->repository->create($data);
    }

    public function update(int $id, array $data): mixed
    {
        return $this->repository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
```

### Repository Interface

```php
// modules/Billing/Repositories/Interfaces/BillingRepositoryInterface.php
namespace Modules\Billing\Repositories\Interfaces;

interface BillingRepositoryInterface
{
    public function all(): mixed;
    public function create(array $data): mixed;
    public function update(int $id, array $data): mixed;
    public function delete(int $id): bool;
}
```

### Repository Implementation

```php
// modules/Billing/Repositories/BillingRepository.php
namespace Modules\Billing\Repositories;

use Modules\Billing\Models\Billing;
use Modules\Billing\Repositories\Interfaces\BillingRepositoryInterface;

class BillingRepository implements BillingRepositoryInterface
{
    public function all(): mixed
    {
        return Billing::all();
    }

    public function create(array $data): mixed
    {
        return Billing::create($data);
    }

    public function update(int $id, array $data): mixed
    {
        return Billing::findOrFail($id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) Billing::destroy($id);
    }
}
```

### Binding in the Service Provider

The generated `BillingServiceProvider` binds interfaces to implementations:

```php
// modules/Billing/Providers/BillingServiceProvider.php
$this->app->bind(BillingRepositoryInterface::class, BillingRepository::class);
$this->app->bind(BillingServiceInterface::class, BillingService::class);
```

> **When to use the Repository Pattern:** Use it when you need to abstract the data layer (e.g., switching from Eloquent to an API), write unit tests without hitting a database, or reuse query logic across multiple services. For simple CRUD that will never change its data source, direct Eloquent usage is perfectly fine.

---

## Models

Models live at `modules/ModuleName/Models/`:

```php
// modules/Billing/Models/Invoice.php
namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['amount', 'user_id', 'status'];
}
```

---

## Views

Module views live at `modules/ModuleName/Resources/views/` and are accessed via the module alias:

```php
// In a controller
return view('billing::index');
return view('billing::invoices.show', compact('invoice'));
```

The view namespace is derived from the lowercase module name.

---

## API Modules

Create a module with API support:

```bash
php artisan module:make Orders --api
```

This generates:
- `Routes/api.php` with RESTful routes
- `Http/Controllers/OrdersController.php` extending `Controller` (no view methods)

---

## Livewire Components

Requires [Livewire 3](https://livewire.laravel.com):

```bash
php artisan module:make Dashboard --with-livewire
# or add a component to an existing module:
php artisan module:make-livewire StatsWidget --module=Dashboard
```

Components are automatically registered via the module's service provider using Livewire's component alias system.

---

## Events

```bash
php artisan module:make-event OrderPlaced --module=Orders
```

This generates an event class and a listener. Bind them in the module's service provider:

```php
protected $listen = [
    OrderPlaced::class => [
        SendOrderConfirmation::class,
    ],
];
```

---

## Translations

```bash
# Generate translation files
php artisan module:make-translation --module=Billing --lang=en,fr

# Or include during module creation:
php artisan module:make Billing --with-translation
```

Translation files are placed at `modules/Billing/Resources/lang/{locale}/`:

```php
// Use in views or controllers:
__('billing::general.title')
trans('billing::validation.required')
```

---

## Enabling and Disabling Modules

```bash
# Disable a module
php artisan module:toggle Billing --disable

# Enable a module
php artisan module:toggle Billing --enable

# List all modules and their status
php artisan module:list
```

The enable/disable state is persisted via a `.disabled` sentinel file inside the module directory. A disabled module is not booted and its routes, views, and migrations are not registered.

The `module.json` file's `enabled` field is also updated for human readability.

---

## Caching Module Metadata

In production, avoid scanning the filesystem on every request by caching module metadata:

```bash
# Cache the module list (run as part of deployment)
php artisan module:cache

# Clear the cache (run before module:cache on re-deploy)
php artisan module:clear
```

The cache is stored at `bootstrap/cache/modules.php`. This file should be regenerated during every deployment. Add these commands to your deployment pipeline alongside `config:cache` and `route:cache`.

---

## Available Commands

| Command | Description |
|---------|-------------|
| `module:make {name}` | Create a new module |
| `module:list` | List all modules with status |
| `module:toggle {name}` | Enable or disable a module |
| `module:cache` | Cache module metadata for faster boot |
| `module:clear` | Remove the module metadata cache |
| `module:make-livewire {name}` | Generate a Livewire component inside a module |
| `module:make-event {name}` | Generate an event and listener inside a module |
| `module:make-translation` | Generate translation files for a module |
| `module:make-migration {name}` | Generate a migration inside a module |
| `module:migrate {name}` | Run migrations for a specific module |
| `module:migrate-all` | Run migrations for all enabled modules |
| `module:make-auth {name}` | Scaffold authentication inside a module |
| `module:make-manager {name}` | Scaffold a module manager dashboard |
| `module:export {name}` | Export a module as a standalone Composer package |
| `module:publish-stubs` | Publish stubs to your application for customization |

---

## Testing Modules

Place module tests inside the module itself or in the application's `tests/` directory. The package provides no test infrastructure of its own — use Laravel's standard testing tools.

Example feature test for a module:

```php
<?php

namespace Tests\Feature\Billing;

use Tests\TestCase;

class InvoiceTest extends TestCase
{
    public function test_authenticated_user_can_create_invoice(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post(route('billing.store'), [
            'amount' => 100.00,
        ]);

        $response->assertRedirect(route('billing.index'));
        $this->assertDatabaseHas('invoices', ['amount' => 100.00]);
    }
}
```

Run module-specific tests:

```bash
php artisan test --filter=Billing
```

---

## Configuration

```bash
php artisan vendor:publish --tag=modularization-config
```

`config/modularization.php`:

```php
return [
    // Where modules live relative to the application root
    'modules_path' => 'modules',

    // Default namespace root for all modules
    'namespace' => 'Modules',

    // Directory names used when generating a module
    'directories' => [
        'Http/Controllers',
        'Models',
        'Providers',
        'Repositories/Interfaces',
        'Repositories',
        'Services/Interfaces',
        'Services',
        'Repositories',
        'Resources/views',
        'Resources/lang/en',
        'Routes',
        'Config',
        'database/migrations',
    ],

    // Set to true to enforce that every module must use the Repository Pattern
    'enforce_repository_pattern' => false,

    // Set to true to auto-register Livewire components found in modules
    'auto_register_livewire' => true,
];
```

---

## Stub Customization

Publish the package stubs to your application:

```bash
php artisan module:publish-stubs
```

Stubs are published to `stubs/vendor/modularization/`. Edit them to match your team's conventions. The package uses your custom stubs automatically when it detects them.

---

## Upgrade Guide

### Upgrading from < 1.1.0

**Breaking changes:**

- `php:^8.0` minimum has been raised to `php:^8.1`.
- `ModularizationService` no longer accepts `Filesystem` as its first constructor parameter. If you manually construct this class, remove that argument.
- Exception classes in `src/Exceptions/` are now `final`. If you extended them, use composition instead.

**New features:**

- `module.json` manifest with `requires` dependency support.
- `module:cache` / `module:clear` commands for production caching.
- `module:list` command with table and JSON output.
- `ModuleDiscovery`, `ModuleStatusManager`, `ModuleCache`, `ModuleDependencyResolver` support classes.
- `Module` value object with `fromManifest()` and `fromDirectory()` constructors.
- `Modularization::find()`, `findOrFail()`, `getEnabledModules()`, `getDisabledModules()`, `refresh()` facade methods.
- PHPStan level 5 static analysis in CI.
- Laravel Pint code style enforcement in CI.

---

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-improvement`.
3. Make your changes.
4. Run the test suite: `vendor/bin/phpunit`.
5. Run code style checks: `vendor/bin/pint`.
6. Run static analysis: `vendor/bin/phpstan analyse`.
7. Submit a pull request.

---

## License

The MIT License. See [LICENSE.md](LICENSE.md).
