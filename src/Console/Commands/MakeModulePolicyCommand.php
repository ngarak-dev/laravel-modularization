<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModulePolicyCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-policy
                            {module : The name of the module}
                            {name : The policy name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a policy in a module';

    protected string $type = 'policy';
}
