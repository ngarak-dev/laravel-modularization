<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleLivewireCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-livewire
                            {module : The name of the module}
                            {name : The name of the Livewire component}
                            {--force : Overwrite existing files}
                            {--subdirectory= : Optional subdirectory within the Livewire directory}
                            {--view-only : Create only the view file without the component class}
                            {--class-only : Create only the component class without the view file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Livewire component in a module';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $namespace = config('modularization.namespace', 'Modules');
        $moduleBasePath = base_path(config('modularization.modules_path', 'modules'));

        $module = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $subdirectory = $this->option('subdirectory') ? Str::studly($this->option('subdirectory')) : null;
        $viewOnly = $this->option('view-only');
        $classOnly = $this->option('class-only');
        $force = $this->option('force');

        // Check if module exists
        $modulePath = "{$moduleBasePath}/{$module}";
        if (!File::isDirectory($modulePath)) {
            $this->error("Module '{$module}' does not exist.");
            return 1;
        }

        // Create directories if they don't exist
        $livewireDir = "{$modulePath}/Livewire";
        if (!File::isDirectory($livewireDir)) {
            File::makeDirectory($livewireDir, 0755, true);
        }

        // If subdirectory is specified, create it
        $componentDir = $livewireDir;
        $componentNamespace = "{$namespace}\\{$module}\\Livewire";
        $viewPrefix = strtolower($module);

        if ($subdirectory) {
            $componentDir = "{$livewireDir}/{$subdirectory}";
            $componentNamespace = "{$componentNamespace}\\{$subdirectory}";
            $viewPrefix = "{$viewPrefix}.livewire.{$this->kebabCase($subdirectory)}";

            if (!File::isDirectory($componentDir)) {
                File::makeDirectory($componentDir, 0755, true);
            }
        } else {
            $viewPrefix = "{$viewPrefix}.livewire";
        }

        // Create view directory if it doesn't exist
        $viewsDir = "{$modulePath}/resources/views/livewire";
        if (!File::isDirectory($viewsDir)) {
            File::makeDirectory($viewsDir, 0755, true);
        }

        if ($subdirectory) {
            $viewsDir = "{$viewsDir}/{$this->kebabCase($subdirectory)}";
            if (!File::isDirectory($viewsDir)) {
                File::makeDirectory($viewsDir, 0755, true);
            }
        }

        // Create component class unless view-only flag is set
        if (!$viewOnly) {
            $this->createComponentClass($componentDir, $name, $componentNamespace, $viewPrefix, $force);
        }

        // Create component view unless class-only flag is set
        if (!$classOnly) {
            $this->createComponentView($viewsDir, $name, $force);
        }

        $this->info("Livewire component {$name} created successfully for module {$module}.");
        return 0;
    }

    /**
     * Create the Livewire component class.
     */
    protected function createComponentClass($componentDir, $name, $namespace, $viewName, $force)
    {
        $componentPath = "{$componentDir}/{$name}.php";

        if (File::exists($componentPath) && !$force) {
            if (!$this->confirm("The Livewire component class already exists. Do you want to overwrite it?")) {
                $this->info("Component class creation skipped.");
                return;
            }
        }

        $stub = $this->getComponentStub($name, $namespace, $viewName);

        File::put($componentPath, $stub);
        $this->info("Livewire component class created: {$componentPath}");
    }

    /**
     * Create the Livewire component view.
     */
    protected function createComponentView($viewsDir, $name, $force)
    {
        $viewPath = "{$viewsDir}/{$this->kebabCase($name)}.blade.php";

        if (File::exists($viewPath) && !$force) {
            if (!$this->confirm("The Livewire component view already exists. Do you want to overwrite it?")) {
                $this->info("Component view creation skipped.");
                return;
            }
        }

        $stub = $this->getViewStub($name);

        File::put($viewPath, $stub);
        $this->info("Livewire component view created: {$viewPath}");
    }

    /**
     * Get the component class stub.
     */
    protected function getComponentStub($name, $namespace, $viewName)
    {
        return <<<EOT
<?php

namespace {$namespace};

use Livewire\Component;

class {$name} extends Component
{
    public function render()
    {
        return view('{$viewName}');
    }
}
EOT;
    }

    /**
     * Get the component view stub.
     */
    protected function getViewStub($name)
    {
        return <<<EOT
<div>
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('$name') }}
    </h2>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div>
                        <!-- $name Component Content -->
                        @if (session()->has('message'))
                            <div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                                {{ session('message') }}
                            </div>
                        @endif

                        <!-- Main content here -->
                        <div class="mt-4">
                            <!-- Replace with your component specific content -->
                            <h3 class="text-lg font-medium text-gray-900">$name Component</h3>
                            <p class="mt-1 text-sm text-gray-600">This is a new Livewire component.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
EOT;
    }

    /**
     * Convert a string to kebab case.
     */
    protected function kebabCase($string)
    {
        return strtolower(preg_replace(
            ['/([a-z\d])([A-Z])/', '/([^-])([A-Z][a-z])/'],
            ['$1-$2', '$1-$2'],
            $string
        ));
    }
}
