<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\InvalidModuleNameException;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\Tests\TestCase;

final class ModulePathResolverTest extends TestCase
{
    public function test_module_names_cannot_escape_the_modules_directory(): void
    {
        $resolver = $this->app->make(ModulePathResolver::class);

        $this->expectException(InvalidModuleException::class);
        $resolver->path('../outside');
    }

    public function test_child_paths_are_resolved_inside_the_module(): void
    {
        $resolver = $this->app->make(ModulePathResolver::class);

        self::assertSame(
            $this->modulePath('Orders', 'Resources/views'),
            $resolver->child('Orders', 'Resources/views')
        );
    }

    public function test_child_paths_reject_parent_segments(): void
    {
        $resolver = $this->app->make(ModulePathResolver::class);

        $this->expectException(InvalidModuleNameException::class);
        $resolver->child('Orders', '../outside');
    }

    public function test_windows_and_unix_separators_are_normalized(): void
    {
        $resolver = $this->app->make(ModulePathResolver::class);

        $joined = $resolver->join('/var/www/modules', 'Orders\\Http/Controllers');
        $normalized = $resolver->normalizePath('C:\\app\\modules\\Orders\\..\\Users');

        $this->assertStringContainsString('Orders', $joined);
        $this->assertStringContainsString('Http', $joined);
        $this->assertStringContainsString('Controllers', $joined);
        $this->assertSame('C:/app/modules/Users', $normalized);
    }

    public function test_absolute_unix_and_windows_modules_paths_are_accepted(): void
    {
        config(['modularization.modules_path' => '/tmp/custom-modules']);
        $resolver = $this->app->make(ModulePathResolver::class);

        $this->assertSame('/tmp/custom-modules', $resolver->modulesPath());
    }
}
