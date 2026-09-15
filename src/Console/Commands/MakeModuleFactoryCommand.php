<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleFactoryCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-factory
                            {module : The name of the module}
                            {name : The factory name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a model factory in a module';

    protected string $type = 'factory';
}
