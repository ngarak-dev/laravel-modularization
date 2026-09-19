<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\ModuleDiscovery;
use NgarakDev\Modularization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModuleSecurityTest extends TestCase
{
    #[Test]
    public function module_names_with_path_traversal_are_rejected(): void
    {
        $discovery = $this->app->make(ModuleDiscovery::class);
        $traversalNames = [
            '../etc',
            '../../etc/passwd',
            '..',
            '../',
            '..\\Windows',
            'foo/../bar',
        ];

        foreach ($traversalNames as $name) {
            $this->assertFalse(
                $discovery->isValidModuleName($name),
                "Expected '{$name}' to be rejected as invalid module name"
            );
        }
    }

    #[Test]
    public function module_names_with_slashes_are_rejected(): void
    {
        $discovery = $this->app->make(ModuleDiscovery::class);

        $this->assertFalse($discovery->isValidModuleName('foo/bar'));
        $this->assertFalse($discovery->isValidModuleName('foo\\bar'));
        $this->assertFalse($discovery->isValidModuleName('/absolute'));
        $this->assertFalse($discovery->isValidModuleName('\\absolute'));
    }

    #[Test]
    public function module_names_with_special_characters_are_rejected(): void
    {
        $discovery = $this->app->make(ModuleDiscovery::class);
        $invalidNames = [
            'foo bar',
            'foo!bar',
            'foo@bar',
            'foo#bar',
            'foo$bar',
            'foo%bar',
            'foo^bar',
            'foo&bar',
            'foo*bar',
            'foo(bar)',
            'foo;bar',
            '',
        ];

        foreach ($invalidNames as $name) {
            $this->assertFalse(
                $discovery->isValidModuleName($name),
                "Expected '{$name}' to be rejected as invalid module name"
            );
        }
    }

    #[Test]
    public function module_names_starting_with_numbers_are_rejected(): void
    {
        $discovery = $this->app->make(ModuleDiscovery::class);

        $this->assertFalse($discovery->isValidModuleName('1Module'));
        $this->assertFalse($discovery->isValidModuleName('123'));
        $this->assertFalse($discovery->isValidModuleName('0Alpha'));
    }

    #[Test]
    public function dot_prefixed_directories_are_not_discovered_as_modules(): void
    {
        $this->files->makeDirectory($this->modulesPath.'/.hidden');
        $this->files->makeDirectory($this->modulesPath.'/.git');
        $this->writeModuleFixture('ValidModule');

        $modules = $this->app->make(ModuleDiscovery::class)->discover(false);

        $this->assertArrayNotHasKey('.hidden', $modules);
        $this->assertArrayNotHasKey('.git', $modules);
        $this->assertArrayHasKey('ValidModule', $modules);
    }

    #[Test]
    public function make_module_command_rejects_path_traversal_names(): void
    {
        $this->artisan('module:make', ['name' => '../EscapeModule'])->assertFailed();
    }

    #[Test]
    public function make_module_command_rejects_names_with_slashes(): void
    {
        $this->artisan('module:make', ['name' => 'foo/bar'])->assertFailed();
    }

    #[Test]
    public function make_module_command_rejects_empty_name(): void
    {
        $this->artisan('module:make', ['name' => ''])->assertFailed();
    }

    #[Test]
    public function valid_module_names_pass_validation(): void
    {
        $discovery = $this->app->make(ModuleDiscovery::class);
        $validNames = [
            'Orders',
            'OrderManagement',
            'order_management',
            'order-management',
            'Module123',
            'CamelCase',
            'under_score',
        ];

        foreach ($validNames as $name) {
            $this->assertTrue(
                $discovery->isValidModuleName($name),
                "Expected '{$name}' to be accepted as a valid module name"
            );
        }
    }

    #[Test]
    public function discovery_only_reads_within_configured_modules_path(): void
    {
        $outsideDir = sys_get_temp_dir().'/outside_modules_'.uniqid();
        $this->files->makeDirectory($outsideDir.'/EscapedModule', 0755, true);
        $this->writeModuleFixture('Inside');

        $modules = $this->app->make(ModuleDiscovery::class)->discover(false);

        $this->assertArrayNotHasKey('EscapedModule', $modules);
        $this->assertArrayHasKey('Inside', $modules);

        $this->files->deleteDirectory($outsideDir);
    }
}
