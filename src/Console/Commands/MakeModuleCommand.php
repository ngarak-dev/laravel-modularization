<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module
                        {name : The name of the module}
                        {--api : Generate API controller and routes}
                        {--force : Force overwrite if module already exists}
                        {--resource= : Create a resource within the module (can specify multiple separated by comma)}
                        {--with-views : Generate view files for the module}
                        {--with-livewire : Generate Livewire components}
                        {--with-livewire-only : Generate only Livewire components without controllers}
                        {--with-crud : Generate CRUD operations}
                        {--with-translations : Generate translation files (en, es, fr, de)}
                        {--languages=* : Specify languages for translation files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new module with repository pattern and service layer';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $namespace = config('modularization.namespace', 'Modules');
        $path = $modulesPath . '/' . $name;
        $withApi = $this->option('api');
        $withViews = $this->option('with-views');
        $withLivewire = $this->option('with-livewire');
        $withLivewireOnly = $this->option('with-livewire-only');
        $withCrud = $this->option('with-crud');
        $resourceOption = $this->option('resource');
        $withTranslations = $this->option('with-translations');
        $languages = $this->option('languages');

        // Parse multiple resources if provided
        $resources = [];
        if (!empty($resourceOption)) {
            $resources = array_map('trim', explode(',', $resourceOption));
        }

        // Check if module exists and handle --force option
        if ($this->files->isDirectory($path)) {
            if ($this->option('force')) {
                $this->files->deleteDirectory($path);
            } else {
                if (!$this->confirm("Module [{$name}] already exists. Do you want to overwrite it?")) {
                    $this->error('Module creation aborted!');
                    return 1;
                }
                $this->files->deleteDirectory($path);
            }
        }

        // Ensure Modules namespace is in composer.json
        $this->ensureModulesNamespaceInComposer($namespace, $modulesPath);

        // Create module directories
        $this->createModuleDirectories($path);

        // Create module files
        $this->createModuleFiles($name, $path, $namespace, $withApi, $withViews, $withLivewire, $withLivewireOnly, $resources, $withCrud);

        // Create translation files if needed
        if ($withTranslations) {
            $this->createTranslationFiles($name, $path, $languages);
        }

        // Update modules config
        $this->updateModulesConfig($name);

        // Output next steps
        $this->outputNextSteps($name);

        $this->info("Module [{$name}] created successfully.");
        return 0;
    }

    /**
     * Create necessary directories for the module.
     *
     * @param  string  $path
     * @return void
     */
    protected function createModuleDirectories($path)
    {
        // Base directories
        $directories = [
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
            'Resources/assets/js',
            'Resources/assets/css',
            'Livewire',
            'Tests/Unit',
            'Tests/Feature',
        ];

        // Create each directory
        foreach ($directories as $directory) {
            $directoryPath = $path . '/' . $directory;
            if (!$this->files->isDirectory($directoryPath)) {
                $this->files->makeDirectory($directoryPath, 0755, true);
            }
        }

        $this->info('Module directories created successfully.');
    }

    /**
     * Create necessary files for the module.
     *
     * @param  string  $name
     * @param  string  $path
     * @param  string  $namespace
     * @param  bool    $withApi
     * @param  bool    $withViews
     * @param  bool    $withLivewire
     * @param  bool    $withLivewireOnly
     * @param  array   $resources
     * @param  bool    $withCrud
     * @return void
     */
    protected function createModuleFiles($name, $path, $namespace, $withApi, $withViews, $withLivewire, $withLivewireOnly, $resources, $withCrud)
    {
        // Create service provider
        $this->createServiceProvider($name, $path, $namespace);

        // Create module config
        $this->createConfig($name, $path);

        // Create base module files if no specific resources defined or if resources exist but we also want base module files
        if (empty($resources) || (!$withLivewireOnly)) {
            // Create model
            $this->createModel($name, $path, $namespace);

            // Create repositories
            $this->createRepositoryInterface($name, $path, $namespace);
            $this->createRepository($name, $path, $namespace);

            // Create services
            $this->createServiceInterface($name, $path, $namespace);
            $this->createService($name, $path, $namespace);

            if (!$withLivewireOnly) {
                // Create controllers
                $this->createWebController($name, $path, $namespace);

                if ($withApi) {
                    $this->createApiController($name, $path, $namespace);
                    $this->createApiRoutes($name, $path, $namespace);
                }

                // Create request
                $this->createRequest($name, $path, $namespace);

                // Create web routes
                $this->createWebRoutes($name, $path, $namespace);
            }

            // Create migration
            $this->createMigration($name, $path);
        }

        if ($withViews && !$withLivewireOnly) {
            $this->createViews($name, $path);
        }

        if ($withLivewire || $withLivewireOnly) {
            $this->createLivewireComponents($name, $path, $namespace, $withLivewireOnly);
            $this->createLivewireRoutes($name, $path, $namespace);
        }

        if ($withCrud && !$withLivewireOnly) {
            $this->createCrudOperations($name, $path, $namespace);
        }

        // Create resource-specific files
        if (!empty($resources)) {
            foreach ($resources as $resourceName) {
                // Create resource-specific files
                $this->createResourceController($name, $path, $namespace, $resourceName);

                if ($withApi) {
                    $this->createResourceApiController($name, $path, $namespace, $resourceName);
                }

                // Create resource model, repository and service
                $this->createResourceModel($name, $path, $namespace, $resourceName);
                $this->createResourceRepository($name, $path, $namespace, $resourceName);
                $this->createResourceService($name, $path, $namespace, $resourceName);

                // Create resource views if needed
                if ($withViews && !$withLivewireOnly) {
                    $this->createResourceViews($name, $path, $resourceName);
                }

                // Create resource Livewire components if needed
                if ($withLivewire || $withLivewireOnly) {
                    $this->createResourceLivewireComponents($name, $path, $namespace, $resourceName);
                }

                // Create resource CRUD operations if needed
                if ($withCrud && !$withLivewireOnly) {
                    $this->createResourceCrudOperations($name, $path, $namespace, $resourceName);
                }
            }
        }
    }

    /**
     * Create service provider for the module.
     */
    protected function createServiceProvider($name, $path, $namespace)
    {
        $content = $this->getStub('provider', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);

        $this->files->put($path . '/Providers/' . $name . 'ServiceProvider.php', $content);
    }

    /**
     * Create model for the module.
     */
    protected function createModel($name, $path, $namespace)
    {
        $content = $this->getStub('model', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
        ]);

        $this->files->put($path . '/Models/' . $name . '.php', $content);
    }

    /**
     * Create repository interface for the module.
     */
    protected function createRepositoryInterface($name, $path, $namespace)
    {
        $content = $this->getStub('repository-interface', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
        ]);

        $this->files->put($path . '/Repositories/Interfaces/' . $name . 'RepositoryInterface.php', $content);
    }

    /**
     * Create repository implementation for the module.
     */
    protected function createRepository($name, $path, $namespace)
    {
        $content = $this->getStub('repository', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
        ]);

        $this->files->put($path . '/Repositories/' . $name . 'Repository.php', $content);
    }

    /**
     * Create service interface for the module.
     */
    protected function createServiceInterface($name, $path, $namespace)
    {
        $content = $this->getStub('service-interface', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
        ]);

        $this->files->put($path . '/Services/Interfaces/' . $name . 'ServiceInterface.php', $content);
    }

    /**
     * Create service implementation for the module.
     */
    protected function createService($name, $path, $namespace)
    {
        $content = $this->getStub('service', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
        ]);

        $this->files->put($path . '/Services/' . $name . 'Service.php', $content);
    }

    /**
     * Create web controller for the module.
     */
    protected function createWebController($name, $path, $namespace)
    {
        $content = $this->getStub('web-controller', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);

        $this->files->put($path . '/Http/Controllers/' . $name . 'Controller.php', $content);
    }

    /**
     * Create API controller for the module.
     */
    protected function createApiController($name, $path, $namespace)
    {
        $content = $this->getStub('api-controller', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
        ]);

        $this->files->put($path . '/Http/Controllers/API/' . $name . 'Controller.php', $content);
    }

    /**
     * Create request class for the module.
     */
    protected function createRequest($name, $path, $namespace)
    {
        $content = $this->getStub('request', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
        ]);

        $this->files->put($path . '/Http/Requests/' . $name . 'Request.php', $content);
    }

    /**
     * Create web routes for the module.
     */
    protected function createWebRoutes($name, $path, $namespace)
    {
        $content = $this->getStub('web-routes', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);

        $this->files->put($path . '/Routes/web.php', $content);
    }

    /**
     * Create API routes for the module.
     */
    protected function createApiRoutes($name, $path, $namespace)
    {
        $content = $this->getStub('api-routes', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);

        $this->files->put($path . '/Routes/api.php', $content);
    }

    /**
     * Create migration for the module.
     */
    protected function createMigration($name, $path)
    {
        $tableName = Str::snake(Str::pluralStudly($name));
        $timestamp = now()->format('Y_m_d_His');
        $filename = $timestamp . '_create_' . $tableName . '_table.php';

        $content = $this->getStub('migration', [
            '{{table}}' => $tableName,
        ]);

        $this->files->put($path . '/Database/Migrations/' . $filename, $content);
    }

    /**
     * Create config file for the module.
     */
    protected function createConfig($name, $path)
    {
        $content = $this->getStub('config', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);

        $this->files->put($path . '/Config/config.php', $content);
    }

    /**
     * Create views for the module.
     */
    protected function createViews($name, $path)
    {
        $viewsPath = $path . '/Resources/views';
        $moduleViewsPath = $viewsPath . '/' . strtolower($name);

        // Create module views directory
        if (!$this->files->isDirectory($moduleViewsPath)) {
            $this->files->makeDirectory($moduleViewsPath, 0755, true);
        }

        // Create index view
        $content = $this->getStub('view-index', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($moduleViewsPath . '/index.blade.php', $content);

        // Create show view
        $content = $this->getStub('view-show', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($moduleViewsPath . '/show.blade.php', $content);

        // Create create view
        $content = $this->getStub('view-create', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($moduleViewsPath . '/create.blade.php', $content);

        // Create edit view
        $content = $this->getStub('view-edit', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($moduleViewsPath . '/edit.blade.php', $content);

        // Create module layout
        $content = $this->getStub('module-layout', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);

        // Create layouts directory if it doesn't exist
        $layoutsPath = $viewsPath . '/layouts';
        if (!$this->files->isDirectory($layoutsPath)) {
            $this->files->makeDirectory($layoutsPath, 0755, true);
        }

        $this->files->put($layoutsPath . '/module-layout.blade.php', $content);

        // Create navigation layout
        $content = $this->getStub('navigation', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($layoutsPath . '/navigation.blade.php', $content);
    }

    /**
     * Create Livewire components for the module.
     */
    protected function createLivewireComponents($name, $path, $namespace, $withLivewireOnly)
    {
        $livewirePath = $path . '/Livewire';
        $viewsPath = $path . '/Resources/views/livewire';

        // Create directories if they don't exist
        if (!$this->files->isDirectory($livewirePath)) {
            $this->files->makeDirectory($livewirePath, 0755, true);
        }

        if (!$this->files->isDirectory($viewsPath)) {
            $this->files->makeDirectory($viewsPath, 0755, true);
        }

        // Create Table component
        $content = $this->getStub('livewire-table', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($livewirePath . '/' . $name . 'Table.php', $content);

        // Create Table view
        $content = $this->getStub('livewire-table-view', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($viewsPath . '/' . Str::kebab($name) . '-table.blade.php', $content);

        // Create Form component
        $content = $this->getStub('livewire-form', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($livewirePath . '/' . $name . 'Form.php', $content);

        // Create Form view
        $content = $this->getStub('livewire-form-view', [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => strtolower($name),
        ]);
        $this->files->put($viewsPath . '/' . Str::kebab($name) . '-form.blade.php', $content);
    }

    /**
     * Create resource controller for the module.
     *
     * @param  string  $moduleName
     * @param  string  $path
     * @param  string  $namespace
     * @param  string  $resourceName
     * @return void
     */
    protected function createResourceController($moduleName, $path, $namespace, $resourceName)
    {
        $content = $this->getStub('web-controller', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => strtolower($resourceName),
        ]);

        $this->files->put($path . '/Http/Controllers/' . $resourceName . 'Controller.php', $content);

        // If API option is enabled, create API controller for the resource
        if ($this->option('api')) {
            $content = $this->getStub('api-controller', [
                '{{namespace}}' => $namespace,
                '{{moduleName}}' => $resourceName,
            ]);

            $this->files->put($path . '/Http/Controllers/API/' . $resourceName . 'Controller.php', $content);

            // Update API routes to include the resource
            $apiRoutesPath = $path . '/routes/api.php';
            if ($this->files->exists($apiRoutesPath)) {
                $routesContent = $this->files->get($apiRoutesPath);
                $resourceRoute = "\nRoute::apiResource('" . strtolower($resourceName) . "', API\\" . $resourceName . "Controller::class);";
                $this->files->put($apiRoutesPath, $routesContent . $resourceRoute);
            }
        }

        // If views option is enabled, create views for the resource
        if ($this->option('with-views')) {
            $this->createResourceViews($moduleName, $path, $resourceName);
        }
    }

    /**
     * Create resource repository and services for the module.
     *
     * @param  string  $moduleName
     * @param  string  $path
     * @param  string  $namespace
     * @param  string  $resourceName
     * @return void
     */
    protected function createResourceRepository($moduleName, $path, $namespace, $resourceName)
    {
        // Create model for the resource
        $content = $this->getStub('model', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
        ]);

        $this->files->put($path . '/Models/' . $resourceName . '.php', $content);

        // Create repository interface for the resource
        $content = $this->getStub('repository-interface', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
        ]);

        $this->files->put($path . '/Repositories/Interfaces/' . $resourceName . 'RepositoryInterface.php', $content);

        // Create repository implementation for the resource
        $content = $this->getStub('repository', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
        ]);

        $this->files->put($path . '/Repositories/' . $resourceName . 'Repository.php', $content);

        // Create service interface for the resource
        $content = $this->getStub('service-interface', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
        ]);

        $this->files->put($path . '/Services/Interfaces/' . $resourceName . 'ServiceInterface.php', $content);

        // Create service implementation for the resource
        $content = $this->getStub('service', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
        ]);

        $this->files->put($path . '/Services/' . $resourceName . 'Service.php', $content);

        // If Livewire option is enabled, create Livewire components for the resource
        if ($this->option('with-livewire') || $this->option('with-livewire-only')) {
            $this->createResourceLivewireComponents($moduleName, $path, $namespace, $resourceName);
        }
    }

    /**
     * Create resource views for the module.
     *
     * @param  string  $moduleName
     * @param  string  $path
     * @param  string  $resourceName
     * @return void
     */
    protected function createResourceViews($moduleName, $path, $resourceName)
    {
        $viewsPath = $path . '/Resources/views';
        $resourceViewsPath = $viewsPath . '/' . strtolower($resourceName);

        // Create module views directory
        if (!$this->files->isDirectory($resourceViewsPath)) {
            $this->files->makeDirectory($resourceViewsPath, 0755, true);
        }

        // Create index view
        $content = $this->getStub('view-index', [
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => strtolower($resourceName),
        ]);
        $this->files->put($resourceViewsPath . '/index.blade.php', $content);

        // Create show view
        $content = $this->getStub('view-show', [
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => strtolower($resourceName),
        ]);
        $this->files->put($resourceViewsPath . '/show.blade.php', $content);

        // Create create view
        $content = $this->getStub('view-create', [
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => strtolower($resourceName),
        ]);
        $this->files->put($resourceViewsPath . '/create.blade.php', $content);

        // Create edit view
        $content = $this->getStub('view-edit', [
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => strtolower($resourceName),
        ]);
        $this->files->put($resourceViewsPath . '/edit.blade.php', $content);
    }

    /**
     * Create resource Livewire components for the module.
     *
     * @param  string  $moduleName
     * @param  string  $path
     * @param  string  $namespace
     * @param  string  $resourceName
     * @return void
     */
    protected function createResourceLivewireComponents($moduleName, $path, $namespace, $resourceName)
    {
        $livewirePath = $path . '/Livewire';
        $viewsPath = $path . '/Resources/views/livewire';
        $resourceNameLower = strtolower($resourceName);

        // Create directories if they don't exist
        $this->files->makeDirectory($livewirePath, 0755, true);
        $this->files->makeDirectory($viewsPath, 0755, true);

        // Create Table component
        $content = $this->getStub('livewire-table', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => $resourceNameLower,
        ]);
        $this->files->put($livewirePath . '/' . $resourceName . 'Table.php', $content);

        // Create Table view
        $content = $this->getStub('livewire-table-view', [
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => $resourceNameLower,
        ]);
        $this->files->put($viewsPath . '/' . Str::kebab($resourceName) . '-table.blade.php', $content);

        // Create Form component
        $content = $this->getStub('livewire-form', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => $resourceNameLower,
        ]);
        $this->files->put($livewirePath . '/' . $resourceName . 'Form.php', $content);

        // Create Form view
        $content = $this->getStub('livewire-form-view', [
            '{{moduleName}}' => $resourceName,
            '{{moduleNameLower}}' => $resourceNameLower,
        ]);
        $this->files->put($viewsPath . '/' . Str::kebab($resourceName) . '-form.blade.php', $content);
    }

    /**
     * Create CRUD operations for the module.
     *
     * @param  string  $moduleName
     * @param  string  $path
     * @param  string  $namespace
     * @return void
     */
    protected function createCrudOperations($moduleName, $path, $namespace)
    {
        // CRUD operations are already included in the controller stubs
        // This method can be used to enhance CRUD functionality or add specific customizations

        // If resource is specified, ensure CRUD methods in resource controller
        if ($resourceName = $this->option('resource')) {
            $controllerPath = $path . '/Http/Controllers/' . $resourceName . 'Controller.php';
            if ($this->files->exists($controllerPath)) {
                // Already created with CRUD methods
            }
        }
    }

    /**
     * Create Livewire routes for the module.
     *
     * @param  string  $name
     * @param  string  $path
     * @param  string  $namespace
     * @return void
     */
    protected function createLivewireRoutes($name, $path, $namespace)
    {
        $moduleNameLower = strtolower($name);

        $content = <<<EOT
<?php

use Illuminate\Support\Facades\Route;
use {$namespace}\\{$name}\Livewire\\{$name}Table;
use {$namespace}\\{$name}\Livewire\\{$name}Form;

/*
|--------------------------------------------------------------------------
| Livewire Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Livewire routes for your module.
|
*/

Route::get('/{$moduleNameLower}', {$name}Table::class)
    ->name('{$moduleNameLower}.index');

Route::get('/{$moduleNameLower}/create', {$name}Form::class)
    ->name('{$moduleNameLower}.create');

Route::get('/{$moduleNameLower}/{id}/edit', {$name}Form::class)
    ->name('{$moduleNameLower}.edit');

Route::get('/{$moduleNameLower}/{id}', {$name}Form::class)
    ->name('{$moduleNameLower}.show');
EOT;

        $this->files->put($path . '/Routes/livewire.php', $content);
    }

    /**
     * Get stub content and replace placeholders.
     */
    protected function getStub($name, $replacements = [])
    {
        // Check for custom stub in application
        $customStubPath = base_path('stubs/vendor/modularization/' . $name . '.stub');

        if (file_exists($customStubPath)) {
            $content = file_get_contents($customStubPath);
        } else {
            // Fall back to package stubs
            $stubsDir = __DIR__ . '/../../../stubs/';
            $stubPath = $stubsDir . $name . '.stub';

            if (file_exists($stubPath)) {
                $content = file_get_contents($stubPath);
            } else {
                // If no stub file exists, use the inline stubs
                $stubs = $this->getStubContents();
                $content = $stubs[$name] ?? '<?php // Stub for ' . $name;
            }
        }

        // Replace all placeholders
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    /**
     * Contains stub contents for various files.
     */
    protected function getStubContents()
    {
        return [
            'provider' => '<?php

namespace {{namespace}}\\{{moduleName}}\Providers;

use Illuminate\Support\ServiceProvider;
use {{namespace}}\\{{moduleName}}\Repositories\Interfaces\\{{moduleName}}RepositoryInterface;
use {{namespace}}\\{{moduleName}}\Repositories\\{{moduleName}}Repository;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;
use {{namespace}}\\{{moduleName}}\Services\\{{moduleName}}Service;

class {{moduleName}}ServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register repositories
        $this->app->bind(
            {{moduleName}}RepositoryInterface::class,
            {{moduleName}}Repository::class
        );

        // Register services
        $this->app->bind(
            {{moduleName}}ServiceInterface::class,
            {{moduleName}}Service::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 
    }
}',
            'model' => '<?php

namespace {{namespace}}\\{{moduleName}}\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {{moduleName}} extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Add your fields here
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        //
    ];
}',
            'repository-interface' => '<?php

