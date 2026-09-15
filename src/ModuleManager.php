<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\CircularModuleDependencyException;
use NgarakDev\Modularization\Exceptions\ModuleDependencyException;

final class ModuleManager
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $modules = null;

    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly ModuleStatusManager $statuses,
        private readonly Filesystem $files,
        private readonly string $cachePath,
    ) {
    }

    /** @return array<string, array<string, mixed>> */
    public function all(bool $cached = false): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        if ($cached && $this->files->exists($this->cachePath)) {
            $contents = include $this->cachePath;
            if (is_array($contents)) {
                return $this->modules = $contents;
            }
        }

        return $this->modules = $this->discovery->discover();
    }

    public function refresh(): void
    {
        $this->modules = $this->discovery->discover();
    }

    public function cache(): void
    {
        $modules = $this->discovery->discover();
        $this->files->ensureDirectoryExists(dirname($this->cachePath));
        $this->files->put($this->cachePath, "<?php\n\nreturn " . var_export($modules, true) . ";\n");
        $this->modules = $modules;
    }

    public function clearCache(): void
    {
        $this->files->delete($this->cachePath);
        $this->modules = null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }

    public function isEnabled(string $name): bool
    {
        return $this->has($name) && $this->statuses->isEnabled($name);
    }

    public function enable(string $name): bool
    {
        $result = $this->statuses->enable($name);
        $this->clearCache();
        $this->refresh();

        return $result;
    }

    public function disable(string $name): bool
    {
        $result = $this->statuses->disable($name);
        $this->clearCache();
        $this->refresh();

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function loadable(): array
    {
        $modules = $this->all((bool) config('modularization.discovery.use_cache', false));
        $ordered = [];
        $visiting = [];
        $visited = [];

        foreach ($modules as $name => $module) {
            if ($module['enabled']) {
                $this->visit($name, $modules, $ordered, $visiting, $visited);
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, array<string, mixed>> $modules
     * @param list<array<string, mixed>> $ordered
     * @param array<string, bool> $visiting
     * @param array<string, bool> $visited
     */
    private function visit(string $name, array $modules, array &$ordered, array &$visiting, array &$visited): void
    {
        if (isset($visited[$name])) {
            return;
        }
        if (isset($visiting[$name])) {
            throw new CircularModuleDependencyException("Circular module dependency detected at [{$name}].");
        }
        if (! isset($modules[$name])) {
            throw new ModuleDependencyException("Module [{$name}] is required but was not discovered.");
        }

        $visiting[$name] = true;
        foreach ($modules[$name]['requires'] as $dependency) {
            $dependency = (string) $dependency;
            if (! isset($modules[$dependency])) {
                throw new ModuleDependencyException(
                    "Module [{$name}] requires missing module [{$dependency}]. Add it or remove the dependency from module.json."
                );
            }
            if (! $modules[$dependency]['enabled']) {
                throw new ModuleDependencyException(
                    "Module [{$name}] requires disabled module [{$dependency}]. Enable the dependency before booting."
                );
            }
            $this->visit($dependency, $modules, $ordered, $visiting, $visited);
        }

        unset($visiting[$name]);
        $visited[$name] = true;
        $ordered[] = $modules[$name];
    }
}
