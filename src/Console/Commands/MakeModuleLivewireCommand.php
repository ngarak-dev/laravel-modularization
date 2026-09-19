<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Generators\ClassGenerator;
use NgarakDev\Modularization\Support\ModuleName;

class MakeModuleLivewireCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:make-livewire
                            {module : The name of the module}
                            {name : The name of the Livewire component}
                            {--force : Overwrite existing files}
                            {--subdirectory= : Optional subdirectory within the Livewire directory}
                            {--view-only : Create only the view file without the component class}
                            {--class-only : Create only the component class without the view file}';

    protected $description = 'Create a new Livewire component in a module';

    public function handle(ClassGenerator $generator): int
    {
        try {
            $module = ModuleName::parse((string) $this->argument('module'))->studly();
            $name = (string) $this->argument('name');
            $subdirectory = $this->option('subdirectory');

            if (is_string($subdirectory) && $subdirectory !== '') {
                $name = trim($subdirectory, '/\\').'/'.$name;
            }

            if ($this->option('view-only')) {
                $created = $generator->generate($module, 'livewire', $name, (bool) $this->option('force'), [
                    'class-only' => true,
                ]);
                array_shift($created);
            } else {
                $created = $generator->generate($module, 'livewire', $name, (bool) $this->option('force'), [
                    'class-only' => (bool) $this->option('class-only'),
                ]);
            }
        } catch (InvalidModuleException|ModuleNotFoundException|ModuleGenerationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($created as $path) {
            $this->line('Created: '.$path);
        }

        $this->info("Livewire component {$name} created successfully for module {$module}.");

        return self::SUCCESS;
    }
}
