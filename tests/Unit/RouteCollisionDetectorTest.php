<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\Exceptions\ModuleException;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\RouteCollisionDetector;
use NgarakDev\Modularization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteCollisionDetectorTest extends TestCase
{
    #[Test]
    public function it_detects_duplicate_route_names_across_modules(): void
    {
        $this->writeModuleFixture('Billing');
        $this->writeModuleFixture('Orders');
        $this->files->put($this->modulePath('Billing', 'Routes/web.php'), "<?php\nRoute::get('/pay', fn () => 'ok')->name('checkout');\n");
        $this->files->put($this->modulePath('Orders', 'Routes/web.php'), "<?php\nRoute::get('/order', fn () => 'ok')->name('checkout');\n");

        $manager = $this->app->make(ModuleManager::class);
        $collisions = $this->app->make(RouteCollisionDetector::class)->detect($manager->all());

        $this->assertArrayHasKey('checkout', $collisions);
        $this->assertContains('Billing', $collisions['checkout']);
        $this->assertContains('Orders', $collisions['checkout']);
    }

    #[Test]
    public function it_throws_when_fail_on_collision_is_enabled(): void
    {
        config(['modularization.routes.fail_on_collision' => true]);
        $this->writeModuleFixture('Billing');
        $this->writeModuleFixture('Orders');
        $this->files->put($this->modulePath('Billing', 'Routes/web.php'), "<?php\nRoute::get('/pay', fn () => 'ok')->name('checkout');\n");
        $this->files->put($this->modulePath('Orders', 'Routes/web.php'), "<?php\nRoute::get('/order', fn () => 'ok')->name('checkout');\n");

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('checkout');
        $this->app->make(RouteCollisionDetector::class)->assertNone($this->app->make(ModuleManager::class)->all());
    }
}
