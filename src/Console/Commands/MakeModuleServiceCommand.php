<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleServiceCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-service
                            {module : The name of the module}
                            {name : The service name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a service and interface in a module';

    protected string $type = 'service';
}
