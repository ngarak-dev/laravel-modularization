<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleRequestCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-request
                            {module : The name of the module}
                            {name : The form request name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a form request in a module';

    protected string $type = 'request';
}
