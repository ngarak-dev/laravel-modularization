<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use Illuminate\Support\Str;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;

/**
 * Validates and normalizes a module or class name so generated paths
 * cannot escape the configured modules directory.
 */
final class ModuleName
{
    private function __construct(
        private readonly string $original,
        private readonly string $studly,
    ) {}

    public static function parse(string $name): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidModuleException::invalidName($name);
        }

        if (preg_match('/[\\/\\\\.\\0\\:]/', $trimmed) === 1) {
            throw InvalidModuleException::invalidName($name);
        }

        if (str_contains($trimmed, '..')) {
            throw InvalidModuleException::invalidName($name);
        }

        $studly = Str::studly($trimmed);

        if ($studly === '' || preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly) !== 1) {
            throw InvalidModuleException::invalidName($name);
        }

        return new self($trimmed, $studly);
    }

    /**
     * Parse a class name that may include nested namespaces (Admin/Widget).
     */
    public static function parseClass(string $name): self
    {
        $normalized = str_replace('\\', '/', trim($name));

        if (str_contains($normalized, '..')) {
            throw InvalidModuleException::invalidName($name);
        }
        $segments = array_filter(explode('/', $normalized), static fn (string $part): bool => $part !== '');

        if ($segments === []) {
            throw InvalidModuleException::invalidName($name);
        }

        $studlySegments = [];

        foreach ($segments as $segment) {
            if (str_contains($segment, '..') || preg_match('/[^A-Za-z0-9_]/', $segment) === 1) {
                throw InvalidModuleException::invalidName($name);
            }

            $studly = Str::studly($segment);

            if ($studly === '' || preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly) !== 1) {
                throw InvalidModuleException::invalidName($name);
            }

            $studlySegments[] = $studly;
        }

        return new self(implode('/', $studlySegments), implode('\\', $studlySegments));
    }

    public function original(): string
    {
        return $this->original;
    }

    public function studly(): string
    {
        return $this->studly;
    }

    public function lower(): string
    {
        return strtolower($this->basename());
    }

    public function kebab(): string
    {
        return Str::kebab($this->basename());
    }

    public function snake(): string
    {
        return Str::snake($this->basename());
    }

    public function snakePlural(): string
    {
        return Str::plural($this->snake());
    }

    public function basename(): string
    {
        $parts = explode('\\', $this->studly);

        return end($parts);
    }

    public function relativePath(): string
    {
        return str_replace('\\', '/', $this->studly);
    }
}
