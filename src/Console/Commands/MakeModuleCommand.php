<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;
use NgarakDev\Modularization\Services\ModuleGenerator;
use NgarakDev\Modularization\Support\ModuleNameValidator;
use NgarakDev\Modularization\Support\ModulePathResolver;
use NgarakDev\Modularization\Support\StubRenderer;

/**
 * Command to create a new module.
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'module:make
                            {name : The name of the module}
                            {--api : Generate API controller and routes}
                            {--force : Force overwrite if module already exists}
                            {--resource= : Create a resource within the module (comma-separated)}
                            {--with-views : Generate view files for the module}
                            {--with-livewire : Generate Livewire components}
                            {--with-livewire-only : Generate only Livewire components without controllers}
                            {--with-crud : Generate CRUD operations}
                            {--with-translations : Generate translation files}
                            {--languages=* : Specify languages for translation files}';

    protected $description = 'Create a new module with repository pattern and service layer';

    private ModuleGenerator $generator;

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $validator = new ModuleNameValidator();

        try {
            $validator->validate($name);
        } catch (InvalidModuleException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $pathResolver = new ModulePathResolver(app());
        $stubRenderer = new StubRenderer($this->files);

        $this->generator = new ModuleGenerator(
            $this->files,
            $pathResolver,
            $validator,
            $stubRenderer,
        );

        $modulePath = $pathResolver->getModulePath($name);

        if ($this->files->isDirectory($modulePath) && !$this->option('force')) {
            if (!$this->confirm("Module [{$name}] already exists. Do you want to overwrite it?")) {
                $this->error('Module creation aborted.');
                return self::FAILURE;
            }
        }

        try {
            $options = $this->getOptions();
            $this->generator->generate($name, $options);
        } catch (ModuleGenerationException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->ensureModulesNamespaceInComposer($pathResolver);
        $this->outputNextSteps($name, $modulePath);

        $this->info("Module [{$name}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * Get command options as array.
     *
     * @return array<string, mixed>
     */
    private function getOptions(): array
    {
        return [
            'api' => $this->option('api'),
            'force' => $this->option('force'),
            'with-views' => $this->option('with-views'),
            'with-livewire' => $this->option('with-livewire'),
            'with-livewire-only' => $this->option('with-livewire-only'),
            'with-crud' => $this->option('with-crud'),
            'with-translations' => $this->option('with-translations'),
            'languages' => $this->option('languages') ?: ['en'],
        ];
    }

    /**
     * Ensure the Modules namespace is in composer.json.
     */
    private function ensureModulesNamespaceInComposer(ModulePathResolver $pathResolver): void
    {
        $composerPath = base_path('composer.json');

        if (!$this->files->exists($composerPath)) {
            $this->warn('composer.json not found. Please add the Modules namespace manually.');
            return;
        }

        $composerJson = json_decode($this->files->get($composerPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn('Unable to parse composer.json. Please add the Modules namespace manually.');
            return;
        }

        $namespace = $pathResolver->getNamespace();
        $modulesPath = basename($pathResolver->getBasePath());

        $psr4 = $composerJson['autoload']['psr-4'] ?? [];

        if (isset($psr4[$namespace . '\\'])) {
            return;
        }

        $composerJson['autoload'] ??= [];
        $composerJson['autoload']['psr-4'] ??= [];
        $composerJson['autoload']['psr-4'][$namespace . '\\'] = $modulesPath . '/';

        $this->files->put(
            $composerPath,
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $this->info("Added '{$namespace}\\' namespace to composer.json");
        $this->line('Run "composer dump-autoload" to update the autoloader.');
    }

    /**
     * Output next steps to the user.
     */
    private function outputNextSteps(string $name, string $modulePath): void
    {
        $moduleNameLower = strtolower($name);

        $this->newLine();
        $this->info('Next steps:');
        $this->newLine();

        $this->line('1. Run composer dump-autoload to update the autoloader:');
        $this->line('   <comment>composer dump-autoload</comment>');
        $this->newLine();

        $this->line('2. Access your module at:');
        $this->line("   <comment>/{$moduleNameLower}</comment>");
        $this->newLine();

        $this->line('3. Run migrations if needed:');
        $this->line('   <comment>php artisan migrate</comment>');
        $this->newLine();

        $this->line('4. List all modules:');
        $this->line('   <comment>php artisan module:list</comment>');
    }
}
