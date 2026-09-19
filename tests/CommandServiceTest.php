<?php

namespace NgarakDev\Modularization\Tests;

use Illuminate\Support\Facades\Artisan;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CommandServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    #[Test]
    public function it_registers_both_command_names()
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:module', $commands);
        $this->assertArrayHasKey('module:make', $commands);

        $makeModuleCommand = get_class($commands['make:module']);
        $moduleMakeCommand = get_class($commands['module:make']);

        $this->assertEquals($makeModuleCommand, $moduleMakeCommand);
    }

    #[Test]
    public function it_has_correctly_defined_options()
    {
        $command = Artisan::all()['make:module'];

        // Get the command definition
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('api'));
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('with-views'));
        $this->assertTrue($definition->hasOption('with-livewire'));
        $this->assertTrue($definition->hasOption('with-livewire-only'));
        $this->assertTrue($definition->hasOption('resource'));
        $this->assertTrue($definition->hasOption('with-crud'));
    }

    #[Test]
    public function it_has_the_correct_signature()
    {
        $command = Artisan::all()['make:module'];

        // Check the command's name argument exists
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('api'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }
}
