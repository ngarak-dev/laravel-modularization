<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modules Directory
    |--------------------------------------------------------------------------
    |
    | The path where all modules will be stored. This is relative to the
    | application base path (i.e. the directory containing artisan).
    |
    */
    'modules_path' => 'modules',

    /*
    |--------------------------------------------------------------------------
    | Module Namespace
    |--------------------------------------------------------------------------
    |
    | The root namespace applied to all modules. By default this is "Modules",
    | meaning a module called "Orders" will have the namespace Modules\Orders.
    |
    */
    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Default Module Directories
    |--------------------------------------------------------------------------
    |
    | Directories created automatically when generating a new module.
    | Paths are relative to the module root.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Auto-register Livewire Components
    |--------------------------------------------------------------------------
    |
    | When true and Livewire is installed, components in each module's Livewire/
    | directory are registered automatically. Set to false to manage component
    | registration manually in your module's service provider.
    |
    */
    'auto_register_livewire' => true,

    /*
    |--------------------------------------------------------------------------
    | Repository Pattern Enforcement
    |--------------------------------------------------------------------------
    |
    | When true, the module generator will always create repository interfaces
    | alongside implementations. This is a generator hint, not a runtime check.
    |
    */
    'enforce_repository_pattern' => true,

];
