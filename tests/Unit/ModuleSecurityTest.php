<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use NgarakDev\Modularization\Support\ModuleDiscovery;
use Orchestra\Testbench\TestCase;

/**
 * Security regression tests ensuring module names and paths cannot escape
 * the configured modules directory via traversal or injection attacks.
 */
class ModuleSecurityTest extends TestCase
{
    protected Filesystem $files;
    protected string $modulesPath;
    protected ModuleDiscovery $discovery;

    protected function getPackageProviders($app): array
    {
        return [ModularizationServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->modulesPath = sys_get_temp_dir() . '/security_modules_' . uniqid();
        $this->files->makeDirectory($this->modulesPath, 0755, true);

        $this->discovery = new ModuleDiscovery(
            files: $this->files,
            modulesPath: $this->modulesPath,
            defaultNamespace: 'Modules',
        );
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function module_names_with_path_traversal_are_rejected(): void
    {
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
                $this->discovery->isValidModuleName($name),
                "Expected '{$name}' to be rejected as invalid module name"
            );
        }
    }

    /** @test */
    public function module_names_with_slashes_are_rejected(): void
    {
        $this->assertFalse($this->discovery->isValidModuleName('foo/bar'));
        $this->assertFalse($this->discovery->isValidModuleName('foo\\bar'));
        $this->assertFalse($this->discovery->isValidModuleName('/absolute'));
        $this->assertFalse($this->discovery->isValidModuleName('\\absolute'));
    }

    /** @test */
    public function module_names_with_special_characters_are_rejected(): void
    {
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
                $this->discovery->isValidModuleName($name),
                "Expected '{$name}' to be rejected as invalid module name"
            );
        }
    }

    /** @test */
    public function module_names_starting_with_numbers_are_rejected(): void
    {
        $this->assertFalse($this->discovery->isValidModuleName('1Module'));
        $this->assertFalse($this->discovery->isValidModuleName('123'));
        $this->assertFalse($this->discovery->isValidModuleName('0Alpha'));
    }

    /** @test */
    public function dot_prefixed_directories_are_not_discovered_as_modules(): void
    {
        $this->files->makeDirectory($this->modulesPath . '/.hidden');
        $this->files->makeDirectory($this->modulesPath . '/.git');
        $this->files->makeDirectory($this->modulesPath . '/ValidModule');

        $modules = $this->discovery->discover();

        $this->assertArrayNotHasKey('.hidden', $modules);
        $this->assertArrayNotHasKey('.git', $modules);
        $this->assertArrayHasKey('ValidModule', $modules);
    }

    /** @test */
    public function make_module_command_rejects_path_traversal_names(): void
    {
        $this->artisan('module:make', ['name' => '../EscapeModule'])
            ->assertFailed();
    }

    /** @test */
    public function make_module_command_rejects_names_with_slashes(): void
    {
        $this->artisan('module:make', ['name' => 'foo/bar'])
            ->assertFailed();
    }

    /** @test */
    public function make_module_command_rejects_empty_name(): void
    {
        $this->artisan('module:make', ['name' => ''])
            ->assertFailed();
    }

    /** @test */
    public function valid_module_names_pass_validation(): void
    {
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
                $this->discovery->isValidModuleName($name),
                "Expected '{$name}' to be accepted as a valid module name"
            );
        }
    }

    /** @test */
    public function discovery_only_reads_within_configured_modules_path(): void
    {
        // Create a module in a completely separate temp directory
        $outsideDir = sys_get_temp_dir() . '/outside_modules_' . uniqid();
        $this->files->makeDirectory($outsideDir . '/EscapedModule', 0755, true);

        $modules = $this->discovery->discover();

        $this->assertArrayNotHasKey('EscapedModule', $modules);

        $this->files->deleteDirectory($outsideDir);
    }
}
