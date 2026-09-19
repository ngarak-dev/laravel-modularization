<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Livewire\Component;
use NgarakDev\Modularization\Contracts\ModuleDiscoveryInterface;
use NgarakDev\Modularization\Events\ModuleDiscovered;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Support\ModuleName;
use ReflectionClass;

/**
 * Discovers modules from the filesystem or a compiled cache.
 */
final class ModuleDiscovery implements ModuleDiscoveryInterface
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModulePathResolver $paths,
        private readonly ModuleManifest $manifest,
        private readonly ModuleCache $cache,
    ) {}

    /**
     * @return array<string, Module>
     */
    public function discover(bool $useCache = true): array
    {
        if ($useCache && $this->cache->exists()) {
            $cached = $this->cache->get();

            if ($cached !== null) {
                return $cached;
            }
        }

        return $this->scan();
    }

    /**
     * @return array<string, Module>
     */
    public function scan(): array
    {
        $modulesPath = $this->paths->modulesPath();

        if (! $this->files->isDirectory($modulesPath)) {
            return [];
        }

        $modules = [];

        foreach ($this->files->directories($modulesPath) as $directory) {
            $name = basename($directory);

            try {
                $parsed = ModuleName::parse($name);
            } catch (InvalidModuleException) {
                continue;
            }

            $modules[$parsed->studly()] = $this->inspect($parsed->studly(), $directory);
        }

        ksort($modules);

        foreach ($modules as $module) {
            if (function_exists('event')) {
                event(new ModuleDiscovered($module));
            }
        }

        return $modules;
    }

    public function isValidModuleName(string $name): bool
    {
        try {
            ModuleName::parse($name);

            return true;
        } catch (InvalidModuleException) {
            return false;
        }
    }

    public function exists(string $name): bool
    {
        try {
            return $this->files->isDirectory($this->paths->path($name));
        } catch (InvalidModuleException) {
            return false;
        }
    }

    public function inspect(string $name, ?string $directory = null): Module
    {
        $directory ??= $this->paths->path($name);
        $meta = $this->manifest->read($directory, $name);
        $disabledFile = $this->paths->join($directory, '.disabled');
        $installingFile = $this->paths->join($directory, '.installing');
        $brokenFile = $this->paths->join($directory, '.broken');
        $enabled = (bool) ($meta['enabled'] ?? true) && ! $this->files->exists($disabledFile);

        $lifecycle = (string) ($meta['lifecycle'] ?? '');
        if ($this->files->exists($installingFile)) {
            $lifecycle = 'installing';
            $enabled = false;
        } elseif ($this->files->exists($brokenFile)) {
            $lifecycle = 'broken';
            $enabled = false;
        } elseif ($lifecycle === '') {
            $lifecycle = $enabled ? 'enabled' : 'disabled';
        }

        $provider = isset($meta['provider']) ? (string) $meta['provider'] : null;
        $providerRelative = $this->paths->firstExistingFile($directory, [
            "Providers/{$name}ServiceProvider.php",
            "providers/{$name}ServiceProvider.php",
        ]);

        if ($providerRelative === null) {
            $provider = null;
        }

        $valid = true;
        $invalidReason = null;

        if ($providerRelative === null) {
            $valid = false;
            $invalidReason = "missing {$name}ServiceProvider.php";
        }

        return new Module(
            name: (string) $meta['name'],
            path: $directory,
            namespace: (string) $meta['namespace'],
            provider: $provider,
            version: (string) $meta['version'],
            description: (string) $meta['description'],
            enabled: $enabled,
            requires: $meta['requires'] ?? [],
            cached: false,
            valid: $valid && $lifecycle !== 'broken',
            invalidReason: $invalidReason ?? ($lifecycle === 'broken' ? 'module marked broken' : null),
            routes: $this->discoverRoutes($directory),
            views: $this->paths->firstExistingDirectory($directory, ['Resources/views', 'resources/views']),
            translations: $this->paths->firstExistingDirectory($directory, ['Resources/lang', 'resources/lang', 'Resources/lang/vendor']),
            migrations: $this->paths->firstExistingDirectory($directory, ['Database/Migrations', 'database/migrations']),
            assets: $this->paths->firstExistingDirectory($directory, ['Resources/assets', 'resources/assets']),
            livewire: $this->discoverLivewire($directory, (string) $meta['namespace'], $name),
            configFiles: $this->discoverConfigFiles($directory),
            manifestPath: isset($meta['manifest_path']) ? (string) $meta['manifest_path'] : null,
            lifecycle: $lifecycle,
            requirementConstraints: is_array($meta['requirement_constraints'] ?? null) ? $meta['requirement_constraints'] : [],
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function discoverRoutes(string $directory): array
    {
        return [
            'web' => $this->paths->firstExistingFile($directory, ['Routes/web.php', 'routes/web.php']),
            'api' => $this->paths->firstExistingFile($directory, ['Routes/api.php', 'routes/api.php']),
            'livewire' => $this->paths->firstExistingFile($directory, ['Routes/livewire.php', 'routes/livewire.php']),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function discoverConfigFiles(string $directory): array
    {
        $configDir = $this->paths->firstExistingDirectory($directory, ['Config', 'config']);

        if ($configDir === null) {
            return [];
        }

        $files = [];

        foreach ($this->files->files($this->paths->join($directory, $configDir)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $configDir.DIRECTORY_SEPARATOR.$file->getFilename();
            }
        }

        return $files;
    }

    /**
     * @return array<int, array{alias: string, class: string}>
     */
    private function discoverLivewire(string $directory, string $namespace, string $moduleName): array
    {
        $livewireDir = $this->paths->firstExistingDirectory($directory, ['Livewire', 'livewire']);

        if ($livewireDir === null) {
            return [];
        }

        $base = $this->paths->join($directory, $livewireDir);
        $components = [];
        $prefix = strtolower($moduleName);

        foreach ($this->files->allFiles($base) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = trim(str_replace($base, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $classPath = str_replace(['/', '\\'], '\\', substr($relative, 0, -4));
            $class = $namespace.'\\Livewire\\'.$classPath;

            if (! class_exists($class) || ! class_exists(Component::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable() || ! $reflection->isSubclassOf(Component::class)) {
                continue;
            }

            $alias = $prefix.'.'.$this->livewireAlias($classPath);

            $components[] = [
                'alias' => $alias,
                'class' => $class,
            ];
        }

        return $components;
    }

    private function livewireAlias(string $classPath): string
    {
        $segments = explode('\\', $classPath);

        return implode('.', array_map(
            static fn (string $segment): string => Str::kebab($segment),
            $segments
        ));
    }
}
