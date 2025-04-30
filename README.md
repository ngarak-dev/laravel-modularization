# Laravel Modular Architecture with Repository Pattern

This package implements a modular architecture in Laravel, combining the Repository Pattern and Service Layer to create maintainable, scalable applications.

## Features

- **Modular Architecture**: Organize code by business domain
- **Repository Pattern**: Clean separation between data access and business logic
- **Service Layer**: Domain-specific business logic encapsulation
- **Auto-Discovery**: Automatic module registration
- **View Integration**: Independent views per module
- **API Ready**: Built-in API controllers and routes
- **Livewire Support**: Create interactive UIs with auto-registered Livewire components

## Installation

You can install the package via composer:

```bash
composer require ngarak-dev/laravel-modularization
```

After installing the package, publish the config file:

```bash
php artisan vendor:publish --provider="NgarakDev\Modularization\Providers\ModularizationServiceProvider" --tag="modularization-config"
```

## Creating Modules

Create a new module with the following command:

```bash
# Basic module
php artisan module:make ModuleName

# With API controllers
php artisan module:make ModuleName --api

# With views
php artisan module:make ModuleName --with-views

# With Livewire components
php artisan module:make ModuleName --with-livewire

# Force overwrite existing module
php artisan module:make ModuleName --force
```

## Creating Livewire Components

Add a Livewire component to an existing module:

```bash
php artisan module:make-livewire ModuleName ComponentName

# Options:
# --force            Overwrite existing files
# --subdirectory=    Create component in a subdirectory
# --view-only        Create only the view file
# --class-only       Create only the component class
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
├── resources/
│   ├── views/                  # Module-specific views
│   │   └── livewire/           # Livewire component views
│   └── lang/                   # Module-specific translations
├── routes/
│   ├── web.php                 # Module web routes
│   └── api.php                 # Module API routes
├── config/                     # Module config files
└── database/
    ├── migrations/             # Module-specific migrations
    ├── seeders/                # Module-specific seeders
    └── factories/              # Model factories
```

## Architecture Flow

1. **Routes** direct requests to **Controllers**
2. **Controllers** validate input using **Requests**
3. **Controllers** delegate business logic to **Services**
4. **Services** implement business rules and use **Repositories** for data access
5. **Repositories** handle all database interactions through **Models**
6. **Livewire Components** handle real-time UI interactions

## Configuration

You can customize the package by editing the published config file at `config/modularization.php`.

## Best Practices

### Module Organization

- Each module should represent a bounded context or business domain
- Keep modules independent of each other when possible
- Use events for cross-module communication

### Repositories

- Always code to interfaces
- Keep repositories focused on single model/entity
- Implement only data access logic, no business rules

### Services

- Implement business rules and workflows
- Use dependency injection for repositories and other services
- Keep methods focused on specific use cases

### Controllers

- Keep thin - delegate to services
- Focus on request/response handling
- Use form requests for validation

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
