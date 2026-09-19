<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\CircularModuleDependencyException;
use NgarakDev\Modularization\ModuleDiscovery;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\ModuleStatusManager;
use NgarakDev\Modularization\Support\ModulePathResolver;
use Orchestra\Testbench\TestCase;

final class ModuleDiscoveryTest extends TestCase
{
    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modulesPath = base_path('modules');
        app('files')->deleteDirectory($this->modulesPath);
        app('files')->makeDirectory($this->modulesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_manifest_metadata_is_discovered_deterministically(): void
    {
        $files = new Filesystem();
        $files->makeDirectory($this->modulesPath . '/Orders', 0755, true);
        $files->put($this->modulesPath . '/Orders/module.json', json_encode([
            'description' => 'Orders domain',
            'requires' => ['Users'],
        ]));

        $discovery = new ModuleDiscovery($files, new ModulePathResolver($this->modulesPath));

        self::assertSame('Orders', $discovery->discover()['Orders']['name']);
        self::assertSame(['Users'], $discovery->discover()['Orders']['requires']);
    }

    public function test_circular_dependencies_are_rejected(): void
    {
        $files = new Filesystem();
        foreach (['Orders' => ['Users'], 'Users' => ['Orders']] as $name => $requires) {
            $path = $this->modulesPath . '/' . $name;
            $files->makeDirectory($path, 0755, true);
            $files->put($path . '/module.json', json_encode(['requires' => $requires]));
        }

        $manager = new ModuleManager(
            new ModuleDiscovery($files, new ModulePathResolver($this->modulesPath)),
            new ModuleStatusManager($files, new ModulePathResolver($this->modulesPath)),
            $files,
            $this->modulesPath . '/cache.php',
        );

        $this->expectException(CircularModuleDependencyException::class);
        $manager->loadable();
    }
}
