<?php

namespace NgarakDev\Modularization\Tests;

use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModularizationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ModularizationServiceProvider::class];
    }

    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }

    /** @test */
    public function test_can_load_service_provider()
    {
        $service = $this->app->make(ModularizationService::class);

        $this->assertInstanceOf(ModularizationService::class, $service);
    }
}
