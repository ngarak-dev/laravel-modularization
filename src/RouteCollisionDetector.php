<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\ModuleException;

/**
 * Detects duplicate Laravel route names across enabled modules.
 */
final class RouteCollisionDetector
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModulePathResolver $paths,
    ) {}

    /**
     * @param  array<string, Module>  $modules
     * @return array<string, array<int, string>>
     */
    public function detect(array $modules): array
    {
        $names = [];

        foreach ($modules as $module) {
            if (! $module->enabled || ! $module->valid) {
                continue;
            }

            foreach ($this->routeNames($module) as $routeName) {
                $names[$routeName][] = $module->name;
            }
        }

        return array_filter($names, static fn (array $owners): bool => count(array_unique($owners)) > 1);
    }

    /**
     * @param  array<string, Module>  $modules
     */
    public function assertNone(array $modules): void
    {
        $collisions = $this->detect($modules);

        if ($collisions === []) {
            return;
        }

        $first = array_key_first($collisions);
        $owners = implode(', ', $collisions[$first]);

        throw new ModuleException(
            "Route name [{$first}] is registered by multiple modules: {$owners}."
        );
    }

    /**
     * @return array<int, string>
     */
    private function routeNames(Module $module): array
    {
        $names = [];

        foreach ($module->routes as $relative) {
            if (! is_string($relative) || $relative === '') {
                continue;
            }

            $full = $this->paths->join($module->path, $relative);

            if (! $this->files->isFile($full)) {
                continue;
            }

            $contents = $this->files->get($full);

            if (preg_match_all("/->name\(\s*['\"]([^'\"]+)['\"]\s*\)/", $contents, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
