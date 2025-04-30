<?php

namespace NgarakDev\Modularization\Tests;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class CommandServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    /** @test */
    public function it_registers_both_command_names()
    {
        // Get all registered commands
        $commands = Artisan::all();

        // Check that both command names are registered
        $this->assertArrayHasKey('make:module', $commands);
        $this->assertArrayHasKey('module:make', $commands);

        // Verify they point to the same command class
        $makeModuleCommand = get_class($commands['make:module']);
        $moduleMakeCommand = get_class($commands['module:make']);

        $this->assertEquals($makeModuleCommand, $moduleMakeCommand);
    }

    /** @test */
    public function it_has_correctly_defined_options()
    {
        $command = Artisan::find('make:module');

        // Get the command definition
        $definition = $command->getDefinition();

        // Check that all the required options are defined
        $this->assertTrue($definition->hasOption('api'));
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('with-views'));
        $this->assertTrue($definition->hasOption('with-livewire'));
        $this->assertTrue($definition->hasOption('with-livewire-only'));
        $this->assertTrue($definition->hasOption('resource'));
        $this->assertTrue($definition->hasOption('with-crud'));
    }

    /** @test */
    public function it_has_the_correct_signature()
    {
        $command = Artisan::find('make:module');

        // Check the command's name argument exists
        $this->assertTrue($command->getDefinition()->hasArgument('name'));

        // Verify that the signature is formatted correctly
        $expectedSignaturePattern = '/make:module \{name : The name of the module\}.*\{--api.*\{--resource.*\{--force.*\{--with-crud.*\{--with-views.*\{--with-livewire.*\{--with-livewire-only/s';
        $reflection = new \ReflectionClass($command);
        $signature = $reflection->getProperty('signature');
        $signature->setAccessible(true);

        $this->assertMatchesRegularExpression($expectedSignaturePattern, $signature->getValue($command));
    }
}
