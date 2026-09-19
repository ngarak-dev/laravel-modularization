<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\ModuleRequirement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ModuleRequirementTest extends TestCase
{
    #[Test]
    public function it_parses_plain_names_and_constraints(): void
    {
        $this->assertSame('Users', ModuleRequirement::parse('Users')->name);
        $this->assertSame('*', ModuleRequirement::parse('Users')->constraint);
        $this->assertSame('^2.0', ModuleRequirement::parse('Users:^2.0')->constraint);
        $this->assertSame('~1.4', ModuleRequirement::parse(['Users' => '~1.4'])->constraint);
        $this->assertSame('>=1.0', ModuleRequirement::parse(['name' => 'Users', 'version' => '>=1.0'])->constraint);
    }

    #[Test]
    public function caret_constraints_match_semver(): void
    {
        $requirement = new ModuleRequirement('Users', '^1.2.3');

        $this->assertTrue($requirement->isSatisfiedBy('1.2.3'));
        $this->assertTrue($requirement->isSatisfiedBy('1.9.0'));
        $this->assertFalse($requirement->isSatisfiedBy('2.0.0'));
        $this->assertFalse($requirement->isSatisfiedBy('1.2.2'));
    }

    #[Test]
    public function tilde_and_comparison_constraints_match(): void
    {
        $tilde = new ModuleRequirement('Users', '~1.4.0');
        $gte = new ModuleRequirement('Users', '>=2.0');

        $this->assertTrue($tilde->isSatisfiedBy('1.4.5'));
        $this->assertFalse($tilde->isSatisfiedBy('1.5.0'));
        $this->assertTrue($gte->isSatisfiedBy('2.1.0'));
        $this->assertFalse($gte->isSatisfiedBy('1.9.9'));
    }
}
