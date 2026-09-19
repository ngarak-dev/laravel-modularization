<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleCacheInterface;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\Support\Module;

/**
 * Handles caching of module metadata for performance.
 */
final class ModuleCache implements ModuleCacheInterface
{
    private const CACHE_FILE = 'modules.php';

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {
    }

    public function exists(): bool
    {
        return $this->files->exists($this->getCachePath());
    }

    /**
     * @return Collection<string, ModuleInterface>|null
     */
    public function get(): ?Collection
    {
        if (!$this->exists()) {
            return null;
        }

        $cached = require $this->getCachePath();

        if (!is_array($cached)) {
            return null;
        }

        $modules = collect();

        foreach ($cached as $name => $data) {
            if (!is_array($data)) {
                continue;
            }

            $modules->put($name, Module::fromArray($data));
        }

        return $modules;
    }

    /**
     * @param Collection<string, ModuleInterface> $modules
     */
    public function put(Collection $modules): void
    {
        $cachePath = $this->getCachePath();
        $cacheDir = dirname($cachePath);

        if (!$this->files->isDirectory($cacheDir)) {
            $this->files->makeDirectory($cacheDir, 0755, true);
        }

        $data = $modules->mapWithKeys(function (ModuleInterface $module) {
            return [$module->getName() => $module->toArray()];
        })->all();

        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        $this->files->put($cachePath, $content);
    }

    public function clear(): void
    {
        if ($this->exists()) {
            $this->files->delete($this->getCachePath());
        }
    }

    public function getCachePath(): string
    {
        return $this->app->bootstrapPath('cache/' . self::CACHE_FILE);
    }
}
