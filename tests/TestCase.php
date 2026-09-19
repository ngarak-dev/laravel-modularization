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
        $app['config']->set('modularization.dump_autoload', false);
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

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function writeModuleFixture(string $name, array $manifest = [], bool $withProvider = true): string
    {
        $path = $this->modulePath($name);
        $this->files->ensureDirectoryExists($path.'/Providers', 0755);
        $this->files->ensureDirectoryExists($path.'/Routes', 0755);

        $payload = array_merge([
            'name' => $name,
            'namespace' => 'Modules\\'.$name,
            'provider' => 'Modules\\'.$name.'\\Providers\\'.$name.'ServiceProvider',
            'version' => '1.0.0',
            'description' => $name.' module',
            'enabled' => true,
            'requires' => [],
        ], $manifest);

        $this->files->put($path.'/module.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        if ($withProvider) {
            $this->files->put($path.'/Providers/'.$name.'ServiceProvider.php', <<<PHP
<?php

namespace Modules\\{$name}\\Providers;

use Illuminate\\Support\\ServiceProvider;

class {$name}ServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {}
}
PHP);
        }

        if ($this->app->bound(\NgarakDev\Modularization\ModuleManager::class)) {
            $this->app->make(\NgarakDev\Modularization\ModuleManager::class)->refresh();
        }

        return $path;
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

        $registry = base_path('bootstrap/cache/modules-registry.json');
        if ($this->files->exists($registry)) {
            $this->files->delete($registry);
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
