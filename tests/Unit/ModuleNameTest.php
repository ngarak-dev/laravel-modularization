<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Support\ModuleName;
use PHPUnit\Framework\TestCase;

class ModuleNameTest extends TestCase
{
    public function test_it_parses_studly_names(): void
    {
        $name = ModuleName::parse('user-profile');

        $this->assertSame('UserProfile', $name->studly());
        $this->assertSame('userprofile', $name->lower());
        $this->assertSame('user-profile', $name->kebab());
        $this->assertSame('user_profile', $name->snake());
    }

    public function test_it_rejects_path_traversal(): void
    {
        $this->expectException(InvalidModuleException::class);
        ModuleName::parse('../Etc');
    }

    public function test_it_rejects_separators(): void
    {
        $this->expectException(InvalidModuleException::class);
        ModuleName::parse('Admin/Users');
    }

    public function test_it_parses_nested_class_names(): void
    {
        $name = ModuleName::parseClass('Admin/Widget');

        $this->assertSame('Admin\\Widget', $name->studly());
        $this->assertSame('Admin/Widget', $name->relativePath());
        $this->assertSame('Widget', $name->basename());
    }

    public function test_it_rejects_nested_class_traversal(): void
    {
        $this->expectException(InvalidModuleException::class);
        ModuleName::parseClass('../Secret');
    }
}
