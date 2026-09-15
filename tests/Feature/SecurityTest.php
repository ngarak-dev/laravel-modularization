<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Support\ModuleName;
use NgarakDev\Modularization\Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_module_names_cannot_escape_the_modules_directory(): void
    {
        $this->artisan('module:make', ['name' => '../Evil'])
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist(base_path('Evil'));
        $this->assertDirectoryDoesNotExist($this->modulesPath.'/../Evil');
    }

    public function test_null_bytes_are_rejected(): void
    {
        $this->expectException(InvalidModuleException::class);
        ModuleName::parse("Billing\0.php");
    }

    public function test_migration_path_cannot_escape_the_module(): void
    {
        $this->artisan('module:make', ['name' => 'Safe'])->assertSuccessful();

        $this->artisan('module:make-migration', [
            'name' => 'create_hack_table',
            'module' => 'Safe',
            '--path' => '../../tmp',
        ])->assertExitCode(1);
    }

    public function test_helper_rejects_unsafe_names(): void
    {
        $this->expectException(InvalidModuleException::class);
        module_path('../secret', 'config.php');
    }
}
