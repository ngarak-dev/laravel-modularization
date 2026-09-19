<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use NgarakDev\Modularization\ModuleCache;
use NgarakDev\Modularization\ModuleDiscovery;
use NgarakDev\Modularization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModuleCacheTest extends TestCase
{
    #[Test]
    public function it_reports_not_cached_when_no_file_exists(): void
    {
        $this->assertFalse($this->app->make(ModuleCache::class)->isCached());
    }

    #[Test]
    public function it_writes_and_reads_modules_from_cache(): void
    {
        $this->writeModuleFixture('Alpha');
        $this->writeModuleFixture('Beta', ['enabled' => false]);
        $this->files->put($this->modulePath('Beta', '.disabled'), '');

        $discovery = $this->app->make(ModuleDiscovery::class);
        $cache = $this->app->make(ModuleCache::class);
        $cache->write($discovery->scan());

        $this->assertTrue($cache->isCached());

        $loaded = $cache->load();
        $this->assertIsArray($loaded);
        $this->assertArrayHasKey('Alpha', $loaded);
        $this->assertArrayHasKey('Beta', $loaded);
        $this->assertTrue($loaded['Alpha']->isEnabled());
        $this->assertFalse($loaded['Beta']->isEnabled());
    }

    #[Test]
    public function it_preserves_module_fields_through_cache(): void
    {
        $this->writeModuleFixture('Orders', [
            'version' => '2.3.1',
            'description' => 'Order management module',
            'requires' => ['Users', 'Payments'],
        ]);

        $discovery = $this->app->make(ModuleDiscovery::class);
        $cache = $this->app->make(ModuleCache::class);
        $cache->write($discovery->scan());
        $loaded = $cache->load();

        $result = $loaded['Orders'];
        $this->assertSame('Orders', $result->name);
        $this->assertSame('Modules\\Orders', $result->namespace);
        $this->assertSame('2.3.1', $result->version);
        $this->assertSame('Order management module', $result->description);
        $this->assertSame(['Users', 'Payments'], $result->requires);
    }

    #[Test]
    public function it_clears_the_cache_file(): void
    {
        $this->writeModuleFixture('Alpha');
        $cache = $this->app->make(ModuleCache::class);
        $cache->write($this->app->make(ModuleDiscovery::class)->scan());
        $this->assertTrue($cache->isCached());

        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->isCached());
    }

    #[Test]
    public function it_returns_false_when_clearing_non_existent_cache(): void
    {
        $this->assertFalse($this->app->make(ModuleCache::class)->clear());
    }

    #[Test]
    public function it_returns_null_when_cache_does_not_exist(): void
    {
        $this->assertNull($this->app->make(ModuleCache::class)->load());
    }

    #[Test]
    public function it_returns_null_for_corrupt_cache(): void
    {
        $cache = $this->app->make(ModuleCache::class);
        $cachePath = $cache->getCachePath();
        $this->files->ensureDirectoryExists(dirname($cachePath), 0755);
        $this->files->put($cachePath, '<?php return "not an array";');

        $this->assertNull($cache->load());
    }

    #[Test]
    public function it_handles_empty_module_list(): void
    {
        $cache = $this->app->make(ModuleCache::class);
        $cache->write([]);
        $loaded = $cache->load();

        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }

    #[Test]
    public function module_cache_command_creates_cache_file(): void
    {
        $this->writeModuleFixture('Billing');

        $this->artisan('module:cache')->assertSuccessful();
        $this->assertTrue($this->app->make(ModuleCache::class)->isCached());
        $this->assertFileExists(base_path('bootstrap/cache/modules-registry.json'));
    }

    #[Test]
    public function module_clear_command_removes_cache_file(): void
    {
        $this->writeModuleFixture('Alpha');
        $this->artisan('module:cache')->assertSuccessful();
        $this->artisan('module:clear')->assertSuccessful();
        $this->assertFalse($this->app->make(ModuleCache::class)->isCached());
    }
}
