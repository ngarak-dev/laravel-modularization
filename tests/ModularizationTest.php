<?php

namespace NgarakDev\Modularization\Tests;

use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use Orchestra\Testbench\TestCase;

class ModularizationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    public function test_it_binds_the_modularization_service(): void
    {
        $this->assertInstanceOf(
            ModularizationService::class,
            $this->app->make('modularization')
        );
    }
}
