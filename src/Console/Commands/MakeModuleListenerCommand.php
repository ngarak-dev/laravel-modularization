<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleListenerCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-listener
                            {module : The name of the module}
                            {name : The listener name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create an event listener in a module';

    protected string $type = 'listener';
}
