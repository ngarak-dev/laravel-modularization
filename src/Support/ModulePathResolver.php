<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use NgarakDev\Modularization\Exceptions\InvalidModuleNameException;

final class ModulePathResolver
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function module(string $name): string
    {
        $normalized = $this->normalizeName($name);

        return $this->basePath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $normalized);
    }

    public function normalizeName(string $name): string
    {
        $name = trim(str_replace('/', '\\', $name), '\\');

        if ($name === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)*$/', $name)) {
            throw new InvalidModuleNameException(
                "Invalid module name [{$name}]. Use letters, numbers, and namespace separators only."
            );
        }

        return implode('\\', array_map(static fn (string $part): string => ucfirst($part), explode('\\', $name)));
    }

    public function child(string $module, string $relativePath): string
    {
        $path = $this->module($module);
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
        if (in_array('..', explode(DIRECTORY_SEPARATOR, $relativePath), true) || str_contains($relativePath, "\0")) {
            throw new InvalidModuleNameException('The requested module path contains an unsafe segment.');
        }
        $candidate = $path . DIRECTORY_SEPARATOR . $relativePath;
        $root = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (! str_starts_with($candidate, $root) && $candidate !== rtrim($path, DIRECTORY_SEPARATOR)) {
            throw new InvalidModuleNameException('The requested module path escapes its module directory.');
        }

        return $candidate;
    }
}
