<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleConsoleCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-command
                            {module : The name of the module}
                            {name : The command name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create an Artisan command in a module';

    protected string $type = 'command';
}
