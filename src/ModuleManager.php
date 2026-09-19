<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Support\Collection;
use NgarakDev\Modularization\Contracts\ModuleRepositoryInterface;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Support\ModuleName;

/**
 * Primary package API for discovering, inspecting, and managing modules.
 */
final class ModuleManager implements ModuleRepositoryInterface
{
    /**
     * @var array<string, Module>|null
     */
    private ?array $modules = null;

    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly ModuleCache $cache,
        private readonly ModuleStatusManager $status,
        private readonly ModulePathResolver $paths,
        private readonly ModuleRegistrar $registrar,
        private readonly ModuleConfiguration $configuration,
        private readonly DependencyResolver $dependencies,
        private readonly RouteCollisionDetector $collisions,
        private readonly ModuleRegistry $registry,
    ) {}

    /**
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules ??= $this->discovery->discover($this->configuration->cacheEnabled());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getModules(): array
    {
        $result = [];

        foreach ($this->all() as $name => $module) {
            $result[$name] = [
                'name' => $module->name,
                'path' => $module->path,
                'enabled' => $module->enabled,
                'version' => $module->version,
                'description' => $module->description,
                'requires' => $module->requires,
                'valid' => $module->valid,
                'cached' => $module->cached,
            ];
        }

        return $result;
    }

    public function get(string $name): Module
    {
        $studly = ModuleName::parse($name)->studly();
        $modules = $this->all();

        if (! isset($modules[$studly])) {
            $this->refreshFromFilesystem($studly);
            $modules = $this->all();
        }

        if (! isset($modules[$studly])) {
            throw ModuleNotFoundException::make($studly, $this->paths->modulesPath());
        }

        return $modules[$studly];
    }

    public function has(string $name): bool
    {
        return $this->hasModule($name);
    }

    public function find(string $name): ?Module
    {
        if (! $this->hasModule($name)) {
            return null;
        }

        return $this->get($name);
    }

    public function isDisabled(string $name): bool
    {
        return $this->hasModule($name) && ! $this->isEnabled($name);
    }

    /**
     * @return Collection<string, Module>
     */
    public function getDependents(string $name): Collection
    {
        try {
            $studly = ModuleName::parse($name)->studly();
        } catch (\Throwable) {
            return collect();
        }

        $dependents = [];

        foreach ($this->all() as $module) {
            if (in_array($studly, $module->requires, true)) {
                $dependents[$module->name] = $module;
            }
        }

        return collect($dependents);
    }

    /**
     * @return array<string, Module>
     */
    public function enabled(): array
    {
        return array_filter($this->all(), static fn (Module $module): bool => $module->enabled);
    }

    /**
     * @return array<string, Module>
     */
    public function disabled(): array
    {
        return array_filter($this->all(), static fn (Module $module): bool => ! $module->enabled);
    }

    public function findOrFail(string $name): Module
    {
        return $this->get($name);
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function exportRegistry(?string $path = null): string
    {
        return $this->registry->write($this->all(), $path);
    }

    public function hasModule(string $name): bool
    {
        try {
            $studly = ModuleName::parse($name)->studly();
        } catch (\Throwable) {
            return false;
        }

        if (isset($this->all()[$studly])) {
            return true;
        }

        $this->refreshFromFilesystem($studly);

        return isset($this->all()[$studly]);
    }

    private function refreshFromFilesystem(string $studly): void
    {
        $directory = $this->paths->path($studly);

        if (! is_dir($directory)) {
            return;
        }

        $this->refresh();
        $this->modules = $this->discovery->discover(false);
    }

    public function isEnabled(string $name): bool
    {
        if (! $this->hasModule($name)) {
            return false;
        }

        return $this->get($name)->enabled;
    }

    public function enable(string $name): bool
    {
        if (! $this->hasModule($name)) {
            return false;
        }

        $this->status->enable($name);
        $this->refresh();

        return true;
    }

    public function disable(string $name): bool
    {
        if (! $this->hasModule($name)) {
            return false;
        }

        $this->status->disable($name);
        $this->refresh();

        return true;
    }

    public function path(?string $module = null, string $path = ''): string
    {
        return $this->paths->path($module, $path);
    }

    /**
     * @return array<string, Module>
     */
    public function discover(bool $useCache = false): array
    {
        $this->modules = $this->discovery->discover($useCache);

        return $this->modules;
    }

    public function cache(): string
    {
        $modules = $this->discovery->scan();
        $path = $this->cache->put($modules);
        $this->registry->write($modules);
        $this->modules = $modules;

        return $path;
    }

    public function refresh(): void
    {
        $this->modules = null;
    }

    public function clearCache(): bool
    {
        $cleared = $this->cache->forget();
        $this->refresh();

        return $cleared;
    }

    public function isCached(): bool
    {
        return $this->cache->exists();
    }

    public function boot(): void
    {
        $modules = $this->all();

        if ($this->configuration->failOnRouteCollision()) {
            $this->collisions->assertNone($modules);
        }

        $this->registrar->registerAll($modules);
    }

    /**
     * @return array<string, Module>
     */
    public function inLoadOrder(): array
    {
        return $this->dependencies->sort(
            array_filter($this->all(), fn (Module $module): bool => $module->enabled && $module->valid),
            $this->configuration->failOnMissingDependencies()
        );
    }

    /**
     * @return array<int, string>
     */
    public function missingDependencies(string $name): array
    {
        return $this->dependencies->missingDependencies($this->get($name), $this->all());
    }

    public function getOrderedByDependencies(): array
    {
        return $this->inLoadOrder();
    }
}