namespace {{namespace}}\\{{moduleName}}\Repositories\Interfaces;

interface {{moduleName}}RepositoryInterface
{
    /**
     * Get all records
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll();

    /**
     * Find record by ID
     * 
     * @param int $id
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}|null
     */
    public function findById($id);

    /**
     * Create a new record
     * 
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function create(array $data);

    /**
     * Update existing record
     * 
     * @param int $id
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function update($id, array $data);

    /**
     * Delete record
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id);
}',
            'repository' => '<?php

namespace {{namespace}}\\{{moduleName}}\Repositories;

use {{namespace}}\\{{moduleName}}\Models\\{{moduleName}};
use {{namespace}}\\{{moduleName}}\Repositories\Interfaces\\{{moduleName}}RepositoryInterface;

class {{moduleName}}Repository implements {{moduleName}}RepositoryInterface
{
    /**
     * @var {{moduleName}}
     */
    protected $model;

    /**
     * Constructor
     */
    public function __construct({{moduleName}} $model)
    {
        $this->model = $model;
    }

    /**
     * Get all records
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        return $this->model->all();
    }

    /**
     * Find record by ID
     * 
     * @param int $id
     * @return {{moduleName}}|null
     */
    public function findById($id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Create a new record
     * 
     * @param array $data
     * @return {{moduleName}}
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update existing record
     * 
     * @param int $id
     * @param array $data
     * @return {{moduleName}}
     */
    public function update($id, array $data)
    {
        $record = $this->findById($id);
        $record->update($data);
        return $record;
    }

    /**
     * Delete record
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        return $this->findById($id)->delete();
    }
}',
            'service-interface' => '<?php

namespace {{namespace}}\\{{moduleName}}\Services\Interfaces;

interface {{moduleName}}ServiceInterface
{
    /**
     * Get all records
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll();

    /**
     * Find record by ID
     * 
     * @param int $id
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}|null
     */
    public function findById($id);

    /**
     * Create a new record
     * 
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function create(array $data);

    /**
     * Update existing record
     * 
     * @param int $id
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function update($id, array $data);

    /**
     * Delete record
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id);
}',
            'service' => '<?php

namespace {{namespace}}\\{{moduleName}}\Services;

use {{namespace}}\\{{moduleName}}\Repositories\Interfaces\\{{moduleName}}RepositoryInterface;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;

class {{moduleName}}Service implements {{moduleName}}ServiceInterface
{
    /**
     * @var {{moduleName}}RepositoryInterface
     */
    protected $repository;

    /**
     * Constructor
     */
    public function __construct({{moduleName}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all records
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        return $this->repository->getAll();
    }

    /**
     * Find record by ID
     * 
     * @param int $id
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}|null
     */
    public function findById($id)
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new record
     * 
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function create(array $data)
    {
        // Add any business logic before creation
        return $this->repository->create($data);
    }

    /**
     * Update existing record
     * 
     * @param int $id
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function update($id, array $data)
    {
        // Add any business logic before update
        return $this->repository->update($id, $data);
    }

    /**
     * Delete record
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        // Add any business logic before deletion
        return $this->repository->delete($id);
    }
}',
            'web-controller' => '<?php

namespace {{namespace}}\\{{moduleName}}\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;
use {{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request;

class {{moduleName}}Controller extends Controller
{
    /**
     * @var {{moduleName}}ServiceInterface
     */
    protected $service;

    /**
     * Constructor
     */
    public function __construct({{moduleName}}ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        ${{moduleNameLower}}s = $this->service->getAll();
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.index\', compact(\'{{moduleNameLower}}s\'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.create\');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store({{moduleName}}Request $request)
    {
        $this->service->create($request->validated());
        return redirect()->route(\'{{moduleNameLower}}.index\')->with(\'success\', \'{{moduleName}} created successfully\');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        ${{moduleNameLower}} = $this->service->findById($id);
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.show\', compact(\'{{moduleNameLower}}\'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        ${{moduleNameLower}} = $this->service->findById($id);
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.edit\', compact(\'{{moduleNameLower}}\'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update({{moduleName}}Request $request, string $id)
    {
        $this->service->update($id, $request->validated());
        return redirect()->route(\'{{moduleNameLower}}.index\')->with(\'success\', \'{{moduleName}} updated successfully\');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->service->delete($id);
        return redirect()->route(\'{{moduleNameLower}}.index\')->with(\'success\', \'{{moduleName}} deleted successfully\');
    }
}',
            'api-controller' => '<?php

namespace {{namespace}}\\{{moduleName}}\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;
use {{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request;

class {{moduleName}}Controller extends Controller
{
    /**
     * @var {{moduleName}}ServiceInterface
     */
    protected $service;

    /**
     * Constructor
     */
    public function __construct({{moduleName}}ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $data = $this->service->getAll();
        return response()->json([\'data\' => $data]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \{{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store({{moduleName}}Request $request)
    {
        $data = $this->service->create($request->validated());
        return response()->json([\'data\' => $data, \'message\' => \'{{moduleName}} created successfully\'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $data = $this->service->findById($id);
        return response()->json([\'data\' => $data]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \{{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update({{moduleName}}Request $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return response()->json([\'data\' => $data, \'message\' => \'{{moduleName}} updated successfully\']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json([\'message\' => \'{{moduleName}} deleted successfully\']);
    }
}',
            'request' => '<?php

namespace {{namespace}}\\{{moduleName}}\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class {{moduleName}}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Define validation rules for your {{moduleName}} here
        ];
    }
}',
            'web-routes' => '<?php

use Illuminate\Support\Facades\Route;
use {{namespace}}\\{{moduleName}}\Http\Controllers\\{{moduleName}}Controller;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your module.
|
*/

Route::resource(\'{{moduleNameLower}}\', {{moduleName}}Controller::class);',
            'api-routes' => '<?php

use Illuminate\Support\Facades\Route;
use {{namespace}}\\{{moduleName}}\Http\Controllers\API\\{{moduleName}}Controller;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your module.
|
*/

Route::apiResource(\'{{moduleNameLower}}\', {{moduleName}}Controller::class);',
            'config' => '<?php

return [
    /*
    |--------------------------------------------------------------------------
    | {{moduleName}} Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to the {{moduleName}} module.
    |
    */
    \'name\' => \'{{moduleName}}\',
    \'enabled\' => true,
];',
            'migration' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(\'{{table}}\', function (Blueprint $table) {
            $table->id();
            // Add your fields here
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(\'{{table}}\');
    }
};',
            'view-index' => '@extends(\'layouts.app\')

@section(\'content\')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __("{{moduleName}} List") }}</span>
                    <a href="{{ route(\'{{moduleNameLower}}.create\') }}" class="btn btn-primary btn-sm">Create New</a>
                </div>

                <div class="card-body">
                    @if (session(\'success\'))
                        <div class="alert alert-success" role="alert">
                            {{ session(\'success\') }}
                        </div>
                    @endif

                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(${{moduleNameLower}}s as ${{moduleNameLower}})
                                <tr>
                                    <td>{{ ${{moduleNameLower}}->id }}</td>
                                    <td>{{ ${{moduleNameLower}}->name }}</td>
                                    <td>{{ ${{moduleNameLower}}->created_at->format(\'Y-m-d\') }}</td>
                                    <td>
                                        <a href="{{ route(\'{{moduleNameLower}}.show\', ${{moduleNameLower}}->id) }}" class="btn btn-info btn-sm">View</a>
                                        <a href="{{ route(\'{{moduleNameLower}}.edit\', ${{moduleNameLower}}->id) }}" class="btn btn-primary btn-sm">Edit</a>
                                        <form action="{{ route(\'{{moduleNameLower}}.destroy\', ${{moduleNameLower}}->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method(\'DELETE\')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this item?\')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">No records found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection',
            'view-show' => '@extends(\'layouts.app\')

@section(\'content\')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __("{{moduleName}} Details") }}</span>
                    <a href="{{ route(\'{{moduleNameLower}}.index\') }}" class="btn btn-secondary btn-sm">Back to List</a>
                </div>

                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th>ID</th>
                            <td>{{ ${{moduleNameLower}}->id }}</td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td>{{ ${{moduleNameLower}}->name }}</td>
                        </tr>
                        <!-- Add more fields here -->
                        <tr>
                            <th>Created At</th>
                            <td>{{ ${{moduleNameLower}}->created_at->format(\'Y-m-d H:i:s\') }}</td>
                        </tr>
                        <tr>
                            <th>Updated At</th>
                            <td>{{ ${{moduleNameLower}}->updated_at->format(\'Y-m-d H:i:s\') }}</td>
                        </tr>
                    </table>

                    <div class="mt-3 d-flex">
                        <a href="{{ route(\'{{moduleNameLower}}.edit\', ${{moduleNameLower}}->id) }}" class="btn btn-primary mr-2">Edit</a>
                        <form action="{{ route(\'{{moduleNameLower}}.destroy\', ${{moduleNameLower}}->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method(\'DELETE\')
                            <button type="submit" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to delete this item?\')">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection',
            'view-create' => '@extends(\'layouts.app\')

@section(\'content\')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __(\'Create {{moduleName}}\') }}</span>
                    <a href="{{ route(\'{{moduleNameLower}}.index\') }}" class="btn btn-secondary btn-sm">Back to List</a>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route(\'{{moduleNameLower}}.store\') }}">
                        @csrf

                        <div class="form-group row mb-3">
                            <label for="name" class="col-md-4 col-form-label text-md-right">{{ __(\'Name\') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error(\'name\') is-invalid @enderror" name="name" value="{{ old(\'name\') }}" required autocomplete="name" autofocus>

                                @error(\'name\')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Add more form fields here -->

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __(\'Create {{moduleName}}\') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection',
            'view-edit' => '@extends(\'layouts.app\')

@section(\'content\')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __(\'Edit {{moduleName}}\') }}</span>
                    <a href="{{ route(\'{{moduleNameLower}}.index\') }}" class="btn btn-secondary btn-sm">Back to List</a>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route(\'{{moduleNameLower}}.update\', ${{moduleNameLower}}->id) }}">
                        @csrf
                        @method(\'PUT\')

                        <div class="form-group row mb-3">
                            <label for="name" class="col-md-4 col-form-label text-md-right">{{ __(\'Name\') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error(\'name\') is-invalid @enderror" name="name" value="{{ old(\'name\', ${{moduleNameLower}}->name) }}" required autocomplete="name" autofocus>

                                @error(\'name\')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Add more form fields here -->

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __(\'Update {{moduleName}}\') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection',
            'livewire-table' => '<?php

namespace {{namespace}}\\{{moduleName}}\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;

class {{moduleName}}Table extends Component
{
    use WithPagination;
    
    public $search = \'\';
    public $perPage = 10;
    public $sortField = \'created_at\';
    public $sortDirection = \'desc\';
    
    protected $listeners = [
        \'refreshTable\' => \'$refresh\',
        \'{{moduleNameLower}}Created\' => \'$refresh\',
        \'{{moduleNameLower}}Updated\' => \'$refresh\',
        \'{{moduleNameLower}}Deleted\' => \'$refresh\',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === \'asc\' ? \'desc\' : \'asc\';
        } else {
            $this->sortDirection = \'asc\';
        }
        
        $this->sortField = $field;
    }

    public function delete($id)
    {
        if (!$id) {
            return;
        }

        app({{moduleName}}ServiceInterface::class)->delete($id);
        
        $this->dispatch(\'{{moduleNameLower}}Deleted\');
        session()->flash(\'message\', \'{{moduleName}} deleted successfully\');
    }

    public function render()
    {
        $service = app({{moduleName}}ServiceInterface::class);
        ${{moduleNameLower}}s = $service->getAll();
        
        return view(\'{{moduleNameLower}}.livewire.{{moduleNameLower}}-table\', [
            \'{{moduleNameLower}}s\' => ${{moduleNameLower}}s,
        ]);
    }
}',
            'livewire-form' => '<?php

namespace {{namespace}}\\{{moduleName}}\Livewire;

use Livewire\Component;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;

class {{moduleName}}Form extends Component
{
    public ${{moduleNameLower}};
    public ${{moduleNameLower}}Id;
    public $isEditing = false;
    
    // Define form properties here
    public $name;
    
    protected $rules = [
        \'name\' => \'required|min:3\',
        // Add additional validation rules
    ];
    
    public function mount(${{moduleNameLower}}Id = null)
    {
        if (${{moduleNameLower}}Id) {
            $this->{{moduleNameLower}}Id = ${{moduleNameLower}}Id;
            $this->isEditing = true;
            $this->loadModel();
        }
    }
    
    public function loadModel()
    {
        $service = app({{moduleName}}ServiceInterface::class);
        $this->{{moduleNameLower}} = $service->findById($this->{{moduleNameLower}}Id);
        
        // Fill form data from model
        $this->name = $this->{{moduleNameLower}}->name;
        // Fill other form properties
    }
    
    public function save()
    {
        $this->validate();
        
        $service = app({{moduleName}}ServiceInterface::class);
        $data = [
            \'name\' => $this->name,
            // Add other form fields
        ];
        
        if ($this->isEditing) {
            $service->update($this->{{moduleNameLower}}Id, $data);
            $this->dispatch(\'{{moduleNameLower}}Updated\');
            session()->flash(\'message\', \'{{moduleName}} updated successfully\');
        } else {
            $service->create($data);
            $this->dispatch(\'{{moduleNameLower}}Created\');
            session()->flash(\'message\', \'{{moduleName}} created successfully\');
            $this->reset([\'name\']); // Reset form fields
        }
    }
    
    public function render()
    {
        return view(\'{{moduleNameLower}}.livewire.{{moduleNameLower}}-form\');
    }
}',
            'livewire-table-view' => '<div>
    <div class="mb-4 flex justify-between items-center">
        <div class="flex items-center">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search..." class="px-4 py-2 border rounded-lg" />
            <select wire:model.live="perPage" class="ml-4 px-4 py-2 border rounded-lg">
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
        <a href="{{ route(\'{{moduleNameLower}}.create\') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Add New {{moduleName}}
        </a>
    </div>

    @if (session()->has(\'message\'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            {{ session(\'message\') }}
        </div>
    @endif

    <div class="overflow-x-auto bg-white rounded-lg shadow overflow-y-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th wire:click="sortBy(\'id\')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                        ID
                        @if ($sortField === \'id\')
                            <span>{!! $sortDirection === \'asc\' ? \'&#8593;\' : \'&#8595;\' !!}</span>
                        @endif
                    </th>
                    <th wire:click="sortBy(\'name\')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                        Name
                        @if ($sortField === \'name\')
                            <span>{!! $sortDirection === \'asc\' ? \'&#8593;\' : \'&#8595;\' !!}</span>
                        @endif
                    </th>
                    <th wire:click="sortBy(\'created_at\')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                        Created At
                        @if ($sortField === \'created_at\')
                            <span>{!! $sortDirection === \'asc\' ? \'&#8593;\' : \'&#8595;\' !!}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse(${{moduleNameLower}}s as ${{moduleNameLower}})
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">{{ ${{moduleNameLower}}->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ ${{moduleNameLower}}->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ ${{moduleNameLower}}->created_at->format(\'Y-m-d H:i\') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route(\'{{moduleNameLower}}.show\', ${{moduleNameLower}}->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                            <a href="{{ route(\'{{moduleNameLower}}.edit\', ${{moduleNameLower}}->id) }}" class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                            <button wire:click="delete({{ ${{moduleNameLower}}->id }})" wire:confirm="Are you sure you want to delete this item?" class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-center">No {{moduleNameLower}}s found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ ${{moduleNameLower}}s->links() }}
    </div>
</div>',
            'livewire-form-view' => '<div>
    <form wire:submit="save" class="space-y-6">
        @if (session()->has(\'message\'))
            <div class="bg-green-100 text-green-700 p-4 rounded mb-6">
                {{ session(\'message\') }}
            </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
            <div class="mt-1">
                <input type="text" wire:model="name" id="name" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            @error(\'name\') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <!-- Add other fields as needed -->

        <div class="flex justify-end">
            <a href="{{ route(\'{{moduleNameLower}}.index\') }}" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-2">
                Cancel
            </a>
            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ $isEditing ? \'Update\' : \'Create\' }}
            </button>
        </div>
    </form>
</div>',
            'module-layout' => '<div>
    <div class="mb-4 flex justify-between items-center">
        <div class="flex items-center">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search..." class="px-4 py-2 border rounded-lg" />
            <select wire:model.live="perPage" class="ml-4 px-4 py-2 border rounded-lg">
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
        <a href="{{ route(\'{{moduleNameLower}}.create\') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Add New {{moduleName}}
        </a>
    </div>

    @if (session()->has(\'message\'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            {{ session(\'message\') }}
        </div>
    @endif

    <div class="overflow-x-auto bg-white rounded-lg shadow overflow-y-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th wire:click="sortBy(\'id\')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                        ID
                        @if ($sortField === \'id\')
                            <span>{!! $sortDirection === \'asc\' ? \'&#8593;\' : \'&#8595;\' !!}</span>
                        @endif
                    </th>
                    <th wire:click="sortBy(\'name\')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                        Name
                        @if ($sortField === \'name\')
                            <span>{!! $sortDirection === \'asc\' ? \'&#8593;\' : \'&#8595;\' !!}</span>
                        @endif
                    </th>
                    <th wire:click="sortBy(\'created_at\')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                        Created At
                        @if ($sortField === \'created_at\')
                            <span>{!! $sortDirection === \'asc\' ? \'&#8593;\' : \'&#8595;\' !!}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse(${{moduleNameLower}}s as ${{moduleNameLower}})
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">{{ ${{moduleNameLower}}->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ ${{moduleNameLower}}->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ ${{moduleNameLower}}->created_at->format(\'Y-m-d H:i\') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route(\'{{moduleNameLower}}.show\', ${{moduleNameLower}}->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                            <a href="{{ route(\'{{moduleNameLower}}.edit\', ${{moduleNameLower}}->id) }}" class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                            <button wire:click="delete({{ ${{moduleNameLower}}->id }})" wire:confirm="Are you sure you want to delete this item?" class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-center">No {{moduleNameLower}}s found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ ${{moduleNameLower}}s->links() }}
    </div>
</div>',
            'resource-api-controller' => '<?php

namespace {{namespace}}\\{{moduleName}}\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;
use {{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request;

class {{moduleName}}Controller extends Controller
{
    /**
     * @var {{moduleName}}ServiceInterface
     */
    protected $service;

    /**
     * Constructor
     */
    public function __construct({{moduleName}}ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $data = $this->service->getAll();
        return response()->json([\'data\' => $data]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \{{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store({{moduleName}}Request $request)
    {
        $data = $this->service->create($request->validated());
        return response()->json([\'data\' => $data, \'message\' => \'{{moduleName}} created successfully\'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $data = $this->service->findById($id);
        return response()->json([\'data\' => $data]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \{{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update({{moduleName}}Request $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return response()->json([\'data\' => $data, \'message\' => \'{{moduleName}} updated successfully\']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json([\'message\' => \'{{moduleName}} deleted successfully\']);
    }
}',
            'resource-model' => '<?php

namespace {{namespace}}\\{{moduleName}}\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {{moduleName}} extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Add your fields here
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        //
    ];
}',
            'resource-service' => '<?php

namespace {{namespace}}\\{{moduleName}}\Services;

use {{namespace}}\\{{moduleName}}\Repositories\Interfaces\\{{moduleName}}RepositoryInterface;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;

class {{moduleName}}Service implements {{moduleName}}ServiceInterface
{
    /**
     * @var {{moduleName}}RepositoryInterface
     */
    protected $repository;

    /**
     * Constructor
     */
    public function __construct({{moduleName}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all records
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        return $this->repository->getAll();
    }

    /**
     * Find record by ID
     * 
     * @param int $id
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}|null
     */
    public function findById($id)
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new record
     * 
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function create(array $data)
    {
        // Add any business logic before creation
        return $this->repository->create($data);
    }

    /**
     * Update existing record
     * 
     * @param int $id
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function update($id, array $data)
    {
        // Add any business logic before update
        return $this->repository->update($id, $data);
    }

    /**
     * Delete record
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        // Add any business logic before deletion
        return $this->repository->delete($id);
    }
}',
            'resource-crud-controller' => '<?php

namespace {{namespace}}\\{{moduleName}}\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;
use {{namespace}}\\{{moduleName}}\Http\Requests\\{{moduleName}}Request;

class {{moduleName}}Controller extends Controller
{
    /**
     * @var {{moduleName}}ServiceInterface
     */
    protected $service;

    /**
     * Constructor
     */
    public function __construct({{moduleName}}ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        ${{moduleNameLower}}s = $this->service->getAll();
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.index\', compact(\'{{moduleNameLower}}s\'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.create\');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store({{moduleName}}Request $request)
    {
        $this->service->create($request->validated());
        return redirect()->route(\'{{moduleNameLower}}.index\')->with(\'success\', \'{{moduleName}} created successfully\');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        ${{moduleNameLower}} = $this->service->findById($id);
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.show\', compact(\'{{moduleNameLower}}\'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        ${{moduleNameLower}} = $this->service->findById($id);
        return view(\'{{moduleNameLower}}::{{moduleNameLower}}.edit\', compact(\'{{moduleNameLower}}\'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update({{moduleName}}Request $request, string $id)
    {
        $this->service->update($id, $request->validated());
        return redirect()->route(\'{{moduleNameLower}}.index\')->with(\'success\', \'{{moduleName}} updated successfully\');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->service->delete($id);
        return redirect()->route(\'{{moduleNameLower}}.index\')->with(\'success\', \'{{moduleName}} deleted successfully\');
    }
}',
            'resource-crud-service' => '<?php

namespace {{namespace}}\\{{moduleName}}\Services;

use {{namespace}}\\{{moduleName}}\Repositories\Interfaces\\{{moduleName}}RepositoryInterface;
use {{namespace}}\\{{moduleName}}\Services\Interfaces\\{{moduleName}}ServiceInterface;

class {{moduleName}}Service implements {{moduleName}}ServiceInterface
{
    /**
     * @var {{moduleName}}RepositoryInterface
     */
    protected $repository;

    /**
     * Constructor
     */
    public function __construct({{moduleName}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all records
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        return $this->repository->getAll();
    }

    /**
     * Find record by ID
     * 
     * @param int $id
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}|null
     */
    public function findById($id)
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new record
     * 
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function create(array $data)
    {
        // Add any business logic before creation
        return $this->repository->create($data);
    }

    /**
     * Update existing record
     * 
     * @param int $id
     * @param array $data
     * @return {{namespace}}\\{{moduleName}}\Models\\{{moduleName}}
     */
    public function update($id, array $data)
    {
        // Add any business logic before update
        return $this->repository->update($id, $data);
    }

    /**
     * Delete record
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        // Add any business logic before deletion
        return $this->repository->delete($id);
    }
}',
            'navigation' => '<div>
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                        <!-- Add your logo here -->
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <!-- Add your navigation links here -->
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6">
                        <!-- Add your user profile and logout link here -->
                    </div>
                </div>
                <div class="-mr-2 flex md:hidden">
                    <!-- Add your mobile menu button here -->
                </div>
            </div>
        </div>
    </nav>
</div>'
        ];
    }

    /**
     * Output next steps to the user.
     *
     * @param string $name
     * @return void
     */
    protected function outputNextSteps($name)
    {
        $moduleNameLower = strtolower($name);

        $this->newLine();
        $this->info('Module created successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->newLine();
        $this->line("1. Run composer dump-autoload to update autoloader:");
        $this->line("   composer dump-autoload");
        $this->newLine();
        $this->line("2. Add the module to your service providers in 'config/app.php':");
        $this->line("   App\Modules\\{$name}\Providers\\{$name}ServiceProvider::class,");
        $this->newLine();
        $this->line("3. Access your module at:");
        $this->line("   " . url($moduleNameLower));
        $this->newLine();
        $this->line("4. Run migrations if needed:");
        // $this->line("   php artisan migrate");
        $this->newLine();
    }

    /**
     * Update the modules configuration.
     *
     * @param string $name
     * @return void
     */
    protected function updateModulesConfig($name)
    {
        $configPath = config_path('modules.php');

        // Create config if it doesn't exist
        if (!$this->files->exists($configPath)) {
            $stub = $this->modulesConfigStub([$name]);
            $this->files->put($configPath, $stub);
            $this->info('Modules config file created at: ' . $configPath);
            return;
        }

        // Read existing config
        $config = include $configPath;

        // Check if module already exists in config
        if (isset($config['modules']) && in_array($name, $config['modules'])) {
            return;
        }

        // Add module to config
        $modules = isset($config['modules']) ? $config['modules'] : [];
        $modules[] = $name;

        // Generate updated config
        $stub = $this->modulesConfigStub($modules);
        $this->files->put($configPath, $stub);

        $this->info('Module added to config file: ' . $configPath);
    }

    /**
     * Generate modules config file stub.
     *
     * @param array $modules
     * @return string
     */
    protected function modulesConfigStub($modules)
    {
        $modulesString = '';
        foreach ($modules as $module) {
            $modulesString .= "        '{$module}',\n";
        }

        return <<<EOT
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your module settings. Below is an example of
    | settings that the modules will use.
    |
    */
    
    'namespace' => 'Modules',
    
    'modules_path' => 'modules',
    
    'modules' => [
{$modulesString}    ],
    
    'auto_discover' => true,
];

EOT;
    }

    /**
     * Create resource API controller.
     */
    protected function createResourceApiController($moduleName, $path, $namespace, $resourceName)
    {
        $controllerPath = $path . '/Http/Controllers/API/' . $resourceName . 'Controller.php';

        $content = $this->getStub('resource-api-controller', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $moduleName,
            '{{resourceName}}' => $resourceName,
            '{{resourceNameLower}}' => strtolower($resourceName),
        ]);

        $this->files->put($controllerPath, $content);
    }

    /**
     * Create resource model.
     */
    protected function createResourceModel($moduleName, $path, $namespace, $resourceName)
    {
        $modelPath = $path . '/Models/' . $resourceName . '.php';

        $content = $this->getStub('resource-model', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $moduleName,
            '{{resourceName}}' => $resourceName,
        ]);

        $this->files->put($modelPath, $content);
    }

    /**
     * Create resource service.
     */
    protected function createResourceService($moduleName, $path, $namespace, $resourceName)
    {
        // Create service interface
        $interfacePath = $path . '/Services/Interfaces/' . $resourceName . 'ServiceInterface.php';

        $interfaceContent = $this->getStub('service-interface', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $moduleName,
            '{{className}}' => $resourceName,
        ]);

        $this->files->put($interfacePath, $interfaceContent);

        // Create service implementation
        $servicePath = $path . '/Services/' . $resourceName . 'Service.php';

        $serviceContent = $this->getStub('service', [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $moduleName,
            '{{className}}' => $resourceName,
        ]);

        $this->files->put($servicePath, $serviceContent);
    }

    /**
     * Create resource CRUD operations.
     */
    protected function createResourceCrudOperations($moduleName, $path, $namespace, $resourceName)
    {
        // Update resource controller with CRUD methods
        $controllerPath = $path . '/Http/Controllers/' . $resourceName . 'Controller.php';

        if ($this->files->exists($controllerPath)) {
            $content = $this->getStub('crud-controller', [
                '{{namespace}}' => $namespace,
                '{{moduleName}}' => $moduleName,
                '{{className}}' => $resourceName,
                '{{classNameLower}}' => strtolower($resourceName),
            ]);

            $this->files->put($controllerPath, $content);
        }

        // Update resource service with CRUD methods
        $servicePath = $path . '/Services/' . $resourceName . 'Service.php';

        if ($this->files->exists($servicePath)) {
            $content = $this->getStub('crud-service', [
                '{{namespace}}' => $namespace,
                '{{moduleName}}' => $moduleName,
                '{{className}}' => $resourceName,
            ]);

            $this->files->put($servicePath, $content);
        }

        // Update resource repository with CRUD methods
        $repoPath = $path . '/Repositories/' . $resourceName . 'Repository.php';

        if ($this->files->exists($repoPath)) {
            $content = $this->getStub('crud-repository', [
                '{{namespace}}' => $namespace,
                '{{moduleName}}' => $moduleName,
                '{{className}}' => $resourceName,
            ]);

            $this->files->put($repoPath, $content);
        }
    }

    /**
     * Ensure the Modules namespace is added to composer.json
     * 
     * @param string $namespace The namespace to add, typically 'Modules'
     * @param string $path The base path for modules, typically 'modules'
     * @return void
     */
    protected function ensureModulesNamespaceInComposer($namespace, $path)
    {
        $composerPath = base_path('composer.json');

        if (!$this->files->exists($composerPath)) {
            $this->warn('composer.json not found. Cannot add namespace automatically.');
            return;
        }

        $composerJson = json_decode($this->files->get($composerPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn('Unable to parse composer.json. Cannot add namespace automatically.');
            return;
        }

        // Check if the namespace already exists in the PSR-4 autoload
        $modulePathName = basename($path);
        $namespaceExists = false;

        if (isset($composerJson['autoload']['psr-4'])) {
            foreach ($composerJson['autoload']['psr-4'] as $existingNamespace => $existingPath) {
                if (trim($existingNamespace, '\\') === $namespace) {
                    $namespaceExists = true;
                    break;
                }
            }
        } else {
            // Create autoload section if it doesn't exist
            if (!isset($composerJson['autoload'])) {
                $composerJson['autoload'] = [];
            }

            $composerJson['autoload']['psr-4'] = [];
        }

        // Add the namespace if it doesn't exist
        if (!$namespaceExists) {
            $composerJson['autoload']['psr-4'][$namespace . '\\'] = $modulePathName . '/';

            // Write back to composer.json with proper formatting
            $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
            $this->files->put($composerPath, json_encode($composerJson, $jsonOptions));

            $this->info("Added '{$namespace}\\' namespace to composer.json");
            $this->line('Remember to run "composer dump-autoload" after creating modules.');
        }
    }
}
