<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Exceptions\InvalidModuleNameException;
use NgarakDev\Modularization\Support\ModulePathResolver;
use PHPUnit\Framework\TestCase;

final class ModulePathResolverTest extends TestCase
{
    public function test_module_names_cannot_escape_the_modules_directory(): void
    {
        $resolver = new ModulePathResolver('/var/www/modules');

        $this->expectException(InvalidModuleNameException::class);
        $resolver->module('../outside');
    }

    public function test_child_paths_are_resolved_inside_the_module(): void
    {
        $resolver = new ModulePathResolver('/var/www/modules');

        self::assertSame(
            '/var/www/modules/Orders/Resources/views',
            $resolver->child('Orders', 'Resources/views')
        );
    }
}
