<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Support\ModuleName;
use Throwable;

/**
 * Persists discovered module metadata so production boots skip filesystem scans.
 */
final class ModuleCache
{
    public const VERSION = 1;

    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
    ) {}

    public function exists(): bool
    {
        return $this->files->isFile($this->configuration->cachePath());
    }

    /**
     * @return array<string, Module>|null
     */
    public function get(): ?array
    {
        if (! $this->exists()) {
            return null;
        }

        try {
            /** @var mixed $payload */
            $payload = $this->files->getRequire($this->configuration->cachePath());
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload) || ($payload['version'] ?? null) !== self::VERSION) {
            return null;
        }

        if (($payload['modules_path'] ?? null) !== $this->configuration->modulesPathRelative()) {
            return null;
        }

        $modules = [];

        foreach ($payload['modules'] ?? [] as $name => $data) {
            if (! is_array($data)) {
                continue;
            }

            try {
                $parsed = ModuleName::parse((string) ($data['name'] ?? $name));
            } catch (InvalidModuleException) {
                continue;
            }

            $path = $this->paths->path($parsed->studly());

            if (! $this->files->isDirectory($path)) {
                continue;
            }

            $data['name'] = $parsed->studly();
            $data['path'] = $path;
            $data['cached'] = true;
            $modules[$parsed->studly()] = Module::fromArray($data);
        }

        return $modules;
    }

    /**
     * @param  array<string, Module>  $modules
     */
    public function put(array $modules): string
    {
        $path = $this->configuration->cachePath();
        $this->files->ensureDirectoryExists(dirname($path));

        $serialized = [];

        foreach ($modules as $name => $module) {
            $data = $module->markCached()->toArray();
            $data['path'] = $this->paths->relativePath($module->path);
            $serialized[$name] = $data;
        }

        $payload = [
            'version' => self::VERSION,
            'generated_at' => date('c'),
            'modules_path' => $this->configuration->modulesPathRelative(),
            'modules' => $serialized,
        ];

        $export = var_export($payload, true);
        $this->files->put($path, "<?php\n\nreturn {$export};\n");

        return $path;
    }

    public function forget(): bool
    {
        $path = $this->configuration->cachePath();

        if ($this->files->isFile($path)) {
            return $this->files->delete($path);
        }

        return false;
    }
}
