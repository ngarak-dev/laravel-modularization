<?php

namespace NgarakDev\Modularization\Tests;

use PHPUnit\Framework\TestCase;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModularizationTest extends TestCase
{
    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }

    // Test loading service provider
    /** @test */
    public function test_can_load_service_provider()
    {
        $provider = new ModularizationServiceProvider(app());
        $this->assertInstanceOf(
            \NgarakDev\Modularization\ModularizationService::class,
            app(config('modularization.service_class', \NgarakDev\Modularization\ModularizationService::class))
        );
    }
}
