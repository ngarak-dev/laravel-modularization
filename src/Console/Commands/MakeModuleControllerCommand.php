<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use NgarakDev\Modularization\Generators\ClassGenerator;

class MakeModuleControllerCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-controller
                            {module : The name of the module}
                            {name : The controller name}
                            {--api : Generate an API controller}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a controller in a module';

    protected string $type = 'controller';

    public function handle(ClassGenerator $generator): int
    {
        $this->type = $this->option('api') ? 'api-controller' : 'controller';

        return parent::handle($generator);
    }
}
