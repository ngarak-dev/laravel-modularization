<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleResourceCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-resource
                            {module : The name of the module}
                            {name : The API resource name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create an API resource in a module';

    protected string $type = 'resource';
}
