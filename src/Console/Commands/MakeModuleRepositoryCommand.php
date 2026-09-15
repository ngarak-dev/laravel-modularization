<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleRepositoryCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-repository
                            {module : The name of the module}
                            {name : The repository name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a repository and interface in a module';

    protected string $type = 'repository';
}
