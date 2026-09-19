<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;
use NgarakDev\Modularization\Generators\ModuleGenerator;
use NgarakDev\Modularization\Support\ModuleName;

/**
 * Command to create a new module.
 */
class MakeModuleCommand extends Command
{
    use InteractsWithModules;

    /**
     * @var array<int, string>
     */
    protected $aliases = ['module:make'];

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
                        {--languages=* : Specify languages for translation files}
                        {--requires=* : Module names this module depends on}
                        {--no-repository : Skip repository scaffolding}
                        {--no-service : Skip service scaffolding}';

    protected $description = 'Create a new module with optional repository and service layers';

    public function handle(ModuleGenerator $generator): int
    {
        try {
            $name = ModuleName::parse((string) $this->argument('name'))->studly();
        } catch (InvalidModuleException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($generator->exists($name)) {
            if (! $this->option('force')) {
                if (! $this->confirm("Module [{$name}] already exists. Do you want to overwrite it?", false)) {
                    $this->error('Module creation aborted!');

                    return self::FAILURE;
                }
            }

            $generator->delete($name);
        }

        try {
            $generator->generate($name, [
                'api' => (bool) $this->option('api'),
                'force' => (bool) $this->option('force'),
                'resource' => $this->option('resource'),
                'with-views' => (bool) $this->option('with-views'),
                'with-livewire' => (bool) $this->option('with-livewire'),
                'with-livewire-only' => (bool) $this->option('with-livewire-only'),
                'with-crud' => (bool) $this->option('with-crud'),
                'with-translations' => (bool) $this->option('with-translations'),
                'languages' => $this->option('languages'),
                'requires' => $this->option('requires'),
                'repositories' => ! $this->option('no-repository'),
                'services' => ! $this->option('no-service'),
            ]);
        } catch (ModuleGenerationException|InvalidModuleException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->outputNextSteps($name);
        $this->info("Module [{$name}] created successfully.");

        return self::SUCCESS;
    }

    private function outputNextSteps(string $name): void
    {
        $this->newLine();
        $this->info('Module created successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. composer dump-autoload');
        $this->line('  2. php artisan module:list');
        $this->line('  3. php artisan module:migrate '.$name);
        $this->newLine();
    }
}
