<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit\Support;

use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Support\ModuleNameValidator;
use PHPUnit\Framework\TestCase;

class ModuleNameValidatorTest extends TestCase
{
    private ModuleNameValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ModuleNameValidator();
    }

    /** @test */
    public function it_accepts_valid_module_names(): void
    {
        $validNames = [
            'Products',
            'UserProfile',
            'Order',
            'PaymentGateway',
            'API_V1',
            'Module123',
        ];

        foreach ($validNames as $name) {
            $this->assertTrue($this->validator->isValid($name), "Expected '{$name}' to be valid");
        }
    }

    /** @test */
    public function it_rejects_empty_names(): void
    {
        $this->expectException(InvalidModuleException::class);
        $this->expectExceptionMessage('Module name cannot be empty');

        $this->validator->validate('');
    }

    /** @test */
    public function it_rejects_names_starting_with_numbers(): void
    {
        $this->expectException(InvalidModuleException::class);
        $this->expectExceptionMessage('must start with a letter');

        $this->validator->validate('123Module');
    }

    /** @test */
    public function it_rejects_names_with_invalid_characters(): void
    {
        $this->expectException(InvalidModuleException::class);

        $this->validator->validate('My-Module');
    }

    /** @test */
    public function it_rejects_path_traversal_attempts(): void
    {
        $dangerousNames = [
            '../etc/passwd',
            'Module/../../../etc',
            'Module/Submodule',
            'Module\\Submodule',
        ];

        foreach ($dangerousNames as $name) {
            $this->assertFalse($this->validator->isValid($name), "Expected '{$name}' to be invalid");
        }
    }

    /** @test */
    public function it_rejects_reserved_names(): void
    {
        $this->expectException(InvalidModuleException::class);
        $this->expectExceptionMessage('reserved name');

        $this->validator->validate('Modules');
    }

    /** @test */
    public function it_rejects_names_exceeding_max_length(): void
    {
        $longName = str_repeat('A', 65);

        $this->expectException(InvalidModuleException::class);
        $this->expectExceptionMessage('cannot exceed');

        $this->validator->validate($longName);
    }

    /** @test */
    public function it_sanitizes_invalid_names(): void
    {
        $this->assertSame('Module', $this->validator->sanitize(''));
        $this->assertSame('Module123', $this->validator->sanitize('123'));
        $this->assertSame('MyModule', $this->validator->sanitize('My-Module'));
        $this->assertSame('MyModule', $this->validator->sanitize('My Module'));
    }
}
