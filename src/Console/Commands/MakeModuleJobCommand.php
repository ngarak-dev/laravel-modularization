<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleJobCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-job
                            {module : The name of the module}
                            {name : The job name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a queued job in a module';

    protected string $type = 'job';
}
