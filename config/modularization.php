<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modules Directory
    |--------------------------------------------------------------------------
    |
    | The directory where your modules are stored. This path is relative to
    | the application base path unless it starts with a forward slash.
    |
    */
    'modules_path' => env('MODULES_PATH', 'modules'),

    /*
    |--------------------------------------------------------------------------
    | Module Namespace
    |--------------------------------------------------------------------------
    |
    | The base namespace for all modules. This namespace must be registered
    | in your composer.json autoload configuration.
    |
    | Example composer.json:
    | "autoload": {
    |     "psr-4": {
    |         "Modules\\": "modules/"
    |     }
    | }
    |
    */
    'namespace' => env('MODULES_NAMESPACE', 'Modules'),

    /*
    |--------------------------------------------------------------------------
    | Default Module Directories
    |--------------------------------------------------------------------------
    |
    | The directories that will be created within each new module. These
    | follow Laravel conventions while supporting the Repository Pattern
    | and Service Layer architecture.
    |
    */
    'directories' => [
        'Config',
        'Database/Factories',
        'Database/Migrations',
        'Database/Seeders',
        'Http/Controllers',
        'Http/Controllers/API',
        'Http/Middleware',
        'Http/Requests',
        'Livewire',
        'Models',
        'Providers',
        'Repositories',
        'Repositories/Interfaces',
        'Resources/assets/css',
        'Resources/assets/js',
        'Resources/lang',
        'Resources/views',
        'Routes',
        'Services',
        'Services/Interfaces',
        'Tests/Feature',
        'Tests/Unit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Register Service Providers
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register each module's
    | service provider during application boot.
    |
    */
    'auto_register_providers' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Register Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically load routes from each
    | module's Routes directory (web.php, api.php, livewire.php).
    |
    */
    'auto_register_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Register Views
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register each module's
    | views with a namespace matching the module name (lowercase).
    |
    | Example: @extends('products::layouts.app')
    |
    */
    'auto_register_views' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Register Translations
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register each module's
    | translations from Resources/lang.
    |
    | Example: __('products::messages.welcome')
    |
    */
    'auto_register_translations' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Register Migrations
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register each module's
    | migrations from Database/Migrations.
    |
    */
    'auto_register_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Register Livewire Components
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register Livewire
    | components from each module's Livewire directory.
    |
    | Example: <livewire:products.product-table />
    |
    */
    'auto_register_livewire' => true,

    /*
    |--------------------------------------------------------------------------
    | Repository Pattern Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, generated controllers and services will follow the
    | Repository Pattern, with data access abstracted through repository
    | interfaces.
    |
    | Note: This is a generator setting, not a runtime enforcement.
    |
    */
    'enforce_repository_pattern' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure module caching behavior. When caching is enabled and cached
    | data exists, modules will be loaded from cache instead of scanning
    | the filesystem.
    |
    | Use `php artisan module:cache` to generate the cache.
    | Use `php artisan module:clear` to clear the cache.
    |
    */
    'cache' => [
        'enabled' => env('MODULES_CACHE', false),
        'path' => env('MODULES_CACHE_PATH', null), // null = bootstrap/cache/modules.php
    ],

    /*
    |--------------------------------------------------------------------------
    | Stubs Path
    |--------------------------------------------------------------------------
    |
    | The path to custom stubs for module generation. If a stub exists at
    | this location, it will be used instead of the package defaults.
    |
    | Publish stubs with: php artisan module:publish-stubs
    |
    */
    'stubs_path' => base_path('stubs/vendor/modularization'),

];
