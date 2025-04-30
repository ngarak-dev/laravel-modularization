<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Directory
    |--------------------------------------------------------------------------
    |
    | This is the path where all modules will be stored. This path is relative
    | to the application base path.
    |
    */
    'modules_path' => 'modules',

    /*
    |--------------------------------------------------------------------------
    | Module Namespace
    |--------------------------------------------------------------------------
    |
    | Define the namespace for your modules. All modules will be created under
    | this namespace. Default is "Modules".
    |
    */
    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Module Directories
    |--------------------------------------------------------------------------
    |
    | These are the default directories that will be created within each module.
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
        'database/migrations',
        'database/seeders',
        'database/factories',
        'routes',
        'config',
        'resources/views',
        'resources/lang',
        'Livewire',
        'Tests/Unit',
        'Tests/Feature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-register Controllers
    |--------------------------------------------------------------------------
    |
    | If set to true, controllers will be automatically registered with Laravel's
    | route system using the appropriate middleware and namespaces.
    |
    */
    'auto_register_controllers' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-register Livewire Components
    |--------------------------------------------------------------------------
    |
    | If set to true, Livewire components will be automatically registered.
    |
    */
    'auto_register_livewire' => true,

    /*
    |--------------------------------------------------------------------------
    | Repository Pattern Implementation
    |--------------------------------------------------------------------------
    |
    | Controls whether to force the use of repository interfaces. If true,
    | all repositories must implement their corresponding interface.
    |
    */
    'enforce_repository_pattern' => true,
];
