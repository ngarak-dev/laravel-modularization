<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Generators;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\ModuleCache;
use NgarakDev\Modularization\ModuleConfiguration;
use NgarakDev\Modularization\ModuleManifest;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\StubLocator;
use NgarakDev\Modularization\Support\ModuleName;

final class ModuleGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
        private readonly ModuleManifest $manifest,
        private readonly StubLocator $stubs,
        private readonly ModuleCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    public function generate(string $name, array $options = []): array
    {
        $module = ModuleName::parse($name);
        $path = $this->paths->path($module->studly());
        $created = [];

        $this->ensureComposerNamespace();
        $this->createDirectories($path);
        $created = array_merge($created, $this->createCoreFiles($module, $path, $options));
        $created = array_merge($created, $this->writeViteAssets($module, $path));

        $this->cache->forget();
        $this->dumpAutoload();

        return $created;
    }

    /**
     * @param  array<int, string>  $languages
     * @return array<int, string>
     */
    public function generateTranslations(string $name, array $languages = [], bool $force = false): array
    {
        $module = ModuleName::parse($name);
        $path = $this->paths->path($module->studly());

        if (! $this->files->isDirectory($path)) {
            throw \NgarakDev\Modularization\Exceptions\ModuleNotFoundException::make($module->studly(), $this->paths->modulesPath());
        }

        if ($languages === []) {
            $languages = ['en', 'es', 'fr', 'de'];
        }

        $created = [];

        foreach ($languages as $language) {
            $lang = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $language) ?: 'en';
            $langDir = $path.'/Resources/lang/'.$lang;
            $this->files->ensureDirectoryExists($langDir, 0755);

            $files = [
                $langDir.'/general.php' => $this->generalTranslationStub(),
                $langDir.'/validation.php' => $this->validationTranslationStub(),
                $langDir.'/'.$module->lower().'.php' => $this->moduleTranslationStub($module, $lang),
            ];

            foreach ($files as $target => $content) {
                if ($this->files->exists($target) && ! $force) {
                    continue;
                }

                $created[] = $this->writeRaw($target, $content);
            }
        }

        return $created;
    }

    public function exists(string $name): bool
    {
        return $this->files->isDirectory($this->paths->path(ModuleName::parse($name)->studly()));
    }

    public function delete(string $name): void
    {
        $path = $this->paths->path(ModuleName::parse($name)->studly());

        if ($this->files->isDirectory($path)) {
            $this->files->deleteDirectory($path);
        }
    }

    private function createDirectories(string $path): void
    {
        foreach ($this->configuration->directories() as $directory) {
            $this->files->ensureDirectoryExists($this->paths->join($path, $directory), 0755);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function createCoreFiles(ModuleName $module, string $path, array $options): array
    {
        $created = [];
        $withApi = (bool) ($options['api'] ?? false);
        $withViews = (bool) ($options['with-views'] ?? false);
        $withLivewire = (bool) ($options['with-livewire'] ?? false);
        $withLivewireOnly = (bool) ($options['with-livewire-only'] ?? false);
        $withTranslations = (bool) ($options['with-translations'] ?? false);
        $withRepositories = (bool) ($options['repositories'] ?? $this->configuration->scaffoldRepositories());
        $withServices = (bool) ($options['services'] ?? $this->configuration->scaffoldServices());
        $resources = $this->normalizeResources($options['resource'] ?? null);
        $requires = $this->normalizeRequires($options['requires'] ?? []);
        $languagesOption = $options['languages'] ?? [];
        $languages = is_array($languagesOption) && $languagesOption !== []
            ? array_values(array_map('strval', $languagesOption))
            : ['en', 'es', 'fr', 'de'];

        $created[] = $this->writeManifest($module, $path, $requires);
        $created[] = $this->writeProvider($module, $path, $withRepositories, $withServices);
        $created[] = $this->writeFromStub('config', $path.'/Config/config.php', $module);

        $className = $module;

        if (! $withLivewireOnly) {
            $created[] = $this->writeFromStub('model', $path.'/Models/'.$module->studly().'.php', $module, $className);

            if ($withRepositories) {
                $created[] = $this->writeFromStub('repository-interface', $path.'/Repositories/Interfaces/'.$module->studly().'RepositoryInterface.php', $module, $className);
                $created[] = $this->writeFromStub('repository', $path.'/Repositories/'.$module->studly().'Repository.php', $module, $className);
            }

            if ($withServices) {
                $created[] = $this->writeFromStub('service-interface', $path.'/Services/Interfaces/'.$module->studly().'ServiceInterface.php', $module, $className);
                $created[] = $this->writeFromStub('service', $path.'/Services/'.$module->studly().'Service.php', $module, $className);
            }

            $created[] = $this->writeFromStub('web-controller', $path.'/Http/Controllers/'.$module->studly().'Controller.php', $module, $className);
            $created[] = $this->writeFromStub('request', $path.'/Http/Requests/'.$module->studly().'Request.php', $module, $className);
            $created[] = $this->writeFromStub('web-routes', $path.'/Routes/web.php', $module, $className);
            $created[] = $this->writeMigration($module, $path);

            if ($withApi) {
                $created[] = $this->writeFromStub('api-controller', $path.'/Http/Controllers/API/'.$module->studly().'Controller.php', $module, $className);
                $created[] = $this->writeFromStub('api-routes', $path.'/Routes/api.php', $module, $className);
            }

            if ($withViews) {
                $created = array_merge($created, $this->writeViews($module, $path, $className));
            }
        }

        if ($withLivewire || $withLivewireOnly) {
            $created = array_merge($created, $this->writeLivewire($module, $path, $className));
            $created[] = $this->writeFromStub('livewire-routes', $path.'/Routes/livewire.php', $module, $className);
        }

        if ($withTranslations) {
            $created = array_merge($created, $this->writeTranslations($module, $path, $languages));
        }

        foreach ($resources as $resource) {
            $created = array_merge($created, $this->writeResource($module, $path, $resource, $options));
        }

        return array_values(array_filter($created));
    }

    private function writeProvider(ModuleName $module, string $path, bool $withRepositories, bool $withServices): string
    {
        if ($withRepositories && $withServices) {
            return $this->writeFromStub('provider', $path.'/Providers/'.$module->studly().'ServiceProvider.php', $module);
        }

        $namespace = $this->configuration->namespace();
        $uses = '';
        $bindings = '        //';

        if ($withRepositories) {
            $uses .= "use {$namespace}\\{$module->studly()}\\Repositories\\Interfaces\\{$module->studly()}RepositoryInterface;".PHP_EOL;
            $uses .= "use {$namespace}\\{$module->studly()}\\Repositories\\{$module->studly()}Repository;".PHP_EOL;
            $bindings = "        \$this->app->bind({$module->studly()}RepositoryInterface::class, {$module->studly()}Repository::class);";
        }

        if ($withServices) {
            $uses .= "use {$namespace}\\{$module->studly()}\\Services\\Interfaces\\{$module->studly()}ServiceInterface;".PHP_EOL;
            $uses .= "use {$namespace}\\{$module->studly()}\\Services\\{$module->studly()}Service;".PHP_EOL;
            $bindings = "        \$this->app->bind({$module->studly()}ServiceInterface::class, {$module->studly()}Service::class);";
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\{$module->studly()}\\Providers;

use Illuminate\\Support\\ServiceProvider;
{$uses}
class {$module->studly()}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
{$bindings}
    }

    public function boot(): void
    {
        //
    }
}

PHP;

        return $this->writeRaw($path.'/Providers/'.$module->studly().'ServiceProvider.php', $content);
    }

    /**
     * @param  array<int, string>  $requires
     */
    private function writeManifest(ModuleName $module, string $path, array $requires): string
    {
        $namespace = $this->configuration->namespace();

        return $this->manifest->write($path, [
            'name' => $module->studly(),
            'namespace' => $namespace.'\\'.$module->studly(),
            'provider' => $namespace.'\\'.$module->studly().'\\Providers\\'.$module->studly().'ServiceProvider',
            'version' => '1.0.0',
            'description' => $module->studly().' module',
            'enabled' => true,
            'requires' => $requires,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function writeResource(ModuleName $module, string $path, ModuleName $resource, array $options): array
    {
        $created = [];
        $withApi = (bool) ($options['api'] ?? false);
        $withViews = (bool) ($options['with-views'] ?? false);
        $withLivewire = (bool) ($options['with-livewire'] ?? false) || (bool) ($options['with-livewire-only'] ?? false);
        $withLivewireOnly = (bool) ($options['with-livewire-only'] ?? false);

        $created[] = $this->writeFromStub('model', $path.'/Models/'.$resource->studly().'.php', $module, $resource);
        $created[] = $this->writeFromStub('repository-interface', $path.'/Repositories/Interfaces/'.$resource->studly().'RepositoryInterface.php', $module, $resource);
        $created[] = $this->writeFromStub('repository', $path.'/Repositories/'.$resource->studly().'Repository.php', $module, $resource);
        $created[] = $this->writeFromStub('service-interface', $path.'/Services/Interfaces/'.$resource->studly().'ServiceInterface.php', $module, $resource);
        $created[] = $this->writeFromStub('service', $path.'/Services/'.$resource->studly().'Service.php', $module, $resource);

        if (! $withLivewireOnly) {
            $created[] = $this->writeFromStub('web-controller', $path.'/Http/Controllers/'.$resource->studly().'Controller.php', $module, $resource);
            $created[] = $this->writeFromStub('request', $path.'/Http/Requests/'.$resource->studly().'Request.php', $module, $resource);

            if ($withApi) {
                $created[] = $this->writeFromStub('api-controller', $path.'/Http/Controllers/API/'.$resource->studly().'Controller.php', $module, $resource);
                $this->appendApiResourceRoute($path, $module, $resource);
            }

            if ($withViews) {
                $created = array_merge($created, $this->writeViews($module, $path, $resource));
            }
        }

        if ($withLivewire) {
            $created = array_merge($created, $this->writeLivewire($module, $path, $resource));
        }

        return $created;
    }

    /**
     * @return array<int, string>
     */
    private function writeViews(ModuleName $module, string $path, ModuleName $class): array
    {
        $viewsPath = $path.'/Resources/views/'.$class->lower();
        $this->files->ensureDirectoryExists($viewsPath, 0755);
        $this->files->ensureDirectoryExists($path.'/Resources/views/layouts', 0755);

        $created = [
            $this->writeFromStub('view-index', $viewsPath.'/index.blade.php', $module, $class),
            $this->writeFromStub('view-show', $viewsPath.'/show.blade.php', $module, $class),
            $this->writeFromStub('view-create', $viewsPath.'/create.blade.php', $module, $class),
            $this->writeFromStub('view-edit', $viewsPath.'/edit.blade.php', $module, $class),
            $this->writeFromStub('module-layout', $path.'/Resources/views/layouts/module-layout.blade.php', $module, $class),
            $this->writeFromStub('navigation', $path.'/Resources/views/layouts/navigation.blade.php', $module, $class),
        ];

        return $created;
    }

    /**
     * @return array<int, string>
     */
    private function writeLivewire(ModuleName $module, string $path, ModuleName $class): array
    {
        $this->files->ensureDirectoryExists($path.'/Livewire', 0755);
        $this->files->ensureDirectoryExists($path.'/Resources/views/livewire', 0755);

        return [
            $this->writeFromStub('livewire-table', $path.'/Livewire/'.$class->studly().'Table.php', $module, $class),
            $this->writeFromStub('livewire-form', $path.'/Livewire/'.$class->studly().'Form.php', $module, $class),
            $this->writeFromStub('livewire-table-view', $path.'/Resources/views/livewire/'.$class->kebab().'-table.blade.php', $module, $class),
            $this->writeFromStub('livewire-form-view', $path.'/Resources/views/livewire/'.$class->kebab().'-form.blade.php', $module, $class),
        ];
    }

    /**
     * @param  array<int, string>  $languages
     * @return array<int, string>
     */
    private function writeTranslations(ModuleName $module, string $path, array $languages): array
    {
        $created = [];

        if ($languages === []) {
            $languages = ['en'];
        }

        foreach ($languages as $language) {
            $lang = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $language) ?: 'en';
            $langDir = $path.'/Resources/lang/'.$lang;
            $this->files->ensureDirectoryExists($langDir, 0755);

            $created[] = $this->writeRaw($langDir.'/general.php', $this->generalTranslationStub());
            $created[] = $this->writeRaw($langDir.'/validation.php', $this->validationTranslationStub());
            $created[] = $this->writeRaw($langDir.'/'.$module->lower().'.php', $this->moduleTranslationStub($module, $lang));
        }

        return $created;
    }

    /**
     * @return array<int, string>
     */
    private function writeViteAssets(ModuleName $module, string $path): array
    {
        $jsDir = $path.'/Resources/assets/js';
        $cssDir = $path.'/Resources/assets/css';
        $this->files->ensureDirectoryExists($jsDir, 0755);
        $this->files->ensureDirectoryExists($cssDir, 0755);

        return [
            $this->writeRaw($jsDir.'/app.js', "// {$module->studly()} module entry\n"),
            $this->writeRaw($cssDir.'/app.css', "/* {$module->studly()} module styles */\n"),
        ];
    }

    private function dumpAutoload(): void
    {
        if (! $this->configuration->dumpAutoload()) {
            return;
        }

        $composer = $this->composerBinary();

        if ($composer === null) {
            return;
        }

        $command = escapeshellarg($composer).' dump-autoload --no-interaction';
        exec($command, $output, $status);
    }

    private function composerBinary(): ?string
    {
        $candidates = [
            base_path('vendor/bin/composer'),
            'composer',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'composer' || is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function writeMigration(ModuleName $module, string $path): string
    {
        $table = $module->snakePlural();
        $filename = date('Y_m_d_His').'_create_'.$table.'_table.php';
        $target = $path.'/Database/Migrations/'.$filename;

        return $this->writeFromStub('migration', $target, $module, $module, ['{{table}}' => $table]);
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function writeFromStub(string $stub, string $target, ModuleName $module, ?ModuleName $class = null, array $extra = []): string
    {
        $class ??= $module;
        $namespace = $this->configuration->namespace();

        $modulePath = $this->paths->path($module->studly());
        $relative = trim(str_replace('\\', '/', substr(dirname($target), strlen(rtrim($modulePath, '/\\')))), '/');
        $classNamespace = $namespace.'\\'.$module->studly();
        if ($relative !== '' && $relative !== '.') {
            $classNamespace .= '\\'.str_replace('/', '\\', $relative);
        }

        $replacements = array_merge([
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $module->studly(),
            '{{moduleNameLower}}' => $module->lower(),
            '{{moduleNameKebab}}' => $module->kebab(),
            '{{className}}' => $class->studly(),
            '{{classNameLower}}' => $class->lower(),
            '{{classNamespace}}' => $classNamespace,
            '{{moduleNamespace}}' => $namespace.'\\'.$module->studly(),
            '{{table}}' => $class->snakePlural(),
        ], $extra);

        $content = $this->stubs->render($stub, $replacements);

        if ($content === '') {
            return '';
        }

        $this->files->ensureDirectoryExists(dirname($target), 0755);
        $this->files->put($target, $content);

        return $target;
    }

    private function writeRaw(string $target, string $content): string
    {
        $this->files->ensureDirectoryExists(dirname($target), 0755);
        $this->files->put($target, $content);

        return $target;
    }

    private function appendApiResourceRoute(string $path, ModuleName $module, ModuleName $resource): void
    {
        $apiPath = $path.'/Routes/api.php';

        if (! $this->files->exists($apiPath)) {
            $this->writeFromStub('api-routes', $apiPath, $module, $resource);

            return;
        }

        $namespace = $this->configuration->namespace();
        $append = PHP_EOL."Route::apiResource('".$resource->lower()."', \\{$namespace}\\{$module->studly()}\\Http\\Controllers\\API\\{$resource->studly()}Controller::class);".PHP_EOL;
        $this->files->append($apiPath, $append);
    }

    /**
     * @return array<int, ModuleName>
     */
    private function normalizeResources(mixed $resource): array
    {
        if ($resource === null || $resource === '' || $resource === false) {
            return [];
        }

        $parts = is_array($resource) ? $resource : explode(',', (string) $resource);

        return array_values(array_map(
            static fn (string $name): ModuleName => ModuleName::parse(trim($name)),
            array_filter($parts, static fn ($name): bool => trim((string) $name) !== '')
        ));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRequires(mixed $requires): array
    {
        if ($requires === null || $requires === '' || $requires === false) {
            return [];
        }

        $parts = is_array($requires) ? $requires : explode(',', (string) $requires);

        return array_values(array_map(
            static fn (string $name): string => ModuleName::parse(trim($name))->studly(),
            array_filter($parts, static fn ($name): bool => trim((string) $name) !== '')
        ));
    }

    private function ensureComposerNamespace(): void
    {
        if (! $this->configuration->updateComposer()) {
            return;
        }

        $composerPath = base_path('composer.json');

        if (! $this->files->isFile($composerPath)) {
            return;
        }

        $composer = json_decode($this->files->get($composerPath), true);

        if (! is_array($composer)) {
            return;
        }

        $namespace = $this->configuration->namespace().'\\';
        $relative = $this->configuration->modulesPathRelative().'/';

        if (isset($composer['autoload']['psr-4'][$namespace])) {
            return;
        }

        $composer['autoload']['psr-4'][$namespace] = $relative;
        $this->files->put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    private function generalTranslationStub(): string
    {
        return <<<'PHP'
<?php

return [
    'created' => 'Created successfully',
    'updated' => 'Updated successfully',
    'deleted' => 'Deleted successfully',
    'not_found' => 'Not found',
    'error' => 'An error occurred',
    'actions' => 'Actions',
    'create' => 'Create',
    'edit' => 'Edit',
    'update' => 'Update',
    'delete' => 'Delete',
    'cancel' => 'Cancel',
    'save' => 'Save',
    'back' => 'Back',
    'confirm' => 'Confirm',
    'confirm_delete' => 'Are you sure you want to delete this?',
    'yes' => 'Yes',
    'no' => 'No',
];

PHP;
    }

    private function validationTranslationStub(): string
    {
        return <<<'PHP'
<?php

        return [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be a string.',
            'numeric' => 'The :attribute must be a number.',
            'email' => 'The :attribute must be a valid email address.',
            'min' => [
                'string' => 'The :attribute must be at least :min characters.',
                'numeric' => 'The :attribute must be at least :min.',
                'array' => 'The :attribute must have at least :min items.',
            ],
            'max' => [
                'string' => 'The :attribute must not exceed :max characters.',
                'numeric' => 'The :attribute must not exceed :max.',
                'array' => 'The :attribute must not have more than :max items.',
            ],
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'date' => 'The :attribute is not a valid date.',
        ];

PHP;
    }

    private function moduleTranslationStub(ModuleName $module, string $language): string
    {
        $name = $module->studly();
        $lower = $module->lower();

        return <<<PHP
<?php

return [
    '{$lower}' => '{$name}',
    'all_{$lower}s' => 'All {$name}s',
    'create_{$lower}' => 'Create {$name}',
    'edit_{$lower}' => 'Edit {$name}',
    'details' => 'Details',
    'name' => 'Name',
    'description' => 'Description',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
];

PHP;
    }
}
