<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use NgarakDev\Modularization\Generators\ClassGenerator;

class MakeModuleTestCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-test
                            {module : The name of the module}
                            {name : The test name}
                            {--unit : Create a unit test}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a test in a module';

    protected string $type = 'test';

    public function handle(ClassGenerator $generator): int
    {
        $this->type = $this->option('unit') ? 'unit-test' : 'test';

        return parent::handle($generator);
    }
}
