<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use NgarakDev\Modularization\Tests\TestCase;

class CommandRegistrationTest extends TestCase
{
    public function test_it_registers_legacy_and_new_commands(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:module', $commands);
        $this->assertArrayHasKey('module:make', $commands);
        $this->assertArrayHasKey('module:list', $commands);
        $this->assertArrayHasKey('module:cache', $commands);
        $this->assertArrayHasKey('module:clear', $commands);
        $this->assertArrayHasKey('module:discover', $commands);
        $this->assertArrayHasKey('module:make-model', $commands);
        $this->assertArrayHasKey('module:make-controller', $commands);
        $this->assertSame(get_class($commands['make:module']), get_class($commands['module:make']));
    }

    public function test_make_module_keeps_documented_options(): void
    {
        $definition = Artisan::all()['make:module']->getDefinition();

        foreach (['api', 'force', 'with-views', 'with-livewire', 'with-livewire-only', 'resource', 'with-crud'] as $option) {
            $this->assertTrue($definition->hasOption($option), $option.' option is missing');
        }
    }
}
