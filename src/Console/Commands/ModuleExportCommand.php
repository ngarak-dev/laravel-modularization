<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:export 
                            {module : The name of the module to export}
                            {--output= : The output directory (default: build)}
                            {--vendor= : The vendor name for the package}
                            {--description= : Package description}
                            {--author= : Package author}
                            {--email= : Author email}
                            {--license=MIT : Package license}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export a module as a standalone package';

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
     * @return int
     */
    public function handle()
    {
        $moduleName = $this->argument('module');
        $outputDir = $this->option('output') ?: 'build';
        $vendorName = $this->option('vendor');
        $description = $this->option('description') ?: "Laravel package for {$moduleName}";
        $author = $this->option('author') ?: 'Ngara K';
        $email = $this->option('email') ?: 'ngarakiringo@gmail.com';
        $license = $this->option('license') ?: 'MIT';

        // Prompt for vendor name if not provided
        if (!$vendorName) {
            $vendorName = $this->ask('Please provide a vendor name for the package', 'NgarakDev');
        }

        // Convert vendor name to lowercase dashed
        $vendorNameLower = strtolower(str_replace(' ', '-', $vendorName));

        // Convert module name to lowercase dashed
        $moduleNameLower = strtolower(str_replace(' ', '-', $moduleName));

        // Format package name
        $packageName = "{$vendorNameLower}/{$moduleNameLower}";

        // Get the module path
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $moduleName;

        // Check if module exists
        if (!$this->files->isDirectory($modulePath)) {
            $this->error("Module [{$moduleName}] does not exist!");
            return 1;
        }

        // Create the export directory structure
        $exportPath = base_path($outputDir . '/' . $moduleNameLower);

        if ($this->files->isDirectory($exportPath)) {
            $this->warn("Export directory already exists: {$exportPath}");
            if (!$this->confirm('Do you want to overwrite it?', true)) {
                $this->info('Export aborted.');
                return 0;
            }
            $this->files->deleteDirectory($exportPath);
        }

        $this->files->makeDirectory($exportPath, 0755, true);

        // Copy the module files
        $this->info("Exporting module to {$exportPath}...");
        $this->exportModuleFiles($modulePath, $exportPath, $moduleName, $vendorName);

        // Create package.json
        $this->createComposerJson($exportPath, $packageName, $moduleName, $vendorName, $description, $author, $email, $license);

        // Create LICENSE file
        $this->createLicenseFile($exportPath, $license, $author);

        // Create README.md
        $this->createReadmeFile($exportPath, $moduleName, $packageName, $description);

        // Create ServiceProvider for package
        $this->createServiceProvider($exportPath, $moduleName, $vendorName);

        $this->info("Module [{$moduleName}] exported successfully to: {$exportPath}");
        return 0;
    }

    /**
     * Export module files to the output directory.
     *
     * @param string $sourcePath Source module path
     * @param string $exportPath Export path
     * @param string $moduleName Module name
     * @param string $vendorName Vendor name
     * @return void
     */
    protected function exportModuleFiles($sourcePath, $exportPath, $moduleName, $vendorName)
    {
        // Create the src directory
        $srcPath = $exportPath . '/src';
        $this->files->makeDirectory($srcPath, 0755, true);

        // Get all module files and directories
        $files = $this->files->allFiles($sourcePath);

        $namespace = config('modularization.namespace', 'Modules');
        $newNamespace = $vendorName . '\\' . $moduleName;

        foreach ($files as $file) {
            // Get the relative path from the module root
            $relativePath = str_replace($sourcePath, '', $file->getPathname());

            // Create the export file path
            $exportFilePath = $srcPath . $relativePath;

            // Create the directory if it doesn't exist
            $exportFileDir = dirname($exportFilePath);
            if (!$this->files->isDirectory($exportFileDir)) {
                $this->files->makeDirectory($exportFileDir, 0755, true);
            }

            // Get file content and replace namespace
            $content = $this->files->get($file->getPathname());

            // Only modify PHP files
            if (Str::endsWith($file->getPathname(), '.php')) {
                // Replace namespace in PHP files
                $content = str_replace(
                    "namespace {$namespace}\\{$moduleName}",
                    "namespace {$newNamespace}",
                    $content
                );

                // Replace use statements for module classes
                $content = preg_replace(
                    "/(use\\s+){$namespace}\\\\{$moduleName}\\\\([^;]+);/",
                    "$1{$newNamespace}\\\\$2;",
                    $content
                );
            }

            // Write the file
            $this->files->put($exportFilePath, $content);
        }

        $this->info('Module files exported successfully.');
    }

    /**
     * Create the composer.json file.
     *
     * @param string $exportPath Export path
     * @param string $packageName Package name
     * @param string $moduleName Module name
     * @param string $vendorName Vendor name
     * @param string $description Package description
     * @param string $author Package author
     * @param string $email Author email
     * @param string $license Package license
     * @return void
     */
    protected function createComposerJson($exportPath, $packageName, $moduleName, $vendorName, $description, $author, $email, $license)
    {
        $composerJsonContent = [
            'name' => $packageName,
            'description' => $description,
            'type' => 'library',
            'license' => $license,
            'authors' => [
                [
                    'name' => $author,
                    'email' => $email
                ]
            ],
            'require' => [
                'php' => '^8.1',
                'illuminate/support' => '^10.0'
            ],
            'require-dev' => [
                'orchestra/testbench' => '^8.0',
                'phpunit/phpunit' => '^10.0'
            ],
            'autoload' => [
                'psr-4' => [
                    $vendorName . '\\' . $moduleName . '\\' => 'src/'
                ]
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $vendorName . '\\' . $moduleName . '\\Tests\\' => 'tests/'
                ]
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [
                        $vendorName . '\\' . $moduleName . '\\' . $moduleName . 'ServiceProvider'
                    ]
                ]
            ],
            'minimum-stability' => 'dev',
            'prefer-stable' => true
        ];

        $this->files->put(
            $exportPath . '/composer.json',
            json_encode($composerJsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info('Created composer.json file.');
    }

    /**
     * Create the LICENSE file.
     *
     * @param string $exportPath Export path
     * @param string $license License type
     * @param string $author Author name
     * @return void
     */
    protected function createLicenseFile($exportPath, $license, $author)
    {
        $year = date('Y');

        if ($license === 'MIT') {
            $licenseContent = <<<EOT
MIT License

Copyright (c) {$year} {$author}

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
EOT;
        } else {
            $licenseContent = "License: {$license}\n\nCopyright (c) {$year} {$author}\n";
        }

        $this->files->put($exportPath . '/LICENSE', $licenseContent);
        $this->info('Created LICENSE file.');
    }

    /**
     * Create the README.md file.
     *
     * @param string $exportPath Export path
     * @param string $moduleName Module name
     * @param string $packageName Package name
     * @param string $description Description
     * @return void
     */
    protected function createReadmeFile($exportPath, $moduleName, $packageName, $description)
    {
        $readmeContent = <<<EOT
# {$moduleName}

{$description}

## Installation

You can install the package via composer:

```bash
composer require {$packageName}
```

## Usage

```php
// Usage example
```

## License

Please see the LICENSE file for more information.
EOT;

        $this->files->put($exportPath . '/README.md', $readmeContent);
        $this->info('Created README.md file.');
    }

    /**
     * Create the ServiceProvider for the package.
     *
     * @param string $exportPath Export path
     * @param string $moduleName Module name
     * @param string $vendorName Vendor name
     * @return void
     */
    protected function createServiceProvider($exportPath, $moduleName, $vendorName)
    {
        $providerContent = <<<EOT
<?php

namespace {$vendorName}\\{$moduleName};

use Illuminate\Support\ServiceProvider;

class {$moduleName}ServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Load routes
        if (\$this->app->routesAreCached() === false) {
            \$this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
            \$this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
        }
        
        // Load views
        \$this->loadViewsFrom(__DIR__ . '/Resources/views', strtolower('{$moduleName}'));
        
        // Load translations
        \$this->loadTranslationsFrom(__DIR__ . '/Resources/lang', strtolower('{$moduleName}'));
        
        // Load migrations
        \$this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        
        // Publish assets
        \$this->publishes([
            __DIR__ . '/Resources/assets' => public_path('vendor/' . strtolower('{$moduleName}')),
        ], '{$moduleName}-assets');
        
        // Publish views
        \$this->publishes([
            __DIR__ . '/Resources/views' => resource_path('views/vendor/' . strtolower('{$moduleName}')),
        ], '{$moduleName}-views');
        
        // Publish translations
        \$this->publishes([
            __DIR__ . '/Resources/lang' => lang_path('vendor/' . strtolower('{$moduleName}')),
        ], '{$moduleName}-translations');
        
        // Publish config
        \$this->publishes([
            __DIR__ . '/Config' => config_path(strtolower('{$moduleName}')),
        ], '{$moduleName}-config');
    }
    
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        if (file_exists(__DIR__ . '/Config/config.php')) {
            \$this->mergeConfigFrom(__DIR__ . '/Config/config.php', strtolower('{$moduleName}'));
        }
        
        // Register module specific bindings
        if (file_exists(__DIR__ . '/Providers/{$moduleName}ServiceProvider.php')) {
            \$this->app->register("\\{$vendorName}\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider");
        }
    }
}
EOT;

        $this->files->put($exportPath . '/src/' . $moduleName . 'ServiceProvider.php', $providerContent);
        $this->info('Created ServiceProvider file.');
    }
}
