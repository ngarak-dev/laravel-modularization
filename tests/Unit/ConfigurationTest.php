<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Config\Repository;
use InvalidArgumentException;
use NgarakDev\Modularization\ModuleConfiguration;
use NgarakDev\Modularization\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_it_exposes_defaults(): void
    {
        $config = $this->app->make(ModuleConfiguration::class);

        $this->assertSame('modules', $config->modulesPathRelative());
        $this->assertSame('Modules', $config->namespace());
        $this->assertTrue($config->autoRegisterRoutes());
        $this->assertTrue($config->scaffoldRepositories());
        $this->assertContains('Http/Controllers', $config->directories());
        $this->assertContains('Jobs', $config->directories());
        $this->assertContains('Policies', $config->directories());
        $this->assertFalse($config->failOnMissingDependencies());
    }

    public function test_it_rejects_unsafe_cache_path(): void
    {
        $repository = new Repository([
            'modularization' => [
                'cache' => [
                    'path' => '../modules.php',
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new ModuleConfiguration($repository))->cachePath();
    }

    public function test_it_rejects_unsafe_modules_path(): void
    {
        $repository = new Repository([
            'modularization' => [
                'modules_path' => '../outside',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new ModuleConfiguration($repository))->modulesPathRelative();
    }
}
