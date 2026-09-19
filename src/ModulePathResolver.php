<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\InvalidModuleNameException;
use NgarakDev\Modularization\Support\ModuleName;

final class ModulePathResolver
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
    ) {}

    public function modulesPath(): string
    {
        return $this->configuration->modulesPath();
    }

    public function path(?string $module = null, string $path = ''): string
    {
        $base = $this->modulesPath();

        if ($module === null || $module === '') {
            return $this->join($base, $path);
        }

        $name = ModuleName::parse($module);
        $modulePath = $this->join($base, $name->studly());

        $this->assertWithinModulesDirectory($modulePath);

        return $this->join($modulePath, $path);
    }

    public function child(string $module, string $relativePath): string
    {
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));

        if (in_array('..', explode(DIRECTORY_SEPARATOR, $relativePath), true) || str_contains($relativePath, "\0")) {
            throw new InvalidModuleNameException('The requested module path contains an unsafe segment.');
        }

        $modulePath = $this->path($module);
        $candidate = $relativePath === '' ? $modulePath : $modulePath.DIRECTORY_SEPARATOR.$relativePath;
        $root = rtrim($modulePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($candidate !== rtrim($modulePath, DIRECTORY_SEPARATOR) && ! str_starts_with($this->normalize($candidate), $this->normalize($root))) {
            throw new InvalidModuleNameException('The requested module path escapes its module directory.');
        }

        return $candidate;
    }

    public function normalizePath(string $path): string
    {
        return $this->normalize($path);
    }

    public function relativePath(string $absolute): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalized = str_replace('\\', '/', $absolute);

        if (str_starts_with($normalized, $base)) {
            return substr($normalized, strlen($base));
        }

        return $normalized;
    }

    public function assertWithinModulesDirectory(string $path): void
    {
        $modulesPath = $this->normalize($this->modulesPath());
        $normalized = $this->normalize($path);

        if ($normalized === $modulesPath) {
            return;
        }

        if (! str_starts_with($normalized, rtrim($modulesPath, '/').'/')) {
            throw InvalidModuleException::make(
                $path,
                'the resolved path escapes the configured modules directory.',
                'Module names cannot contain path separators or `..` segments.'
            );
        }
    }

    /**
     * Locate a file using PascalCase first, then lowercase Laravel-style paths.
     *
     * @param  array<int, string>  $candidates
     */
    public function firstExistingFile(string $modulePath, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $full = $this->join($modulePath, $candidate);
            if ($this->files->isFile($full)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $candidates
     */
    public function firstExistingDirectory(string $modulePath, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $full = $this->join($modulePath, $candidate);
            if ($this->files->isDirectory($full)) {
                return $candidate;
            }
        }

        return null;
    }

    public function join(string $base, string $path = ''): string
    {
        $base = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR);

        if ($path === '') {
            return $base;
        }

        $path = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $base.DIRECTORY_SEPARATOR.$path;
    }

    private function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $parts = [];

        foreach (explode('/', $normalized) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        $prefix = str_starts_with($normalized, '/') ? '/' : '';

        return $prefix.implode('/', $parts);
    }
}
