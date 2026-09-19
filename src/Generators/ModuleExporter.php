<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\ModuleConfiguration;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\Support\ModuleName;

final class ModuleExporter
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
    ) {}

    /**
     * @param  array{output?: string, vendor?: string, description?: string, author?: string, email?: string, license?: string, force?: bool}  $options
     */
    public function export(string $name, array $options = []): string
    {
        $module = ModuleName::parse($name);
        $modulePath = $this->paths->path($module->studly());

        if (! $this->files->isDirectory($modulePath)) {
            throw ModuleNotFoundException::make($module->studly(), $this->paths->modulesPath());
        }

        $outputDir = (string) ($options['output'] ?? 'build');
        $vendorName = (string) ($options['vendor'] ?? 'NgarakDev');
        $description = (string) ($options['description'] ?? "Laravel package for {$module->studly()}");
        $author = (string) ($options['author'] ?? 'Ngara K');
        $email = (string) ($options['email'] ?? 'ngarakiringo@gmail.com');
        $license = (string) ($options['license'] ?? 'MIT');
        $force = (bool) ($options['force'] ?? false);

        $vendorNameLower = strtolower(str_replace(' ', '-', $vendorName));
        $moduleNameLower = strtolower(str_replace(' ', '-', $module->studly()));
        $packageName = "{$vendorNameLower}/{$moduleNameLower}";
        $exportPath = base_path(trim($outputDir, '/\\').'/'.$moduleNameLower);

        if ($this->files->isDirectory($exportPath)) {
            if (! $force) {
                throw new \RuntimeException("Export directory already exists: {$exportPath}");
            }

            $this->files->deleteDirectory($exportPath);
        }

        $this->files->ensureDirectoryExists($exportPath, 0755);
        $this->exportModuleFiles($modulePath, $exportPath, $module->studly(), $vendorName);
        $this->createComposerJson($exportPath, $packageName, $module->studly(), $vendorName, $description, $author, $email, $license);
        $this->createLicenseFile($exportPath, $license, $author);
        $this->createReadmeFile($exportPath, $module->studly(), $packageName, $description);
        $this->createServiceProvider($exportPath, $module->studly(), $vendorName);

        return $exportPath;
    }

    private function exportModuleFiles(string $sourcePath, string $exportPath, string $moduleName, string $vendorName): void
    {
        $srcPath = $exportPath.'/src';
        $this->files->ensureDirectoryExists($srcPath, 0755);
        $namespace = $this->configuration->namespace();
        $newNamespace = $vendorName.'\\'.$moduleName;

        foreach ($this->files->allFiles($sourcePath) as $file) {
            $relativePath = str_replace($sourcePath, '', $file->getPathname());
            $exportFilePath = $srcPath.$relativePath;
            $this->files->ensureDirectoryExists(dirname($exportFilePath), 0755);
            $content = $this->files->get($file->getPathname());

            if (Str::endsWith($file->getPathname(), '.php')) {
                $content = str_replace(
                    "namespace {$namespace}\\{$moduleName}",
                    "namespace {$newNamespace}",
                    $content,
                );
                $replaced = preg_replace(
                    "/(use\\s+){$namespace}\\\\{$moduleName}\\\\([^;]+);/",
                    "$1{$newNamespace}\\\\$2;",
                    $content,
                );
                $content = is_string($replaced) ? $replaced : $content;
            }

            $this->files->put($exportFilePath, $content);
        }
    }

    private function createComposerJson(
        string $exportPath,
        string $packageName,
        string $moduleName,
        string $vendorName,
        string $description,
        string $author,
        string $email,
        string $license
    ): void {
        $composerJsonContent = [
            'name' => $packageName,
            'description' => $description,
            'type' => 'library',
            'license' => $license,
            'authors' => [
                [
                    'name' => $author,
                    'email' => $email,
                ],
            ],
            'require' => [
                'php' => '^8.1',
                'illuminate/support' => '^10.0|^11.0|^12.0',
            ],
            'require-dev' => [
                'orchestra/testbench' => '^8.0|^9.0|^10.0',
                'phpunit/phpunit' => '^10.0|^11.0',
            ],
            'autoload' => [
                'psr-4' => [
                    $vendorName.'\\'.$moduleName.'\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $vendorName.'\\'.$moduleName.'\\Tests\\' => 'tests/',
                ],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [
                        $vendorName.'\\'.$moduleName.'\\'.$moduleName.'ServiceProvider',
                    ],
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];

        $this->files->put(
            $exportPath.'/composer.json',
            json_encode($composerJsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    private function createLicenseFile(string $exportPath, string $license, string $author): void
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

        $this->files->put($exportPath.'/LICENSE', $licenseContent);
    }

    private function createReadmeFile(string $exportPath, string $moduleName, string $packageName, string $description): void
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

        $this->files->put($exportPath.'/README.md', $readmeContent);
    }

    private function createServiceProvider(string $exportPath, string $moduleName, string $vendorName): void
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
        if (\$this->app->routesAreCached() === false) {
            \$this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
            \$this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
        }

        \$this->loadViewsFrom(__DIR__ . '/Resources/views', strtolower('{$moduleName}'));
        \$this->loadTranslationsFrom(__DIR__ . '/Resources/lang', strtolower('{$moduleName}'));
        \$this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        \$this->publishes([
            __DIR__ . '/Resources/assets' => public_path('vendor/' . strtolower('{$moduleName}')),
        ], '{$moduleName}-assets');

        \$this->publishes([
            __DIR__ . '/Resources/views' => resource_path('views/vendor/' . strtolower('{$moduleName}')),
        ], '{$moduleName}-views');

        \$this->publishes([
            __DIR__ . '/Resources/lang' => lang_path('vendor/' . strtolower('{$moduleName}')),
        ], '{$moduleName}-translations');

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
        if (file_exists(__DIR__ . '/Config/config.php')) {
            \$this->mergeConfigFrom(__DIR__ . '/Config/config.php', strtolower('{$moduleName}'));
        }

        if (file_exists(__DIR__ . '/Providers/{$moduleName}ServiceProvider.php')) {
            \$this->app->register("\\{$vendorName}\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider");
        }
    }
}
EOT;

        $this->files->put($exportPath.'/src/'.$moduleName.'ServiceProvider.php', $providerContent);
    }
}
