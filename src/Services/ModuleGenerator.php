<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Services;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;
use NgarakDev\Modularization\Support\ModuleNameValidator;
use NgarakDev\Modularization\Support\ModulePathResolver;
use NgarakDev\Modularization\Support\StubRenderer;

/**
 * Generates module files and directories.
 */
final class ModuleGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModulePathResolver $pathResolver,
        private readonly ModuleNameValidator $nameValidator,
        private readonly StubRenderer $stubRenderer,
    ) {
    }

    /**
     * Generate a new module.
     *
     * @param array<string, mixed> $options
     */
    public function generate(string $name, array $options = []): string
    {
        $this->nameValidator->validate($name);

        $modulePath = $this->pathResolver->getModulePath($name);
        $namespace = $this->pathResolver->getNamespace();

        if ($this->files->isDirectory($modulePath) && !($options['force'] ?? false)) {
            throw ModuleGenerationException::alreadyExists($name);
        }

        if ($this->files->isDirectory($modulePath)) {
            $this->files->deleteDirectory($modulePath);
        }

        $this->createDirectories($modulePath);
        $this->createBaseFiles($name, $modulePath, $namespace, $options);

        return $modulePath;
    }

    /**
     * Create the module directory structure.
     */
    public function createDirectories(string $modulePath): void
    {
        $directories = config('modularization.directories', []);

        foreach ($directories as $directory) {
            $path = $modulePath . '/' . $directory;
            if (!$this->files->isDirectory($path)) {
                $this->files->makeDirectory($path, 0755, true);
            }
        }
    }

    /**
     * Create the base module files.
     *
     * @param array<string, mixed> $options
     */
    public function createBaseFiles(string $name, string $path, string $namespace, array $options): void
    {
        $replacements = $this->stubRenderer->getModuleReplacements($name, $namespace);

        $this->createModuleJson($name, $path, $namespace, $replacements);
        $this->createServiceProvider($name, $path, $replacements);
        $this->createModel($name, $path, $replacements);
        $this->createRepositoryInterface($name, $path, $replacements);
        $this->createRepository($name, $path, $replacements);
        $this->createServiceInterface($name, $path, $replacements);
        $this->createService($name, $path, $replacements);
        $this->createRequest($name, $path, $replacements);
        $this->createMigration($name, $path, $replacements);
        $this->createConfigFile($name, $path, $replacements);
        $this->createWebRoutes($name, $path, $replacements);
        $this->createWebController($name, $path, $replacements);

        if ($options['api'] ?? false) {
            $this->createApiController($name, $path, $replacements);
            $this->createApiRoutes($name, $path, $replacements);
        }

        if ($options['with-views'] ?? false) {
            $this->createViews($name, $path, $replacements);
        }

        if (($options['with-livewire'] ?? false) || ($options['with-livewire-only'] ?? false)) {
            $this->createLivewireComponents($name, $path, $replacements);
            $this->createLivewireRoutes($name, $path, $replacements);
        }

        if ($options['with-translations'] ?? false) {
            $this->createTranslations($name, $path, $options['languages'] ?? ['en']);
        }
    }

    /**
     * Create the module.json manifest file.
     *
     * @param array<string, string> $replacements
     */
    private function createModuleJson(string $name, string $path, string $namespace, array $replacements): void
    {
        $manifest = [
            'name' => $name,
            'description' => "{$name} module",
            'version' => '1.0.0',
            'enabled' => true,
            'provider' => "{$namespace}\\{$name}\\Providers\\{$name}ServiceProvider",
            'requires' => [],
            'routes' => [
                'prefix' => strtolower($name),
                'middleware' => ['web'],
            ],
            'menu' => [
                'title' => $name,
                'icon' => 'fa fa-th-large',
            ],
        ];

        $this->files->put(
            $path . '/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createServiceProvider(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('provider', $replacements);
        $this->files->put($path . '/Providers/' . $name . 'ServiceProvider.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createModel(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('model', $replacements);
        $this->files->put($path . '/Models/' . $name . '.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createRepositoryInterface(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('repository-interface', $replacements);
        $this->files->put($path . '/Repositories/Interfaces/' . $name . 'RepositoryInterface.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createRepository(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('repository', $replacements);
        $this->files->put($path . '/Repositories/' . $name . 'Repository.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createServiceInterface(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('service-interface', $replacements);
        $this->files->put($path . '/Services/Interfaces/' . $name . 'ServiceInterface.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createService(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('service', $replacements);
        $this->files->put($path . '/Services/' . $name . 'Service.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createRequest(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('request', $replacements);
        $this->files->put($path . '/Http/Requests/' . $name . 'Request.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createMigration(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('migration', $replacements);
        $timestamp = date('Y_m_d_His');
        $tableName = $replacements['{{table}}'];
        $filename = "{$timestamp}_create_{$tableName}_table.php";
        $this->files->put($path . '/Database/Migrations/' . $filename, $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createConfigFile(string $name, string $path, array $replacements): void
    {
        $content = "<?php\n\nreturn [\n    'name' => '{$name}',\n    'description' => '{$name} Module',\n    'enabled' => true,\n];\n";
        $this->files->put($path . '/Config/config.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createWebRoutes(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('web-routes', $replacements);
        $this->files->put($path . '/Routes/web.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createWebController(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('web-controller', $replacements);
        $this->files->put($path . '/Http/Controllers/' . $name . 'Controller.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createApiController(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('api-controller', $replacements);
        $this->files->put($path . '/Http/Controllers/API/' . $name . 'Controller.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createApiRoutes(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('api-routes', $replacements);
        $this->files->put($path . '/Routes/api.php', $content);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createViews(string $name, string $path, array $replacements): void
    {
        $moduleNameLower = strtolower($name);
        $viewsPath = $path . '/Resources/views/' . $moduleNameLower;
        $layoutsPath = $path . '/Resources/views/layouts';

        if (!$this->files->isDirectory($viewsPath)) {
            $this->files->makeDirectory($viewsPath, 0755, true);
        }

        if (!$this->files->isDirectory($layoutsPath)) {
            $this->files->makeDirectory($layoutsPath, 0755, true);
        }

        $views = ['view-index', 'view-show', 'view-create', 'view-edit'];

        foreach ($views as $view) {
            $viewName = str_replace('view-', '', $view);
            $content = $this->stubRenderer->render($view, $replacements);
            $this->files->put($viewsPath . '/' . $viewName . '.blade.php', $content);
        }

        $layoutContent = $this->stubRenderer->render('module-layout', $replacements);
        $this->files->put($layoutsPath . '/module-layout.blade.php', $layoutContent);

        if ($this->stubRenderer->hasStub('navigation')) {
            $navContent = $this->stubRenderer->render('navigation', $replacements);
            $this->files->put($layoutsPath . '/navigation.blade.php', $navContent);
        }
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createLivewireComponents(string $name, string $path, array $replacements): void
    {
        $livewirePath = $path . '/Livewire';
        $viewsPath = $path . '/Resources/views/livewire';

        if (!$this->files->isDirectory($livewirePath)) {
            $this->files->makeDirectory($livewirePath, 0755, true);
        }

        if (!$this->files->isDirectory($viewsPath)) {
            $this->files->makeDirectory($viewsPath, 0755, true);
        }

        $tableContent = $this->stubRenderer->render('livewire-table', $replacements);
        $this->files->put($livewirePath . '/' . $name . 'Table.php', $tableContent);

        $formContent = $this->stubRenderer->render('livewire-form', $replacements);
        $this->files->put($livewirePath . '/' . $name . 'Form.php', $formContent);

        $kebabName = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

        $tableViewContent = $this->stubRenderer->render('livewire-table-view', $replacements);
        $this->files->put($viewsPath . '/' . $kebabName . '-table.blade.php', $tableViewContent);

        $formViewContent = $this->stubRenderer->render('livewire-form-view', $replacements);
        $this->files->put($viewsPath . '/' . $kebabName . '-form.blade.php', $formViewContent);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function createLivewireRoutes(string $name, string $path, array $replacements): void
    {
        $content = $this->stubRenderer->render('livewire-routes', $replacements);
        $this->files->put($path . '/Routes/livewire.php', $content);
    }

    /**
     * Create translation files for the module.
     *
     * @param array<string> $languages
     */
    private function createTranslations(string $name, string $path, array $languages): void
    {
        $langPath = $path . '/Resources/lang';

        foreach ($languages as $lang) {
            $langDir = $langPath . '/' . $lang;

            if (!$this->files->isDirectory($langDir)) {
                $this->files->makeDirectory($langDir, 0755, true);
            }

            $content = "<?php\n\nreturn [\n    'welcome' => 'Welcome to {$name}',\n];\n";
            $this->files->put($langDir . '/messages.php', $content);
        }
    }
}
