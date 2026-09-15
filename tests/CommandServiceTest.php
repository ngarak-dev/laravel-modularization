<?php

namespace NgarakDev\Modularization\Tests;

use Illuminate\Support\Facades\Artisan;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use Orchestra\Testbench\TestCase;

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
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:module', $commands);
        $this->assertArrayHasKey('module:make', $commands);

        $makeModuleCommand = get_class($commands['make:module']);
        $moduleMakeCommand = get_class($commands['module:make']);

        $this->assertEquals($makeModuleCommand, $moduleMakeCommand);
    }

    /** @test */
    public function it_has_correctly_defined_options()
    {
        $command = $this->app->make(MakeModuleCommand::class);
        $definition = $command->getDefinition();

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
        $command = $this->app->make(MakeModuleCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('name'));

        $reflection = new \ReflectionClass($command);
        $signatureProp = $reflection->getProperty('signature');
        $signatureProp->setAccessible(true);
        $signature = $signatureProp->getValue($command);

        $this->assertMatchesRegularExpression('/make:module/', $signature);
        $this->assertMatchesRegularExpression('/\{name/', $signature);
        $this->assertMatchesRegularExpression('/\{--api/', $signature);
        $this->assertMatchesRegularExpression('/\{--resource/', $signature);
        $this->assertMatchesRegularExpression('/\{--force/', $signature);
        $this->assertMatchesRegularExpression('/\{--with-views/', $signature);
        $this->assertMatchesRegularExpression('/\{--with-livewire/', $signature);
        $this->assertMatchesRegularExpression('/\{--with-livewire-only/', $signature);
    }
}
