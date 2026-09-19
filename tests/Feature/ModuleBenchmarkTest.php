<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use NgarakDev\Modularization\ModuleDiscovery;
use NgarakDev\Modularization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModuleBenchmarkTest extends TestCase
{
    #[Test]
    public function discovery_handles_fifty_modules(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $this->writeModuleFixture('Module'.$i, [
                'requires' => $i > 1 ? ['Module'.($i - 1)] : [],
            ]);
        }

        $started = hrtime(true);
        $modules = $this->app->make(ModuleDiscovery::class)->scan();
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;

        $this->assertCount(50, $modules);
        $this->assertLessThan(2500, $elapsedMs, "Discovering 50 modules took {$elapsedMs}ms");
    }
}
