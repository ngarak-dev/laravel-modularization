<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleModelCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-model
                            {module : The name of the module}
                            {name : The model name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a model in a module';

    protected string $type = 'model';
}
