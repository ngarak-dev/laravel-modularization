<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;
use NgarakDev\Modularization\Support\ModuleCache;
use Orchestra\Testbench\TestCase;

class ModuleCacheTest extends TestCase
{
    protected Filesystem $files;
    protected string $bootstrapPath;
    protected ModuleCache $cache;

    protected function getPackageProviders($app): array
    {
        return [ModularizationServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->bootstrapPath = sys_get_temp_dir() . '/module_cache_test_' . uniqid();
        $this->files->makeDirectory($this->bootstrapPath . '/cache', 0755, true);

        $this->cache = new ModuleCache($this->files, $this->bootstrapPath);
    }

    protected function tearDown(): void
    {
        if ($this->files->isDirectory($this->bootstrapPath)) {
            $this->files->deleteDirectory($this->bootstrapPath);
        }

        parent::tearDown();
    }

    private function makeModule(string $name, bool $enabled = true): Module
    {
        return new Module(
            name: $name,
            path: '/modules/' . $name,
            enabled: $enabled,
            namespace: 'Modules\\' . $name,
            version: '1.0.0',
            description: 'Test module',
            requires: [],
        );
    }

    /** @test */
    public function it_reports_not_cached_when_no_file_exists(): void
    {
        $this->assertFalse($this->cache->isCached());
    }

    /** @test */
    public function it_writes_and_reads_modules_from_cache(): void
    {
        $modules = [
            'Alpha' => $this->makeModule('Alpha'),
            'Beta'  => $this->makeModule('Beta', false),
        ];

        $this->cache->write($modules);

        $this->assertTrue($this->cache->isCached());

        $loaded = $this->cache->load();

        $this->assertIsArray($loaded);
        $this->assertCount(2, $loaded);
        $this->assertArrayHasKey('Alpha', $loaded);
        $this->assertArrayHasKey('Beta', $loaded);
        $this->assertTrue($loaded['Alpha']->isEnabled());
        $this->assertFalse($loaded['Beta']->isEnabled());
    }

    /** @test */
    public function it_preserves_all_module_fields_through_cache(): void
    {
        $module = new Module(
            name: 'Orders',
            path: '/modules/Orders',
            enabled: true,
            namespace: 'Modules\\Orders',
            version: '2.3.1',
            description: 'Order management module',
            requires: ['Users', 'Payments'],
        );

        $this->cache->write(['Orders' => $module]);
        $loaded = $this->cache->load();

        $result = $loaded['Orders'];
        $this->assertSame('Orders', $result->name);
        $this->assertSame('/modules/Orders', $result->path);
        $this->assertSame('Modules\\Orders', $result->namespace);
        $this->assertSame('2.3.1', $result->version);
        $this->assertSame('Order management module', $result->description);
        $this->assertSame(['Users', 'Payments'], $result->requires);
    }

    /** @test */
    public function it_clears_the_cache_file(): void
    {
        $this->cache->write(['Alpha' => $this->makeModule('Alpha')]);
        $this->assertTrue($this->cache->isCached());

        $result = $this->cache->clear();

        $this->assertTrue($result);
        $this->assertFalse($this->cache->isCached());
    }

    /** @test */
    public function it_returns_false_when_clearing_non_existent_cache(): void
    {
        $this->assertFalse($this->cache->clear());
    }

    /** @test */
    public function it_returns_null_when_cache_does_not_exist(): void
    {
        $this->assertNull($this->cache->load());
    }

    /** @test */
    public function it_returns_null_for_corrupt_cache(): void
    {
        $cachePath = $this->cache->getCachePath();
        $cacheDir = dirname($cachePath);

        if (! $this->files->isDirectory($cacheDir)) {
            $this->files->makeDirectory($cacheDir, 0755, true);
        }

        $this->files->put($cachePath, '<?php return "not an array";');

        $loaded = $this->cache->load();
        $this->assertNull($loaded);
    }

    /** @test */
    public function it_handles_empty_module_list(): void
    {
        $this->cache->write([]);
        $loaded = $this->cache->load();

        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }

    /** @test */
    public function it_creates_cache_directory_if_missing(): void
    {
        $this->files->deleteDirectory($this->bootstrapPath . '/cache');
        $this->assertFalse($this->files->isDirectory($this->bootstrapPath . '/cache'));

        $this->cache->write(['Alpha' => $this->makeModule('Alpha')]);

        $this->assertTrue($this->files->isDirectory($this->bootstrapPath . '/cache'));
        $this->assertTrue($this->cache->isCached());
    }

    /** @test */
    public function module_cache_command_creates_cache_file(): void
    {
        $modulesPath = sys_get_temp_dir() . '/test_modules_cache_' . uniqid();
        $this->files->makeDirectory($modulesPath . '/Billing', 0755, true);

        config(['modularization.modules_path' => $modulesPath]);

        $this->artisan('module:cache')->assertSuccessful();

        $this->files->deleteDirectory($modulesPath);
    }

    /** @test */
    public function module_clear_command_removes_cache_file(): void
    {
        $this->cache->write(['Alpha' => $this->makeModule('Alpha')]);
        $this->assertTrue($this->cache->isCached());

        $this->artisan('module:clear')->assertSuccessful();
    }
}
