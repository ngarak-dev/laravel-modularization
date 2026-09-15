<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Generators\ClassGenerator;

class MakeModuleClassCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:make-class
                            {module : The name of the module}
                            {name : The class name}
                            {--type=model : The artifact type to generate}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate a class inside a module';

    protected string $type = 'model';

    public function handle(ClassGenerator $generator): int
    {
        $type = $this->type;

        if ($this->getDefinition()->hasOption('type')) {
            $type = (string) ($this->option('type') ?: $this->type);
        }

        try {
            $created = $generator->generate(
                (string) $this->argument('module'),
                $type,
                (string) $this->argument('name'),
                (bool) $this->option('force')
            );
        } catch (InvalidModuleException|ModuleNotFoundException|ModuleGenerationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($created as $path) {
            $this->line('Created: '.$path);
        }

        $this->info(ucfirst($type).' created successfully.');

        return self::SUCCESS;
    }
}
