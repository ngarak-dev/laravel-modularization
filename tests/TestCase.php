<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected Filesystem $files;
    protected string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->modulesPath = base_path('modules');

        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('modularization.namespace', 'Modules');
        $app['config']->set('modularization.modules_path', 'modules');
    }

    /**
     * Create a test module directory.
     */
    protected function createTestModule(string $name, bool $enabled = true): string
    {
        $path = $this->modulesPath . '/' . $name;

        $this->files->makeDirectory($path, 0755, true);
        $this->files->makeDirectory($path . '/Providers', 0755, true);

        $manifest = [
            'name' => $name,
            'description' => 'Test module',
            'version' => '1.0.0',
            'enabled' => $enabled,
        ];

        $this->files->put($path . '/module.json', json_encode($manifest, JSON_PRETTY_PRINT));

        if (!$enabled) {
            $this->files->put($path . '/.disabled', '');
        }

        return $path;
    }
}
