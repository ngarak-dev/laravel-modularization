<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleSeederCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-seeder
                            {module : The name of the module}
                            {name : The seeder name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a seeder in a module';

    protected string $type = 'seeder';
}
