<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use NgarakDev\Modularization\DependencyResolver;
use NgarakDev\Modularization\Exceptions\CircularModuleDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;
use NgarakDev\Modularization\Facades\Modularization;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\ModuleCache;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Tests\TestCase;

class ModuleLifecycleTest extends TestCase
{
    public function test_it_creates_discovers_and_lists_a_module(): void
    {
        $this->artisan('module:make', ['name' => 'Billing'])->assertSuccessful();

        $this->assertTrue($this->files->isDirectory($this->modulePath('Billing')));
        $this->assertTrue($this->files->isFile($this->modulePath('Billing', 'module.json')));
        $this->assertTrue($this->files->isFile($this->modulePath('Billing', 'Providers/BillingServiceProvider.php')));

        $manager = $this->app->make(ModuleManager::class);
        $this->assertTrue($manager->hasModule('Billing'));
        $this->assertTrue($manager->isEnabled('Billing'));

        $this->artisan('module:list')
            ->expectsOutputToContain('Billing')
            ->assertSuccessful();
    }

    public function test_it_persists_enable_and_disable_state(): void
    {
        $this->artisan('make:module', ['name' => 'Catalog'])->assertSuccessful();

        $this->artisan('module:toggle', ['name' => 'Catalog', '--disable' => true])
            ->expectsOutput('Module [Catalog] has been disabled.')
            ->assertSuccessful();

        $this->assertTrue($this->files->exists($this->modulePath('Catalog', '.disabled')));
        $this->assertFalse(Modularization::isEnabled('Catalog'));

        $this->artisan('module:toggle', ['name' => 'Catalog', '--enable' => true])
            ->expectsOutput('Module [Catalog] has been enabled.')
            ->assertSuccessful();

        $this->assertFalse($this->files->exists($this->modulePath('Catalog', '.disabled')));
        $this->assertTrue(Modularization::isEnabled('Catalog'));
    }

    public function test_it_caches_and_clears_module_metadata(): void
    {
        $this->artisan('module:make', ['name' => 'Orders'])->assertSuccessful();

        $this->artisan('module:cache')->assertSuccessful();
        $this->assertTrue($this->files->exists(base_path('bootstrap/cache/modules.php')));

        $this->artisan('module:clear')->assertSuccessful();
        $this->assertFalse($this->files->exists(base_path('bootstrap/cache/modules.php')));
    }

    public function test_it_loads_modules_in_dependency_order(): void
    {
        $this->artisan('module:make', [
            'name' => 'Orders',
            '--requires' => ['Users'],
        ])->assertSuccessful();

        $this->artisan('module:make', ['name' => 'Users'])->assertSuccessful();

        $manager = $this->app->make(ModuleManager::class);
        $manager->refresh();
        $order = array_keys($manager->inLoadOrder());

        $this->assertSame(['Users', 'Orders'], array_values(array_intersect($order, ['Users', 'Orders'])));
    }

    public function test_it_detects_missing_dependencies(): void
    {
        $this->artisan('module:make', [
            'name' => 'Invoices',
            '--requires' => ['Payments'],
        ])->assertSuccessful();

        $manager = $this->app->make(ModuleManager::class);
        $this->assertSame(['Payments'], $manager->missingDependencies('Invoices'));
    }

    public function test_it_detects_circular_dependencies(): void
    {
        $a = new Module('A', '/tmp/A', 'Modules\\A', null, '1.0.0', '', true, ['B']);
        $b = new Module('B', '/tmp/B', 'Modules\\B', null, '1.0.0', '', true, ['A']);

        $this->expectException(CircularModuleDependencyException::class);
        (new DependencyResolver)->sort(['A' => $a, 'B' => $b]);
    }

    public function test_it_throws_when_required_module_is_missing(): void
    {
        $module = new Module('Orders', '/tmp/Orders', 'Modules\\Orders', null, '1.0.0', '', true, ['Users']);

        $this->expectException(ModuleDependencyException::class);
        (new DependencyResolver)->sort(['Orders' => $module], true);
    }

    public function test_helper_resolves_module_paths(): void
    {
        $this->artisan('module:make', ['name' => 'Inventory'])->assertSuccessful();

        $this->assertSame(
            $this->modulePath('Inventory', 'Http/Controllers'),
            module_path('Inventory', 'Http/Controllers')
        );
    }

    public function test_disabled_modules_are_not_registered(): void
    {
        $this->artisan('module:make', ['name' => 'Reports'])->assertSuccessful();
        $this->artisan('module:toggle', ['name' => 'Reports', '--disable' => true])->assertSuccessful();

        $this->reloadApplication();

        $this->assertFalse(
            $this->app->getProvider('Modules\\Reports\\Providers\\ReportsServiceProvider') !== null
            && $this->app->make(ModuleManager::class)->isEnabled('Reports')
        );
        $this->assertFalse($this->app->make(ModuleManager::class)->isEnabled('Reports'));
    }

    public function test_cache_ignores_paths_outside_the_modules_directory(): void
    {
        $this->artisan('module:make', ['name' => 'Billing'])->assertSuccessful();
        $this->app['config']->set('modularization.cache.enabled', true);

        $manager = $this->app->make(ModuleManager::class);
        $manager->cache();

        $cachePath = base_path('bootstrap/cache/modules.php');
        $payload = require $cachePath;
        $payload['modules']['Billing']['path'] = '../secret';
        $payload['modules']['Evil'] = [
            'name' => '../Hack',
            'path' => '../secret',
            'namespace' => 'Modules\\Hack',
            'provider' => null,
            'version' => '1.0.0',
            'description' => '',
            'enabled' => true,
            'requires' => [],
            'valid' => true,
        ];

        $export = var_export($payload, true);
        $this->files->put($cachePath, "<?php\n\nreturn {$export};\n");

        $manager->refresh();
        $cached = $this->app->make(ModuleCache::class)->get();

        $this->assertNotNull($cached);
        $this->assertArrayHasKey('Billing', $cached);
        $this->assertSame($this->modulePath('Billing'), $cached['Billing']->path);
        $this->assertArrayNotHasKey('Evil', $cached);
        $this->assertArrayNotHasKey('../Hack', $cached);
    }

    public function test_cached_modules_are_marked_cached(): void
    {
        $this->artisan('module:make', ['name' => 'Warehouse'])->assertSuccessful();
        $this->app['config']->set('modularization.cache.enabled', true);

        $manager = $this->app->make(ModuleManager::class);
        $manager->cache();
        $manager->refresh();

        $this->assertTrue($manager->get('Warehouse')->cached);
    }

    public function test_cached_metadata_is_used_when_cache_is_enabled(): void
    {
        $this->artisan('module:make', ['name' => 'Shipping'])->assertSuccessful();
        $this->app['config']->set('modularization.cache.enabled', true);

        $manager = $this->app->make(ModuleManager::class);
        $manager->cache();
        $this->files->deleteDirectory($this->modulePath('Shipping'));
        $manager->refresh();

        $this->assertFalse($manager->hasModule('Shipping'));
    }
}
