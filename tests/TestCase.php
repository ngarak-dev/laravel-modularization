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

    protected function getPackageProviders($app): array
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('modularization.namespace', 'Modules');
        $app['config']->set('modularization.modules_path', 'modules');
        $app['config']->set('modularization.update_composer', false);
        $app['config']->set('modularization.dependencies.fail_on_missing', false);
        $app['config']->set('modularization.cache.enabled', false);
        $app['config']->set('modularization.cache.path', 'bootstrap/cache/modules.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = base_path('modules');
        $this->files->ensureDirectoryExists($this->modulesPath, 0755);
        $this->registerModulesAutoloader();
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        $cache = base_path('bootstrap/cache/modules.php');
        if ($this->files->exists($cache)) {
            $this->files->delete($cache);
        }

        parent::tearDown();
    }

    protected function registerModulesAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (! str_starts_with($class, 'Modules\\')) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen('Modules\\')));
            $path = base_path('modules/'.$relative.'.php');

            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    protected function modulePath(string $name, string $path = ''): string
    {
        return $path === ''
            ? $this->modulesPath.'/'.$name
            : $this->modulesPath.'/'.$name.'/'.$path;
    }
}
